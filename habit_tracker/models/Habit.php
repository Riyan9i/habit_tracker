<?php
class Habit {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create($user_id, $data) {
        $name = $this->conn->real_escape_string($data['name']);
        $category = $this->conn->real_escape_string($data['category']);
        $frequency = $this->conn->real_escape_string($data['frequency']);
        $reminder_time = !empty($data['reminder_time']) ? $this->conn->real_escape_string($data['reminder_time']) : null;
        $description = isset($data['description']) ? $this->conn->real_escape_string($data['description']) : null;
        
        $sql = "INSERT INTO habits (user_id, name, category, frequency, reminder_time, description, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isssss", $user_id, $name, $category, $frequency, $reminder_time, $description);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Habit created successfully!', 'id' => $stmt->insert_id];
        } else {
            return ['success' => false, 'message' => 'Failed to create habit: ' . $stmt->error];
        }
    }
    
    public function update($habit_id, $data) {
        $name = $this->conn->real_escape_string($data['name']);
        $category = $this->conn->real_escape_string($data['category']);
        $frequency = $this->conn->real_escape_string($data['frequency']);
        $reminder_time = !empty($data['reminder_time']) ? $this->conn->real_escape_string($data['reminder_time']) : null;
        
        $sql = "UPDATE habits SET name = ?, category = ?, frequency = ?, reminder_time = ?, updated_at = NOW() 
                WHERE id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssssi", $name, $category, $frequency, $reminder_time, $habit_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Habit updated successfully!'];
        } else {
            return ['success' => false, 'message' => 'Failed to update habit: ' . $stmt->error];
        }
    }
    
    public function delete($habit_id) {
        $sql = "DELETE FROM habits WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $habit_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Habit deleted successfully!'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete habit: ' . $stmt->error];
        }
    }
    
    public function getAll($user_id) {
        $sql = "SELECT * FROM habits WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $habits = [];
        while ($row = $result->fetch_assoc()) {
            $habits[] = $row;
        }
        return $habits;
    }
    
    public function getTodayHabits($user_id) {
        $today = date('Y-m-d');
        $day_of_week = date('w'); // 0 = Sunday, 6 = Saturday
        
        $sql = "SELECT h.*, 
                (SELECT COUNT(*) FROM habit_completions hc 
                 WHERE hc.habit_id = h.id AND DATE(hc.completion_date) = ?) as completed
                FROM habits h 
                WHERE h.user_id = ? AND h.is_active = 1 
                AND (h.frequency = 'Daily' OR (h.frequency = 'Weekly' AND DAYOFWEEK(?) = ?))
                ORDER BY h.reminder_time ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sisi", $today, $user_id, $today, $day_of_week);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $habits = [];
        while ($row = $result->fetch_assoc()) {
            $row['completed'] = (bool)$row['completed'];
            $habits[] = $row;
        }
        return $habits;
    }
    
    public function markAsCompleted($habit_id) {
        $today = date('Y-m-d');
        
        // Check if already completed today
        $check_sql = "SELECT id FROM habit_completions WHERE habit_id = ? AND DATE(completion_date) = ?";
        $check_stmt = $this->conn->prepare($check_sql);
        $check_stmt->bind_param("is", $habit_id, $today);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            return ['success' => false, 'message' => 'Habit already completed today'];
        }
        
        // Insert completion
        $sql = "INSERT INTO habit_completions (habit_id, completion_date, completed_at) VALUES (?, ?, NOW())";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $habit_id, $today);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Habit marked as completed!'];
        } else {
            return ['success' => false, 'message' => 'Failed to mark habit as completed: ' . $stmt->error];
        }
    }
    
    public function getCurrentStreak($user_id) {
        $sql = "SELECT COUNT(DISTINCT DATE(completion_date)) as streak 
                FROM habit_completions hc 
                JOIN habits h ON hc.habit_id = h.id 
                WHERE h.user_id = ? 
                AND hc.completion_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ORDER BY hc.completion_date DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['streak'] ?? 0;
    }
    
   public function getHabitCompletionRate($habit_id) {
    // Default values
    $completed = 0;
    $days = 0;
    $completion_rate = 0;

    $sql = "SELECT 
            (SELECT COUNT(*) FROM habit_completions WHERE habit_id = ?) as completed,
            (SELECT DATEDIFF(CURDATE(), MIN(created_at)) + 1 FROM habits WHERE id = ?) as days
            FROM dual";

    if ($stmt = $this->conn->prepare($sql)) {
        $stmt->bind_param("ii", $habit_id, $habit_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $completed = isset($row['completed']) ? (int)$row['completed'] : 0;
                $days = isset($row['days']) ? max(1, (int)$row['days']) : 1; // avoid division by zero
                $completion_rate = round(($completed / $days) * 100);
            }
        }
        $stmt->close();
    }

    return [
        'completion_rate' => $completion_rate,
        'completed' => $completed,
        'total' => $days
    ];
}


    
    public function getCategoryColor($category) {
        $colors = [
            'Health' => '#4CAF50',
            'Study' => '#2196F3',
            'Fitness' => '#FF5722',
            'Work' => '#9C27B0',
            'Personal' => '#FFC107',
            'Social' => '#00BCD4',
            'Other' => '#795548'
        ];
        return $colors[$category] ?? '#6c757d';
    }


   public function getCompletedHabitsToday($user_id) {
    $today = date('Y-m-d');
    $sql = "SELECT h.* 
            FROM habits h 
            JOIN habit_completions hc ON hc.habit_id = h.id
            WHERE h.user_id = ? AND DATE(hc.completion_date) = ?";
    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();

    $habits = [];
    while ($row = $result->fetch_assoc()) {
        $habits[] = $row;
    }
    return $habits;
}

/**
 * Check if a habit is completed on a given date
 *
 * @param int $habit_id
 * @param string|null $date Format: 'Y-m-d'. If null, defaults to today.
 * @return bool
 */
public function isCompletedOnDate($habit_id, $date = null) {
    if (!$date) {
        $date = date('Y-m-d');
    }

    $sql = "SELECT COUNT(*) as count
            FROM habit_completions
            WHERE habit_id = ? AND DATE(completion_date) = ?";

    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param("is", $habit_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return ($row['count'] > 0);
}


    

    public function getWeeklyProgress($user_id) {
    // আজ থেকে 7 দিন আগের তারিখ
    $start_date = date('Y-m-d', strtotime('-6 days'));
    $end_date = date('Y-m-d');

    // Completed habits count
    $sql_completed = "SELECT COUNT(DISTINCT h.id) as completed
                      FROM habits h
                      JOIN habit_completions hc ON h.id = hc.habit_id
                      WHERE h.user_id = ? 
                      AND hc.completion_date BETWEEN ? AND ?";

    $stmt = $this->conn->prepare($sql_completed);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $completed_row = $result->fetch_assoc();
    $completed_count = $completed_row['completed'] ?? 0;

    // Total active habits
    $sql_total = "SELECT COUNT(*) as total 
                  FROM habits 
                  WHERE user_id = ? AND is_active = 1";
    $stmt2 = $this->conn->prepare($sql_total);
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $total_row = $result2->fetch_assoc();
    $total_habits = $total_row['total'] ?? 0;

    // Completion rate
    $completion_rate = $total_habits > 0 ? round(($completed_count / $total_habits) * 100) : 0;

    return [
        'completed' => $completed_count,
        'total' => $total_habits,
        'completion_rate' => $completion_rate
    ];
}



public function getRecentActivity($user_id, $limit = 5) {
    $sql = "SELECT h.name AS habit_name,
    hc.completion_date AS completed_at,
            
            CASE 
                WHEN hc.id IS NOT NULL THEN 'Complete'
                ELSE 'Incomplete'
            END AS status
        FROM habits h
        LEFT JOIN habit_completions hc 
            ON h.id = hc.habit_id
        WHERE h.user_id = ?
        ORDER BY hc.completion_date DESC
        LIMIT ?";

    
    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    
    return $activities;
}

public function getMonthlyCompleted($user_id) {
    $start_date = date('Y-m-01'); // মাসের প্রথম দিন
    $end_date = date('Y-m-t');    // মাসের শেষ দিন

    $sql = "SELECT COUNT(*) as completed 
            FROM habit_completions hc
            JOIN habits h ON hc.habit_id = h.id
            WHERE h.user_id = ? 
            AND DATE(hc.completion_date) BETWEEN ? AND ?";

    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return $row['completed'] ?? 0;
}

public function getMonthlySuccessRate($user_id) {
    // মাসের প্রথম এবং শেষ দিন
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');

    // মাসে complete করা habit এর সংখ্যা
    $sql_completed = "SELECT COUNT(DISTINCT habit_id) as completed 
                      FROM habit_completions hc
                      JOIN habits h ON hc.habit_id = h.id
                      WHERE h.user_id = ? 
                      AND DATE(hc.completion_date) BETWEEN ? AND ?";

    $stmt = $this->conn->prepare($sql_completed);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $completed = $result->fetch_assoc()['completed'] ?? 0;

    // মাসে তৈরি habit এর সংখ্যা
    $sql_total = "SELECT COUNT(*) as total 
                  FROM habits 
                  WHERE user_id = ? 
                  AND DATE(created_at) <= ?"; // এখন পর্যন্ত তৈরি সব habit

    $stmt2 = $this->conn->prepare($sql_total);
    $stmt2->bind_param("is", $user_id, $end_date);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $total = $result2->fetch_assoc()['total'] ?? 0;

    if ($total > 0) {
        return round(($completed / $total) * 100);
    } else {
        return 0;
    }
}


public function getOverallCompletionRate($user_id) {
    // Total active habits
    $sql_total = "SELECT COUNT(*) as total FROM habits WHERE user_id = ? AND is_active = 1";
    $stmt_total = $this->conn->prepare($sql_total);
    $stmt_total->bind_param("i", $user_id);
    $stmt_total->execute();
    $result_total = $stmt_total->get_result();
    $row_total = $result_total->fetch_assoc();
    $total_habits = $row_total['total'] ?? 0;

    if ($total_habits == 0) {
        return 0; // কোনো habit নেই
    }

    // Total completed entries
    $sql_completed = "SELECT COUNT(*) as completed 
                      FROM habit_completions hc
                      JOIN habits h ON hc.habit_id = h.id
                      WHERE h.user_id = ?";
    $stmt_completed = $this->conn->prepare($sql_completed);
    $stmt_completed->bind_param("i", $user_id);
    $stmt_completed->execute();
    $result_completed = $stmt_completed->get_result();
    $row_completed = $result_completed->fetch_assoc();
    $completed_count = $row_completed['completed'] ?? 0;

    // Overall completion rate in %
    $completion_rate = round(($completed_count / $total_habits) * 100);

    return $completion_rate;
}
public function getWeeklyReport($user_id) {
    // Last 7 days
    $start_date = date('Y-m-d', strtotime('-6 days'));
    $end_date = date('Y-m-d');

    // Get habits and their completions
    $sql = "SELECT h.id, h.name,
                   (SELECT COUNT(*) FROM habit_completions hc WHERE hc.habit_id = h.id AND DATE(hc.completed_at) BETWEEN ? AND ?) as completed
            FROM habits h
            WHERE h.user_id = ?
            ORDER BY h.name";

    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param("ssi", $start_date, $end_date, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $report = [];
    while ($row = $result->fetch_assoc()) {
        $report[] = $row;
    }

    return $report;
}
public function getLongestStreak($habit_id) {
    // Fetch all completion dates for this habit
    $sql = "SELECT DATE(completed_at) as completed_date
            FROM habit_completions
            WHERE habit_id = ?
            ORDER BY completed_at ASC";

    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param("i", $habit_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $dates = [];
    while ($row = $result->fetch_assoc()) {
        $dates[] = $row['completed_date'];
    }

    if (empty($dates)) return 0;

    // Calculate longest consecutive streak
    $longest = 1;
    $current = 1;

    for ($i = 1; $i < count($dates); $i++) {
        $prev = strtotime($dates[$i - 1]);
        $curr = strtotime($dates[$i]);

        if (($curr - $prev) === 86400) { // consecutive day
            $current++;
        } else {
            $current = 1;
        }

        if ($current > $longest) {
            $longest = $current;
        }
    }

    return $longest;
}


public function getHabitStreak($user_id) {
    // প্রথমে user এর সব habit ids নাও
    $sql = "SELECT id FROM habits WHERE user_id = ?";
    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $habit_ids = [];
    while ($row = $result->fetch_assoc()) {
        $habit_ids[] = $row['id'];
    }

    if(empty($habit_ids)) return 0;

    // habit_ids কে comma separated string বানাও
    $ids = implode(",", $habit_ids);

    // এখন habit_completions থেকে সব completed dates নিয়ে আসো
    $sql2 = "SELECT DISTINCT DATE(completed_at) as completed_date
             FROM habit_completions
             WHERE habit_id IN ($ids)
             ORDER BY completed_date ASC";

    $result2 = $this->conn->query($sql2);

    $dates = [];
    while($row = $result2->fetch_assoc()) {
        $dates[] = $row['completed_date'];
    }

    if(empty($dates)) return 0;

    $longest_streak = 0;
    $current_streak = 1;

    for($i = 1; $i < count($dates); $i++) {
        $prev = strtotime($dates[$i - 1]);
        $curr = strtotime($dates[$i]);

        if(($curr - $prev) === 86400) {
            $current_streak++;
        } else {
            $current_streak = 1;
        }

        if($current_streak > $longest_streak) {
            $longest_streak = $current_streak;
        }
    }

    // শেষ streak current কিনা check
    $today = strtotime(date('Y-m-d'));
    $last_completed = strtotime(end($dates));

    if(($today - $last_completed) === 86400 || $today == $last_completed) {
        return max($longest_streak, $current_streak);
    }

    return $longest_streak;
}


public function getHabitPerformance($user_id) {
    $sql = "SELECT h.id, h.name, h.category,
                   COUNT(hc.id) AS completed_count,
                   (SELECT COUNT(*) FROM habits WHERE user_id = ?) AS total
            FROM habits h
            LEFT JOIN habit_completions hc ON h.id = hc.habit_id
            WHERE h.user_id = ?
            GROUP BY h.id";
    
    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $habits = [];
    while ($row = $result->fetch_assoc()) {
        // Completion rate
        $completion_rate = $row['total'] > 0 ? round(($row['completed_count'] / $row['total']) * 100) : 0;

        // Calculate streak
        $streak = 0;
        $habit_id = $row['id'];
        $sql_streak = "SELECT completion_date 
                       FROM habit_completions 
                       WHERE habit_id = ? 
                       ORDER BY completion_date DESC";
        $stmt_streak = $this->conn->prepare($sql_streak);
        $stmt_streak->bind_param("i", $habit_id);
        $stmt_streak->execute();
        $result_streak = $stmt_streak->get_result();

        $today = date('Y-m-d');
        $current_date = $today;

        while ($completion = $result_streak->fetch_assoc()) {
            if ($completion['completion_date'] == $current_date) {
                $streak++;
                $current_date = date('Y-m-d', strtotime($current_date . ' -1 day'));
            } else {
                break;
            }
        }

        $habits[] = [
            'name' => $row['name'],
            'category' => $row['category'],
            'completed' => $row['completed_count'],
            'total' => $row['total'],
            'completion_rate' => $completion_rate,
            'streak' => $streak
        ];
    }

    return $habits;
}









}
?>