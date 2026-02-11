<?php
class HabitController {
    private $conn;
    private $habitModel;
    private $notificationModel;
    
    public function __construct($db) {
        $this->conn = $db;
        require_once '../models/Habit.php';
        require_once '../models/Notification.php';
        $this->habitModel = new Habit($db);
        $this->notificationModel = new Notification($db);
    }
    



    /**
     * Create a new habit
     */
    public function createHabit($user_id, $data) {
        $errors = $this->validateHabitData($data);
        
        if (!empty($errors)) {
            return array('success' => false, 'errors' => $errors);
        }
        
        $habit_id = $this->habitModel->createHabit($user_id, $data);
        
        if ($habit_id) {
            // Create notification for habit creation
            $this->notificationModel->createNotification($user_id, 
                'Habit Created', 
                "You've created a new habit: '{$data['title']}'. Keep up the good work!",
                'habit_created'
            );
            
            return array('success' => true, 'habit_id' => $habit_id, 'message' => 'Habit created successfully');
        } else {
            return array('success' => false, 'message' => 'Failed to create habit');
        }
    }
    
    /**
     * Update an existing habit
     */
    public function updateHabit($habit_id, $user_id, $data) {
        // Check if habit belongs to user
        if (!$this->habitModel->checkHabitOwnership($habit_id, $user_id)) {
            return array('success' => false, 'message' => 'Habit not found or unauthorized');
        }
        
        $errors = $this->validateHabitData($data, true);
        
        if (!empty($errors)) {
            return array('success' => false, 'errors' => $errors);
        }
        
        if ($this->habitModel->updateHabit($habit_id, $data)) {
            return array('success' => true, 'message' => 'Habit updated successfully');
        } else {
            return array('success' => false, 'message' => 'Failed to update habit');
        }
    }
    
    /**
     * Delete a habit
     */
    public function deleteHabit($habit_id, $user_id) {
        // Check if habit belongs to user
        if (!$this->habitModel->checkHabitOwnership($habit_id, $user_id)) {
            return array('success' => false, 'message' => 'Habit not found or unauthorized');
        }
        
        if ($this->habitModel->deleteHabit($habit_id)) {
            return array('success' => true, 'message' => 'Habit deleted successfully');
        } else {
            return array('success' => false, 'message' => 'Failed to delete habit');
        }
    }
    
    /**
     * Toggle habit completion for today
     */
    public function toggleHabitCompletion($habit_id, $user_id) {
        // Check if habit belongs to user
        if (!$this->habitModel->checkHabitOwnership($habit_id, $user_id)) {
            return array('success' => false, 'message' => 'Habit not found or unauthorized');
        }
        
        $result = $this->habitModel->toggleCompletion($habit_id);
        
        if ($result['success']) {
            $habit = $this->habitModel->getHabitById($habit_id);
            
            // Create notification
            if ($result['completed']) {
                $this->notificationModel->createNotification($user_id,
                    'Habit Completed',
                    "Great job! You've completed '{$habit['title']}' for today!",
                    'habit_completed'
                );
                
                // Check for streak milestone
                $streak = $this->habitModel->getHabitStreak($habit_id);
                if ($streak % 7 == 0) {
                    $this->notificationModel->createNotification($user_id,
                        'Streak Milestone!',
                        "Amazing! You've maintained a {$streak}-day streak for '{$habit['title']}'!",
                        'streak_milestone'
                    );
                }
            }
            
            return array(
                'success' => true, 
                'completed' => $result['completed'],
                'streak' => $this->habitModel->getHabitStreak($habit_id),
                'message' => $result['completed'] ? 'Habit marked as completed' : 'Habit marked as incomplete'
            );
        } else {
            return array('success' => false, 'message' => 'Failed to toggle completion');
        }
    }
    
    /**
     * Get all habits for a user
     */
    public function getUserHabits($user_id, $filter = 'all') {
        $habits = $this->habitModel->getUserHabits($user_id, $filter);
        
        // Add completion status for today
        foreach ($habits as &$habit) {
            $habit['completed_today'] = $this->habitModel->isCompletedToday($habit['id']);
            $habit['current_streak'] = $this->habitModel->getHabitStreak($habit['id']);
        }
        
        return array('success' => true, 'habits' => $habits);
    }
    
    /**
     * Get habit details
     */
    public function getHabitDetails($habit_id, $user_id) {
        // Check if habit belongs to user
        if (!$this->habitModel->checkHabitOwnership($habit_id, $user_id)) {
            return array('success' => false, 'message' => 'Habit not found or unauthorized');
        }
        
        $habit = $this->habitModel->getHabitById($habit_id);
        
        if ($habit) {
            // Add additional data
            $habit['completion_history'] = $this->habitModel->getCompletionHistory($habit_id, 30);
            $habit['current_streak'] = $this->habitModel->getHabitStreak($habit_id);
            $habit['longest_streak'] = $this->habitModel->getHabitLongestStreak($habit_id);
            $habit['completion_rate'] = $this->habitModel->getHabitCompletionRate($habit_id);
            
            return array('success' => true, 'habit' => $habit);
        } else {
            return array('success' => false, 'message' => 'Habit not found');
        }
    }
    
    /**
     * Bulk update habit completions
     */
    public function bulkUpdateCompletions($user_id, $completions) {
        $results = array();
        $success_count = 0;
        
        foreach ($completions as $habit_id => $completed) {
            // Check ownership
            if ($this->habitModel->checkHabitOwnership($habit_id, $user_id)) {
                $result = $this->habitModel->setCompletionStatus($habit_id, $completed);
                if ($result) {
                    $success_count++;
                    $results[$habit_id] = array('success' => true);
                } else {
                    $results[$habit_id] = array('success' => false, 'message' => 'Update failed');
                }
            } else {
                $results[$habit_id] = array('success' => false, 'message' => 'Unauthorized');
            }
        }
        
        if ($success_count > 0) {
            // Create notification for bulk update
            $this->notificationModel->createNotification($user_id,
                'Daily Habits Updated',
                "You've updated {$success_count} habit(s) for today. Keep it up!",
                'bulk_update'
            );
        }
        
        return array(
            'success' => true,
            'updated_count' => $success_count,
            'results' => $results
        );
    }
    
    /**
     * Get habit statistics
     */
    public function getHabitStatistics($user_id) {
        $stats = array();
        
        $stats['total_habits'] = $this->habitModel->getTotalHabits($user_id);
        $stats['active_habits'] = $this->habitModel->getActiveHabitsCount($user_id);
        $stats['completed_today'] = $this->habitModel->getTodayCompletedCount($user_id);
        $stats['completion_rate'] = $this->habitModel->getOverallCompletionRate($user_id);
        $stats['current_streak'] = $this->habitModel->getCurrentStreak($user_id);
        $stats['longest_streak'] = $this->habitModel->getLongestStreak($user_id);
        
        // Weekly completion data
        $stats['weekly_completion'] = $this->habitModel->getWeeklyCompletionData($user_id);
        
        // Best performing habits
        $stats['best_habits'] = $this->habitModel->getBestPerformingHabits($user_id, 3);
        
        // Habits needing improvement
        $stats['needs_improvement'] = $this->habitModel->getHabitsNeedingImprovement($user_id, 3);
        
        return array('success' => true, 'stats' => $stats);
    }
    
    /**
     * Validate habit data
     */
    private function validateHabitData($data, $isUpdate = false) {
        $errors = array();
        
        if (!$isUpdate || isset($data['title'])) {
            if (empty($data['title'])) {
                $errors['title'] = 'Title is required';
            } elseif (strlen($data['title']) > 100) {
                $errors['title'] = 'Title must be less than 100 characters';
            }
        }
        
        if (isset($data['description']) && strlen($data['description']) > 500) {
            $errors['description'] = 'Description must be less than 500 characters';
        }
        
        if (isset($data['frequency']) && !in_array($data['frequency'], ['daily', 'weekly', 'weekdays', 'custom'])) {
            $errors['frequency'] = 'Invalid frequency value';
        }
        
        if (isset($data['reminder_time'])) {
            if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $data['reminder_time'])) {
                $errors['reminder_time'] = 'Invalid time format (HH:MM)';
            }
        }
        
        if (isset($data['difficulty']) && !in_array($data['difficulty'], ['easy', 'medium', 'hard'])) {
            $errors['difficulty'] = 'Invalid difficulty value';
        }
        
        return $errors;
    }
    
    /**
     * Get upcoming reminders
     */
    public function getUpcomingReminders($user_id) {
        $habits = $this->habitModel->getHabitsWithReminders($user_id);
        $reminders = array();
        
        foreach ($habits as $habit) {
            if ($habit['reminder_time'] && !$habit['completed_today']) {
                $reminders[] = array(
                    'habit_id' => $habit['id'],
                    'title' => $habit['title'],
                    'reminder_time' => $habit['reminder_time'],
                    'reminder_sent' => $habit['reminder_sent']
                );
            }
        }
        
        return array('success' => true, 'reminders' => $reminders);
    }
    
    /**
     * Mark reminder as sent
     */
    public function markReminderSent($habit_id, $user_id) {
        if ($this->habitModel->checkHabitOwnership($habit_id, $user_id)) {
            $this->habitModel->markReminderSent($habit_id);
            return array('success' => true, 'message' => 'Reminder marked as sent');
        }
        
        return array('success' => false, 'message' => 'Unauthorized');
    }
    
    /**
     * Reset habit streak (for testing or correction)
     */
    public function resetHabitStreak($habit_id, $user_id, $new_streak = 0) {
        // Check if habit belongs to user
        if (!$this->habitModel->checkHabitOwnership($habit_id, $user_id)) {
            return array('success' => false, 'message' => 'Habit not found or unauthorized');
        }
        
        if ($this->habitModel->resetStreak($habit_id, $new_streak)) {
            return array('success' => true, 'message' => 'Streak reset successfully');
        } else {
            return array('success' => false, 'message' => 'Failed to reset streak');
        }
    }
}
?>