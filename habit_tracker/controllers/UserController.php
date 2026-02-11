<?php
class UserController {
    private $conn;
    private $habitModel;
    private $calorieModel;
    
    public function __construct($db) {
        $this->conn = $db;
        require_once '../models/Habit.php';
        require_once '../models/Calorie.php';
        $this->habitModel = new Habit($db);
        $this->calorieModel = new Calorie($db);
    }
    
    /**
     * Get user dashboard data
     */
    public function getDashboardData($user_id) {
        $data = array();
        
        // Get today's habits
        $data['today_habits'] = $this->habitModel->getTodayHabits($user_id);
        $data['completed_today'] = count(array_filter($data['today_habits'], function($h) {
            return $h['completed'];
        }));
        
        // Get streak
        $data['streak'] = $this->habitModel->getCurrentStreak($user_id);
        
        // Get calorie data
        $data['calories_today'] = $this->calorieModel->getTodayTotalCalories($user_id);
        $data['calories_burned'] = $this->calorieModel->getTodayBurnedCalories($user_id);
        
        // Get weekly progress
        $data['weekly_progress'] = $this->habitModel->getWeeklyProgress($user_id);
        
        // Get recent activity
        $data['recent_activity'] = $this->habitModel->getRecentActivity($user_id, 5);
        
        return $data;
    }
    
    /**
     * Get user profile data
     */
    public function getProfileData($user_id) {
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $user = $result->fetch_assoc();
        
        if ($user) {
            // Remove sensitive data
            unset($user['password']);
            unset($user['verification_code']);
            
            // Add stats
            $user['total_habits'] = $this->habitModel->getTotalHabits($user_id);
            $user['current_streak'] = $this->habitModel->getCurrentStreak($user_id);
            $user['completion_rate'] = $this->habitModel->getOverallCompletionRate($user_id);
            
            // Calculate BMI if data available
            if ($user['height'] && $user['weight']) {
                $height_m = $user['height'] / 100;
                $user['bmi'] = round($user['weight'] / ($height_m * $height_m), 1);
            }
        }
        
        return $user;
    }
    
    /**
     * Update user profile
     */
    public function updateProfile($user_id, $data) {
        $updates = array();
        $params = array();
        $types = '';
        
        // Build dynamic update query
        $allowed_fields = ['name', 'email', 'phone', 'gender', 'age', 'height', 'weight', 
                          'weight_goal', 'target_weight', 'activity_level', 'bio'];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
                $types .= 's';
            }
        }
        
        if (empty($updates)) {
            return array('success' => false, 'message' => 'No data to update');
        }
        
        // Add user_id to params
        $params[] = $user_id;
        $types .= 'i';
        
        // Build and execute query
        $sql = "UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            return array('success' => true, 'message' => 'Profile updated successfully');
        } else {
            return array('success' => false, 'message' => 'Failed to update profile: ' . $stmt->error);
        }
    }
    
    /**
     * Change password
     */
    public function changePassword($user_id, $current_password, $new_password) {
        // Get current password hash
        $sql = "SELECT password FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            return array('success' => false, 'message' => 'User not found');
        }
        
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            return array('success' => false, 'message' => 'Current password is incorrect');
        }
        
        // Hash new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password
        $sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            return array('success' => true, 'message' => 'Password changed successfully');
        } else {
            return array('success' => false, 'message' => 'Failed to change password');
        }
    }
    
    /**
     * Get user statistics
     */
    public function getUserStats($user_id) {
        $stats = array();
        
        // Habit stats
        $stats['total_habits'] = $this->habitModel->getTotalHabits($user_id);
        $stats['current_streak'] = $this->habitModel->getCurrentStreak($user_id);
        $stats['longest_streak'] = $this->habitModel->getLongestStreak($user_id);
        $stats['completion_rate'] = $this->habitModel->getOverallCompletionRate($user_id);
        
        // Calorie stats
        $stats['calories_today'] = $this->calorieModel->getTodayTotalCalories($user_id);
        $stats['calories_week'] = $this->calorieModel->getWeeklyCalorieData($user_id);
        
        // Activity stats
        $stats['habits_completed_today'] = count($this->habitModel->getCompletedHabitsToday($user_id));
        $stats['habits_completed_week'] = $this->habitModel->getWeeklyCompleted($user_id);
        $stats['habits_completed_month'] = $this->habitModel->getMonthlyCompleted($user_id);
        
        return $stats;
    }
    
    /**
     * Get user reports
     */
    public function getUserReports($user_id, $period = 'week') {
        $reports = array();
        
        switch ($period) {
            case 'week':
                $reports['habits'] = $this->habitModel->getWeeklyReport($user_id);
                $reports['calories'] = $this->calorieModel->getWeeklyCalorieData($user_id);
                break;
            case 'month':
                $reports['habits'] = $this->habitModel->getMonthlyReport($user_id);
                $reports['calories'] = $this->calorieModel->getMonthlyCalorieData($user_id);
                break;
            default:
                $reports['habits'] = $this->habitModel->getWeeklyReport($user_id);
                $reports['calories'] = $this->calorieModel->getWeeklyCalorieData($user_id);
        }
        
        return $reports;
    }
    
    /**
     * Update notification preferences
     */
    public function updateNotificationPref($user_id, $preferences) {
        $email_notifications = isset($preferences['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($preferences['sms_notifications']) ? 1 : 0;
        
        // Determine notification preference
        if ($email_notifications && $sms_notifications) {
            $notification_pref = 'Both';
        } elseif ($email_notifications) {
            $notification_pref = 'Email';
        } elseif ($sms_notifications) {
            $notification_pref = 'SMS';
        } else {
            $notification_pref = 'None';
        }
        
        $sql = "UPDATE users SET notification_pref = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $notification_pref, $user_id);
        
        if ($stmt->execute()) {
            return array('success' => true, 'message' => 'Notification preferences updated');
        } else {
            return array('success' => false, 'message' => 'Failed to update preferences');
        }
    }
    
    /**
     * Delete user account
     */
    public function deleteAccount($user_id) {
        // Start transaction
        $this->conn->begin_transaction();
        
        try {
            // Delete user data from related tables
            $tables = ['habits', 'habit_completions', 'food_entries', 'activity_calories', 'notifications'];
            
            foreach ($tables as $table) {
                $sql = "DELETE FROM $table WHERE user_id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
            }
            
            // Delete user from users table
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            // Commit transaction
            $this->conn->commit();
            
            return array('success' => true, 'message' => 'Account deleted successfully');
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->conn->rollback();
            return array('success' => false, 'message' => 'Failed to delete account: ' . $e->getMessage());
        }
    }
}
?>