<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/mailer.php';
require_once '../../controllers/AuthController.php';

$auth = new AuthController($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $result = $auth->register($name, $email, $phone, $password, $confirm_password);
    
    if ($result['success']) {
    // Send verification email
    $mailer = new Mailer();
    $sent = $mailer->sendVerificationEmail($email, $name, $result['verification_code']);
    
    if($sent){
        $_SESSION['message'] = "Registration successful! Please check your email for verification.";
        header('Location: login.php');
        exit();
    } else {
        $error = "Registration done, but failed to send verification email. Check SMTP settings.";
    }
} else {
    $error = $result['message'];
}

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Habit Tracker</title>
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
        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .register-left {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .register-right {
            padding: 50px;
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
        .btn-register {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-weight: bold;
            width: 100%;
            transition: transform 0.3s;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            color: white;
        }
        .password-strength {
            height: 5px;
            border-radius: 2px;
            margin-top: 5px;
            transition: all 0.3s;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="register-card">
                    <div class="row g-0">
                        <!-- Left Side -->
                        <div class="col-lg-6 d-none d-lg-block">
                            <div class="register-left">
                                <h2 class="mb-4">Join Habit Tracker</h2>
                                <p class="mb-4">Start your journey to better habits and a healthier lifestyle.</p>
                                <div class="benefits mb-5">
                                    <h5>Benefits:</h5>
                                    <p><i class="fas fa-check-circle me-2"></i> Track unlimited habits</p>
                                    <p><i class="fas fa-check-circle me-2"></i> Personalized progress reports</p>
                                    <p><i class="fas fa-check-circle me-2"></i> Smart reminders</p>
                                    <p><i class="fas fa-check-circle me-2"></i> Calorie tracking</p>
                                    <p><i class="fas fa-check-circle me-2"></i> Community support</p>
                                </div>
                                <div class="quote">
                                    <p class="fst-italic">"Small daily improvements are the key to staggering long-term results."</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Side -->
                        <div class="col-lg-6">
                            <div class="register-right">
                                <div class="text-center mb-5">
                                    <h2 class="fw-bold">Create Account</h2>
                                    <p class="text-muted">Fill in your details to get started</p>
                                </div>
                                
                                <?php if (isset($error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?php echo $error; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" action="" id="registerForm">
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <label class="form-label">Full Name</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                <input type="text" class="form-control" name="name" placeholder="John Doe" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <label class="form-label">Phone Number</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                                <input type="tel" class="form-control" name="phone" placeholder="+880 1234 567890">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                            <input type="email" class="form-control" name="email" placeholder="you@example.com" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <label class="form-label">Password</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                <input type="password" class="form-control" name="password" id="password" placeholder="Create password" required>
                                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <div class="password-strength" id="passwordStrength"></div>
                                            <small class="form-text text-muted">Use 8+ characters with letters & numbers</small>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <label class="form-label">Confirm Password</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                <input type="password" class="form-control" name="confirm_password" placeholder="Confirm password" required>
                                            </div>
                                            <div class="text-danger small mt-1" id="passwordMatch"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4 form-check">
                                        <input type="checkbox" class="form-check-input" name="terms" required>
                                        <label class="form-check-label">
                                            I agree to the <a href="#" class="text-decoration-none">Terms & Conditions</a> and <a href="#" class="text-decoration-none">Privacy Policy</a>
                                        </label>
                                    </div>
                                    
                                    <div class="mb-4 form-check">
                                        <input type="checkbox" class="form-check-input" name="newsletter">
                                        <label class="form-check-label">
                                            Send me tips and updates via email
                                        </label>
                                    </div>
                                    
                                    <button type="submit" class="btn-register mb-4">
                                        <i class="fas fa-user-plus me-2"></i>Create Account
                                    </button>
                                    
                                    <div class="text-center">
                                        <p class="text-muted">Already have an account? <a href="login.php" class="text-decoration-none fw-bold">Sign In</a></p>
                                    </div>
                                </form>
                                
                                <hr class="my-4">
                                
                                <div class="text-center">
                                    <p class="text-muted mb-3">Or register with</p>
                                    <div class="d-flex justify-content-center gap-3">
                                        <button class="btn btn-outline-primary">
                                            <i class="fab fa-google"></i> Google
                                        </button>
                                        <button class="btn btn-outline-dark">
                                            <i class="fab fa-github"></i> GitHub
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
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
            strengthBar.style.backgroundColor = colors[strength - 1] || '#dc3545';
        });
        
        // Confirm password match
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
        const matchMessage = document.getElementById('passwordMatch');
        
        confirmPasswordInput.addEventListener('input', function() {
            if (this.value !== passwordInput.value) {
                matchMessage.textContent = 'Passwords do not match';
            } else {
                matchMessage.textContent = '';
            }
        });
        
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
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
    </script>
</body>
</html>