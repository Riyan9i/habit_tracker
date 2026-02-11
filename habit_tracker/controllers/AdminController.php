<?php
class AdminController {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Get admin dashboard statistics
     */
    public function getDashboardStats() {
        $stats = array();
        
        // Total users (excluding admins)
        $result = $this->conn->query("SELECT COUNT(*) as count FROM users WHERE is_admin = 0");
        $stats['total_users'] = $result->fetch_assoc()['count'];
        
        // Active users today
        $result = $this->conn->query("SELECT COUNT(DISTINCT user_id) as count FROM habit_completions WHERE DATE(completed_at) = CURDATE()");
        $stats['active_today'] = $result->fetch_assoc()['count'];
        
        // Total habits
        $result = $this->conn->query("SELECT COUNT(*) as count FROM habits");
        $stats['total_habits'] = $result->fetch_assoc()['count'];
        
        // Average completion rate
        $result = $this->conn->query("
            SELECT ROUND(AVG(completion_rate), 2) as rate FROM (
                SELECT h.user_id, 
                       (COUNT(hc.id) / (DATEDIFF(CURDATE(), MIN(h.created_at)) + 1)) * 100 as completion_rate
                FROM habits h
                LEFT JOIN habit_completions hc ON h.id = hc.habit_id AND DATE(hc.completion_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY h.user_id
            ) as rates
        ");
        $stats['avg_completion'] = $result->fetch_assoc()['rate'] ?? 0;
        
        // New users today
        $result = $this->conn->query("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE() AND is_admin = 0");
        $stats['new_today'] = $result->fetch_assoc()['count'];
        
        // Blocked users
        $result = $this->conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 0 AND is_admin = 0");
        $stats['blocked_users'] = $result->fetch_assoc()['count'];
        
        // Habit categories distribution
        $result = $this->conn->query("SELECT category, COUNT(*) as count FROM habits GROUP BY category ORDER BY count DESC");
        $stats['categories'] = array();
        while ($row = $result->fetch_assoc()) {
            $stats['categories'][] = $row;
        }
        
        return $stats;
    }
    
    /**
     * Get all users with pagination
     */
    public function getUsers($page = 1, $limit = 10, $search = '') {
        $offset = ($page - 1) * $limit;
        
        $where = "WHERE is_admin = 0";
        if (!empty($search)) {
            $search = $this->conn->real_escape_string($search);
            $where .= " AND (name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
        }
        
        // Get total count
        $result = $this->conn->query("SELECT COUNT(*) as total FROM users $where");
        $total = $result->fetch_assoc()['total'];
        
        // Get users
        $sql = "SELECT * FROM users $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
        $result = $this->conn->query($sql);
        
        $users = array();
        while ($row = $result->fetch_assoc()) {
            // Get additional user stats
            $row['habits_count'] = $this->getUserHabitsCount($row['id']);
            $row['last_activity'] = $this->getUserLastActivity($row['id']);
            $users[] = $row;
        }
        
        return array(
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        );
    }
    
    /**
     * Get user habits count
     */
    private function getUserHabitsCount($user_id) {
        $result = $this->conn->query("SELECT COUNT(*) as count FROM habits WHERE user_id = $user_id");
        return $result->fetch_assoc()['count'];
    }
    
    /**
     * Get user last activity
     */
    private function getUserLastActivity($user_id) {
        $result = $this->conn->query("SELECT MAX(completed_at) as last_activity FROM habit_completions hc JOIN habits h ON hc.habit_id = h.id WHERE h.user_id = $user_id");
        $row = $result->fetch_assoc();
        return $row['last_activity'];
    }
    
    /**
     * Get user details
     */
    public function getUserDetails($user_id) {
        $sql = "SELECT * FROM users WHERE id = ? AND is_admin = 0";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $user = $result->fetch_assoc();
        
        if ($user) {
            // Get user habits
            $user['habits'] = $this->getUserHabits($user_id);
            
            // Get user statistics
            $user['stats'] = $this->getUserStatistics($user_id);
            
            // Get recent activity
            $user['recent_activity'] = $this->getUserRecentActivity($user_id);
        }
        
        return $user;
    }
    
    /**
     * Get user habits
     */
    private function getUserHabits($user_id) {
        $sql = "SELECT * FROM habits WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $habits = array();
        while ($row = $result->fetch_assoc()) {
            // Get habit completion rate
            $row['completion_rate'] = $this->getHabitCompletionRate($row['id']);
            $habits[] = $row;
        }
        
        return $habits;
    }
    
    /**
     * Get habit completion rate
     */
    private function getHabitCompletionRate($habit_id) {
        $sql = "SELECT 
                (SELECT COUNT(*) FROM habit_completions WHERE habit_id = ?) as completed,
                (SELECT DATEDIFF(CURDATE(), MIN(created_at)) + 1 FROM habits WHERE id = ?) as days
                FROM dual";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $habit_id, $habit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['days'] > 0) {
            return round(($row['completed'] / $row['days']) * 100);
        }
        return 0;
    }
    
    /**
     * Get user statistics
     */
    private function getUserStatistics($user_id) {
        $stats = array();
        
        // Total habits
        $result = $this->conn->query("SELECT COUNT(*) as count FROM habits WHERE user_id = $user_id");
        $stats['total_habits'] = $result->fetch_assoc()['count'];
        
        // Completed habits today
        $result = $this->conn->query("
            SELECT COUNT(DISTINCT hc.habit_id) as count 
            FROM habit_completions hc 
            JOIN habits h ON hc.habit_id = h.id 
            WHERE h.user_id = $user_id AND DATE(hc.completion_date) = CURDATE()
        ");
        $stats['completed_today'] = $result->fetch_assoc()['count'];
        
        // Current streak
        $result = $this->conn->query("
            SELECT COUNT(DISTINCT DATE(completion_date)) as streak 
            FROM habit_completions hc 
            JOIN habits h ON hc.habit_id = h.id 
            WHERE h.user_id = $user_id 
            AND hc.completion_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY hc.completion_date DESC
        ");
        $stats['current_streak'] = $result->fetch_assoc()['streak'] ?? 0;
        
        // Total calorie entries
        $result = $this->conn->query("SELECT COUNT(*) as count FROM food_entries WHERE user_id = $user_id");
        $stats['food_entries'] = $result->fetch_assoc()['count'];
        
        // Total activity entries
        $result = $this->conn->query("SELECT COUNT(*) as count FROM activity_calories WHERE user_id = $user_id");
        $stats['activity_entries'] = $result->fetch_assoc()['count'];
        
        return $stats;
    }
    
    /**
     * Get user recent activity
     */
    private function getUserRecentActivity($user_id) {
        $sql = "
            SELECT h.name as habit_name, hc.completion_date, hc.completed_at 
            FROM habit_completions hc 
            JOIN habits h ON hc.habit_id = h.id 
            WHERE h.user_id = ? 
            ORDER BY hc.completed_at DESC 
            LIMIT 10
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $activities = array();
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
        
        return $activities;
    }
    
    /**
     * Update user status
     */
    public function updateUserStatus($user_id, $status) {
        $is_active = $status === 'active' ? 1 : 0;
        
        $sql = "UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $is_active, $user_id);
        
        if ($stmt->execute()) {
            return array('success' => true, 'message' => 'User status updated');
        } else {
            return array('success' => false, 'message' => 'Failed to update user status');
        }
    }
    
    /**
     * Reset user password
     */
    public function resetUserPassword($user_id) {
        // Generate temporary password
        $temp_password = substr(md5(uniqid()), 0, 8);
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        
        $sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            return array(
                'success' => true,
                'message' => 'Password reset successful',
                'temp_password' => $temp_password
            );
        } else {
            return array('success' => false, 'message' => 'Failed to reset password');
        }
    }
    
    /**
     * Delete user
     */
    public function deleteUser($user_id) {
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
            $sql = "DELETE FROM users WHERE id = ? AND is_admin = 0";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            // Commit transaction
            $this->conn->commit();
            
            return array('success' => true, 'message' => 'User deleted successfully');
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->conn->rollback();
            return array('success' => false, 'message' => 'Failed to delete user: ' . $e->getMessage());
        }
    }
    
    /**
     * Get system settings
     */
    public function getSystemSettings() {
        $sql = "SELECT * FROM admin_settings LIMIT 1";
        $result = $this->conn->query($sql);
        return $result->fetch_assoc();
    }
    
    /**
     * Update system settings
     */
    public function updateSystemSettings($data) {
        $allowed_fields = ['smtp_host', 'smtp_username', 'smtp_password', 'smtp_port', 
                          'sms_api_key', 'sms_sender_id', 'sms_provider', 'theme_color', 'maintenance_mode'];
        
        $updates = array();
        $params = array();
        $types = '';
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
                $types .= 's';
            }
        }
        
        if (empty($updates)) {
            return array('success' => false, 'message' => 'No data to update');
        }
        
        // Build and execute query
        $sql = "UPDATE admin_settings SET " . implode(', ', $updates) . ", updated_at = NOW()";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            return array('success' => true, 'message' => 'Settings updated successfully');
        } else {
            return array('success' => false, 'message' => 'Failed to update settings');
        }
    }
    
    /**
     * Get system reports
     */
    public function getSystemReports($period = 'month') {
        $reports = array();
        
        // User growth
        $reports['user_growth'] = $this->getUserGrowthReport($period);
        
        // Habit statistics
        $reports['habit_stats'] = $this->getHabitStatistics($period);
        
        // Activity statistics
        $reports['activity_stats'] = $this->getActivityStatistics($period);
        
        // Notification statistics
        $reports['notification_stats'] = $this->getNotificationStatistics($period);
        
        return $reports;
    }
    
    /**
     * Get user growth report
     */
    private function getUserGrowthReport($period) {
        $interval = $period === 'month' ? 'MONTH' : 'WEEK';
        
        $sql = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m-%d') as date,
                COUNT(*) as new_users,
                SUM(COUNT(*)) OVER (ORDER BY DATE_FORMAT(created_at, '%Y-%m-%d')) as total_users
            FROM users 
            WHERE is_admin = 0 
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 $interval)
            GROUP BY DATE(created_at)
            ORDER BY date
        ";
        
        $result = $this->conn->query($sql);
        
        $data = array();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    /**
     * Get habit statistics
     */
    private function getHabitStatistics($period) {
        $interval = $period === 'month' ? 'MONTH' : 'WEEK';
        
        $sql = "
            SELECT 
                h.category,
                COUNT(*) as total_habits,
                ROUND(AVG(completion_rate), 2) as avg_completion_rate
            FROM habits h
            LEFT JOIN (
                SELECT 
                    habit_id,
                    (COUNT(*) / (DATEDIFF(CURDATE(), DATE(MIN(created_at))) + 1)) * 100 as completion_rate
                FROM habit_completions 
                WHERE completion_date >= DATE_SUB(CURDATE(), INTERVAL 1 $interval)
                GROUP BY habit_id
            ) hc ON h.id = hc.habit_id
            WHERE h.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 $interval)
            GROUP BY h.category
            ORDER BY total_habits DESC
        ";
        
        $result = $this->conn->query($sql);
        
        $data = array();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    /**
     * Get activity statistics
     */
    private function getActivityStatistics($period) {
        $interval = $period === 'month' ? 'MONTH' : 'WEEK';
        
        $sql = "
            SELECT 
                DATE(completion_date) as date,
                COUNT(DISTINCT user_id) as active_users,
                COUNT(*) as total_completions
            FROM habit_completions hc
            JOIN habits h ON hc.habit_id = h.id
            WHERE hc.completion_date >= DATE_SUB(CURDATE(), INTERVAL 1 $interval)
            GROUP BY DATE(completion_date)
            ORDER BY date
        ";
        
        $result = $this->conn->query($sql);
        
        $data = array();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    /**
     * Get notification statistics
     */
    private function getNotificationStatistics($period) {
        $interval = $period === 'month' ? 'MONTH' : 'WEEK';
        
        $sql = "
            SELECT 
                type,
                status,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER (), 2) as percentage
            FROM notifications 
            WHERE sent_at >= DATE_SUB(CURDATE(), INTERVAL 1 $interval)
            GROUP BY type, status
            ORDER BY type, status
        ";
        
        $result = $this->conn->query($sql);
        
        $data = array();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
}
?>