<?php
class Notification {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Create a new notification
     */
    public function createNotification($user_id, $title, $message, $type = 'info', $related_id = null) {
        $sql = "INSERT INTO notifications (user_id, title, message, type, related_id) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        
        $stmt->bind_param("isssi", $user_id, $title, $message, $type, $related_id);
        
        if ($stmt->execute()) {
            return $this->conn->insert_id;
        }
        
        return false;
    }
    
    /**
     * Get user notifications
     */
    public function getUserNotifications($user_id, $limit = 10, $unread_only = false) {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        
        if ($unread_only) {
            $sql .= " AND is_read = 0";
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        if ($limit > 0) {
            $sql .= " LIMIT ?";
        }
        
        $stmt = $this->conn->prepare($sql);
        
        if ($limit > 0) {
            $stmt->bind_param("ii", $user_id, $limit);
        } else {
            $stmt->bind_param("i", $user_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = array();
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        return $notifications;
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id, $user_id) {
        // First check if notification belongs to user
        $check_sql = "SELECT id FROM notifications WHERE id = ? AND user_id = ?";
        $check_stmt = $this->conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $notification_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            return false;
        }
        
        $sql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $notification_id);
        
        return $stmt->execute();
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllAsRead($user_id) {
        $sql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        
        return $stmt->execute();
    }
    
    /**
     * Delete a notification
     */
    public function deleteNotification($notification_id, $user_id) {
        // First check if notification belongs to user
        $check_sql = "SELECT id FROM notifications WHERE id = ? AND user_id = ?";
        $check_stmt = $this->conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $notification_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            return false;
        }
        
        $sql = "DELETE FROM notifications WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $notification_id);
        
        return $stmt->execute();
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount($user_id) {
        $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['count'];
    }
    
    /**
     * Send email notification
     */
    public function sendEmailNotification($user_id, $subject, $body) {
        // Get user email
        $sql = "SELECT email, notification_pref FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user || !$user['email']) {
            return false;
        }
        
        // Check notification preference
        if (!in_array($user['notification_pref'], ['Both', 'Email'])) {
            return false;
        }
        
        // In a real application, you would use a mailing library here
        // For now, we'll just log it
        $this->logNotification('email', $user_id, $user['email'], $subject);
        
        // Store in database as sent email
        $sql = "INSERT INTO email_logs (user_id, email, subject, status) VALUES (?, ?, ?, 'sent')";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iss", $user_id, $user['email'], $subject);
        $stmt->execute();
        
        return true;
    }
    
    /**
     * Send SMS notification
     */
    public function sendSMSNotification($user_id, $message) {
        // Get user phone number
        $sql = "SELECT phone, notification_pref FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user || !$user['phone']) {
            return false;
        }
        
        // Check notification preference
        if (!in_array($user['notification_pref'], ['Both', 'SMS'])) {
            return false;
        }
        
        // In a real application, you would use an SMS API here
        // For now, we'll just log it
        $this->logNotification('sms', $user_id, $user['phone'], $message);
        
        // Store in database as sent SMS
        $sql = "INSERT INTO sms_logs (user_id, phone, message, status) VALUES (?, ?, ?, 'sent')";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iss", $user_id, $user['phone'], $message);
        $stmt->execute();
        
        return true;
    }
    
    /**
     * Get notification statistics
     */
    public function getNotificationStats($user_id) {
        $stats = array();
        
        // Total notifications
        $sql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stats['total'] = $row['total'];
        
        // Unread count
        $stats['unread'] = $this->getUnreadCount($user_id);
        
        // Notifications by type
        $sql = "SELECT type, COUNT(*) as count FROM notifications WHERE user_id = ? GROUP BY type";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stats['by_type'] = array();
        while ($row = $result->fetch_assoc()) {
            $stats['by_type'][$row['type']] = $row['count'];
        }
        
        return $stats;
    }
    
    /**
     * Clean up old notifications
     */
    public function cleanupOldNotifications($days_old = 30) {
        $sql = "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $days_old);
        
        return $stmt->execute();
    }
    
    /**
     * Create system-wide notification
     */
    public function createSystemNotification($title, $message, $type = 'system') {
        // Get all active users
        $sql = "SELECT id FROM users WHERE status = 'active'";
        $result = $this->conn->query($sql);
        
        $success_count = 0;
        while ($row = $result->fetch_assoc()) {
            if ($this->createNotification($row['id'], $title, $message, $type)) {
                $success_count++;
            }
        }
        
        return $success_count;
    }
    
    /**
     * Log notification for debugging
     */
    private function logNotification($method, $user_id, $recipient, $content) {
        $log_entry = date('Y-m-d H:i:s') . " - {$method} notification sent to user {$user_id} ({$recipient}): {$content}\n";
        file_put_contents('../logs/notifications.log', $log_entry, FILE_APPEND);
    }
}
?>