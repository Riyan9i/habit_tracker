<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';

class Mailer {
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->configure();
    }
    
    private function configure() {
        $this->mail->isSMTP();
        $this->mail->Host = 'smtp.gmail.com';
        $this->mail->SMTPAuth = true;
        $this->mail->Username = 'jahangiruji2019@gmail.com'; // Sender Gmail
        $this->mail->Password = 'fsuy nblv byqp zgue';       // Gmail App Password
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = 587;
        $this->mail->setFrom('jahangiruji2019@gmail.com', 'Habit Tracker');
    }
    
    public function sendVerificationEmail($to, $name, $verificationCode) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to, $name);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Email Verification - Habit Tracker';
            
            $verificationLink = "http://" . $_SERVER['HTTP_HOST'] . "/habit_tracker/verify.php?code=$verificationCode";

            
            $body = "
                <h2>Welcome to Habit Tracker!</h2>
                <p>Hello $name,</p>
                <p>Thank you for registering. Please verify your email address by clicking the link below:</p>
                <p><a href='$verificationLink' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Verify Email</a></p>
                <p>Or copy this link: $verificationLink</p>
                <p>This link will expire in 24 hours.</p>
                <br>
                <p>Best regards,<br>Habit Tracker Team</p>
            ";
            
            $this->mail->Body = $body;
            $this->mail->AltBody = "Please verify your email: $verificationLink";
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    public function sendOTP($to, $name, $otp) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to, $name);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Password Reset OTP - Habit Tracker';
            
            $body = "
                <h2>Password Reset Request</h2>
                <p>Hello $name,</p>
                <p>You have requested to reset your password. Use the OTP below:</p>
                <h1 style='color: #4CAF50; font-size: 36px; text-align: center;'>$otp</h1>
                <p>This OTP is valid for 10 minutes.</p>
                <p>If you didn't request this, please ignore this email.</p>
                <br>
                <p>Best regards,<br>Habit Tracker Team</p>
            ";
            
            $this->mail->Body = $body;
            $this->mail->AltBody = "Your OTP for password reset: $otp";
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
}
?>
