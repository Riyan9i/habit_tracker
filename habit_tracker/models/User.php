<?php
class User {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function getUserById($user_id) {
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    public function updateProfile($user_id, $data, $files = []) {
        $name = $this->conn->real_escape_string($data['name']);
        $email = $this->conn->real_escape_string($data['email']);
        $phone = isset($data['phone']) ? $this->conn->real_escape_string($data['phone']) : null;
        $gender = isset($data['gender']) ? $this->conn->real_escape_string($data['gender']) : null;
        $dob = isset($data['dob']) ? $this->conn->real_escape_string($data['dob']) : null;
        $bio = isset($data['bio']) ? $this->conn->real_escape_string($data['bio']) : null;
        $age = isset($data['age']) ? (int)$data['age'] : null;
        $weight = isset($data['weight']) ? (float)$data['weight'] : null;
        $height = isset($data['height']) ? (float)$data['height'] : null;
        $weight_goal = isset($data['weight_goal']) ? $this->conn->real_escape_string($data['weight_goal']) : 'Maintain';
        $activity_level = isset($data['activity_level']) ? $this->conn->real_escape_string($data['activity_level']) : null;
        $target_weight = isset($data['target_weight']) ? (float)$data['target_weight'] : null;
        
        // Handle profile picture upload
        $profile_picture = null;
        if (isset($files['profile_picture']) && $files['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../assets/images/uploads/';
            $file_name = time() . '_' . basename($files['profile_picture']['name']);
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($files['profile_picture']['tmp_name'], $file_path)) {
                $profile_picture = $file_name;
            }
        }
        
        // Build SQL query
        $sql = "UPDATE users SET 
                name = ?, 
                email = ?, 
                phone = ?, 
                gender = ?, 
                dob = ?, 
                bio = ?, 
                age = ?, 
                weight = ?, 
                height = ?, 
                weight_goal = ?, 
                activity_level = ?, 
                target_weight = ?, 
                updated_at = NOW()";
        
        $params = [$name, $email, $phone, $gender, $dob, $bio, $age, $weight, $height, $weight_goal, $activity_level, $target_weight];
        $param_types = "ssssssiddssd";
        
        if ($profile_picture) {
            $sql .= ", profile_picture = ?";
            $params[] = $profile_picture;
            $param_types .= "s";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $user_id;
        $param_types .= "i";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($param_types, ...$params);
        
        if ($stmt->execute()) {
            $updated_user = $this->getUserById($user_id);
            return [
                'success' => true,
                'message' => 'Profile updated successfully!',
                'user' => $updated_user
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to update profile: ' . $stmt->error];
        }
    }
    
    public function changePassword($user_id, $data) {
        $current_password = $data['current_password'];
        $new_password = $data['new_password'];
        $confirm_password = $data['confirm_password'];
        
        // Verify current password
        $user = $this->getUserById($user_id);
        if (!password_verify($current_password, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Validate new password
        if ($new_password !== $confirm_password) {
            return ['success' => false, 'message' => 'New passwords do not match'];
        }
        
        if (strlen($new_password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters long'];
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = ? WHERE id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Password changed successfully!'];
        } else {
            return ['success' => false, 'message' => 'Failed to change password: ' . $stmt->error];
        }
    }
    
    public function updateNotificationPref($user_id, $data) {
        $email_notifications = isset($data['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($data['sms_notifications']) ? 1 : 0;
        
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
        
        $sql = "UPDATE users SET notification_pref = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $notification_pref, $user_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Notification preferences updated!'];
        } else {
            return ['success' => false, 'message' => 'Failed to update preferences: ' . $stmt->error];
        }
    }
    
    public function getTotalHabits($user_id) {
        $sql = "SELECT COUNT(*) as count FROM habits WHERE user_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['count'] ?? 0;
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
    
    public function getCompletionRate($user_id) {
        $sql = "SELECT 
                (SELECT COUNT(*) FROM habit_completions hc 
                 JOIN habits h ON hc.habit_id = h.id 
                 WHERE h.user_id = ? AND hc.completion_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as completed,
                (SELECT COUNT(*) * 30 FROM habits WHERE user_id = ?) as total
                FROM dual";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['total'] > 0) {
            return round(($row['completed'] / $row['total']) * 100);
        }
        return 0;
    }
}
?>