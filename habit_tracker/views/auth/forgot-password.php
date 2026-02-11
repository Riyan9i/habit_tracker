<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/mailer.php';
require_once '../../controllers/AuthController.php';

$auth = new AuthController($conn);
$message = '';
$error = '';
$step = isset($_POST['step']) ? $_POST['step'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        $email = $_POST['email'];
        $result = $auth->forgotPassword($email);
        
        if ($result['success']) {
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_otp'] = $result['otp'];
            
            // Send OTP via email
            $mailer = new Mailer();
            $mailer->sendOTP($email, $result['user']['name'], $result['otp']);
            
            $message = "OTP sent to your email!";
            $step = 2;
        } else {
            $error = $result['message'];
        }
    } elseif ($step == 2) {
        $email = $_SESSION['reset_email'];
        $otp = $_POST['otp'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error = "Passwords do not match!";
        } elseif ($otp != $_SESSION['reset_otp']) {
            $error = "Invalid OTP!";
        } else {
            $result = $auth->resetPassword($email, $otp, $new_password);
            
            if ($result['success']) {
                session_destroy();
                $_SESSION['message'] = "Password reset successful! Please login.";
                header('Location: login.php');
                exit();
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Habit Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        .forgot-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 500px;
            margin: 0 auto;
        }
        .forgot-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .forgot-body {
            padding: 40px;
        }
        .form-control {
            border-radius: 10px;
            padding: 15px;
            border: 2px solid #e0e0e0;
        }
        .form-control:focus {
            border-color: #4CAF50;
            box-shadow: none;
        }
        .btn-reset {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-weight: bold;
            width: 100%;
            transition: transform 0.3s;
        }
        .btn-reset:hover {
            transform: translateY(-2px);
            color: white;
        }
        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }
        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
        }
        .otp-input:focus {
            border-color: #4CAF50;
            outline: none;
        }
        .back-link {
            color: #4CAF50;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="forgot-card">
            <div class="forgot-header">
                <h3><i class="fas fa-key me-2"></i>Password Reset</h3>
                <p class="mb-0">Recover your account</p>
            </div>
            
            <div class="forgot-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($step == 1): ?>
                    <!-- Step 1: Email Input -->
                    <form method="POST" action="">
                        <input type="hidden" name="step" value="1">
                        <div class="mb-4">
                            <p class="text-muted mb-4">Enter your email address and we'll send you an OTP to reset your password.</p>
                            <label class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" name="email" placeholder="you@example.com" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-reset mb-4">
                            <i class="fas fa-paper-plane me-2"></i>Send OTP
                        </button>
                        
                        <div class="text-center">
                            <a href="login.php" class="back-link">
                                <i class="fas fa-arrow-left me-1"></i>Back to Login
                            </a>
                        </div>
                    </form>
                <?php elseif ($step == 2): ?>
                    <!-- Step 2: OTP Verification -->
                    <form method="POST" action="">
                        <input type="hidden" name="step" value="2">
                        <div class="mb-4">
                            <p class="text-muted mb-4">Enter the 6-digit OTP sent to <?php echo $_SESSION['reset_email']; ?> and your new password.</p>
                            
                            <div class="mb-4">
                                <label class="form-label">OTP Code</label>
                                <div class="otp-inputs">
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <input type="text" class="otp-input" name="otp[]" maxlength="1" 
                                               oninput="moveToNext(this, <?php echo $i; ?>)" 
                                               onkeyup="moveToNext(this, <?php echo $i; ?>)">
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" name="otp" id="fullOtp">
                                <div class="text-center mt-2">
                                    <small class="text-muted">Didn't receive OTP? <a href="#" onclick="resendOTP()" class="text-primary">Resend</a></small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" name="new_password" id="newPassword" placeholder="Enter new password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength mt-2" id="passwordStrength"></div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" name="confirm_password" placeholder="Confirm new password" required>
                                </div>
                                <div class="text-danger small mt-1" id="passwordMatch"></div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-reset mb-4">
                            <i class="fas fa-sync-alt me-2"></i>Reset Password
                        </button>
                        
                        <div class="text-center">
                            <a href="login.php" class="back-link">
                                <i class="fas fa-arrow-left me-1"></i>Back to Login
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // OTP Input handling
        function moveToNext(input, currentIndex) {
            if (input.value.length >= input.maxLength) {
                const nextInput = input.parentElement.querySelector(`input:nth-child(${currentIndex + 1})`);
                if (nextInput) {
                    nextInput.focus();
                }
            } else if (input.value.length === 0) {
                const prevInput = input.parentElement.querySelector(`input:nth-child(${currentIndex - 1})`);
                if (prevInput) {
                    prevInput.focus();
                }
            }
            
            // Update hidden input with full OTP
            const otpInputs = document.querySelectorAll('.otp-input');
            let fullOtp = '';
            otpInputs.forEach(otpInput => {
                fullOtp += otpInput.value;
            });
            document.getElementById('fullOtp').value = fullOtp;
        }
        
        // Toggle password visibility
        document.getElementById('toggleNewPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('newPassword');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Password strength indicator
        document.getElementById('newPassword').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            const colors = ['#dc3545', '#ffc107', '#17a2b8', '#28a745'];
            const width = strength * 25;
            
            strengthBar.style.width = width + '%';
            strengthBar.style.height = '5px';
            strengthBar.style.backgroundColor = colors[strength - 1] || '#dc3545';
            strengthBar.style.borderRadius = '2px';
            strengthBar.style.transition = 'all 0.3s';
        });
        
        // Resend OTP
        function resendOTP() {
            // Implement AJAX call to resend OTP
            alert('OTP resent! Check your email.');
        }
        
        // Auto-focus first OTP input
        document.addEventListener('DOMContentLoaded', function() {
            const firstOtpInput = document.querySelector('.otp-input');
            if (firstOtpInput) {
                firstOtpInput.focus();
            }
        });
    </script>
</body>
</html>