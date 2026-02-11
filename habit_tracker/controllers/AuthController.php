<?php
class AuthController {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function register($name, $email, $phone, $password, $confirm_password) {
        // Validation
        if (empty($name) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }
        
        if ($password !== $confirm_password) {
            return ['success' => false, 'message' => 'Passwords do not match'];
        }
        
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }
        
        // Check if email exists
        $check_sql = "SELECT id FROM users WHERE email = ?";
        $check_stmt = $this->conn->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $verification_code = md5(uniqid(rand(), true));
        
        // Insert user
        $sql = "INSERT INTO users (name, email, phone, password, verification_code, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sssss", $name, $email, $phone, $hashed_password, $verification_code);
        
        if ($stmt->execute()) {
            return [
                'success' => true, 
                'message' => 'Registration successful! Please check your email for verification.',
                'verification_code' => $verification_code
            ];
        } else {
            return ['success' => false, 'message' => 'Registration failed: ' . $stmt->error];
        }
    }
    
    public function login($email, $password) {
        // Get user
        $sql = "SELECT * FROM users WHERE email = ? AND is_active = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        $user = $result->fetch_assoc();
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        // Check email verification
        if (!$user['email_verified']) {
            return ['success' => false, 'message' => 'Please verify your email first'];
        }
        
        // Update last login
        $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $update_stmt = $this->conn->prepare($update_sql);
        $update_stmt->bind_param("i", $user['id']);
        $update_stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'is_admin' => $user['is_admin'],
                'dark_mode' => $user['dark_mode'],
                'profile_picture' => $user['profile_picture']
            ]
        ];
    }
    
    public function forgotPassword($email) {
        // Check if email exists
        $sql = "SELECT id, name FROM users WHERE email = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Email not found'];
        }
        
        $user = $result->fetch_assoc();
        
        // Generate OTP
        $otp = rand(100000, 999999);
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // Store OTP in database
        $otp_sql = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
        $otp_stmt = $this->conn->prepare($otp_sql);
        $otp_stmt->bind_param("sss", $email, $otp, $expires_at);
        $otp_stmt->execute();
        
        return [
            'success' => true,
            'message' => 'OTP sent to your email',
            'otp' => $otp,
            'user' => $user
        ];
    }
    
    public function resetPassword($email, $otp, $new_password) {
        // Verify OTP
        $sql = "SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $email, $otp);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Invalid or expired OTP'];
        }
        
        // Hash new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password
        $update_sql = "UPDATE users SET password = ? WHERE email = ?";
        $update_stmt = $this->conn->prepare($update_sql);
        $update_stmt->bind_param("ss", $hashed_password, $email);
        
        if ($update_stmt->execute()) {
            // Delete used OTP
            $delete_sql = "DELETE FROM password_resets WHERE email = ?";
            $delete_stmt = $this->conn->prepare($delete_sql);
            $delete_stmt->bind_param("s", $email);
            $delete_stmt->execute();
            
            return ['success' => true, 'message' => 'Password reset successful'];
        } else {
            return ['success' => false, 'message' => 'Password reset failed'];
        }
    }


    public function verifyEmail($code) {
    $sql = "UPDATE users SET email_verified = 1 WHERE verification_code = ? AND email_verified = 0";
    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param("s", $code);
    $stmt->execute();

    if($stmt->affected_rows > 0){
        return true; // success
    } else {
        return false; // invalid or already verified
    }
}

}
?>