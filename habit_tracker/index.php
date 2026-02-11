<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

// Redirect if logged in
if ($isLoggedIn) {
    if ($isAdmin) {
        header('Location: views/admin/dashboard.php');
    } else {
        header('Location: views/user/dashboard.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Habit Tracker - Build Better Habits</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0;
        }
        .feature-card {
            transition: transform 0.3s;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .feature-card:hover {
            transform: translateY(-10px);
        }
        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            color: #4CAF50;
        }
        .cta-button {
            padding: 15px 40px;
            font-size: 1.2rem;
            border-radius: 50px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background-color: rgba(0,0,0,0.8);">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-chart-line me-2"></i>HabitTracker
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link active" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                    <li class="nav-item">
                        <a href="views/auth/login.php" class="btn btn-outline-light ms-2">Login</a>
                    </li>
                    <li class="nav-item">
                        <a href="views/auth/register.php" class="btn btn-success ms-2">Sign Up Free</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section" id="home">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Build Better Habits, Transform Your Life</h1>
                    <p class="lead mb-4">Track your habits, monitor calories, and achieve your goals with our comprehensive habit tracking system.</p>
                    <div class="d-flex gap-3">
                        <a href="views/auth/register.php" class="btn btn-light btn-lg cta-button">
                            <i class="fas fa-rocket me-2"></i>Start Free Trial
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg cta-button">
                            <i class="fas fa-play-circle me-2"></i>Learn More
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <img src="https://cdn.pixabay.com/photo/2017/10/10/21/47/laptop-2838921_1280.jpg" 
                         class="img-fluid rounded shadow-lg" alt="Habit Tracker Dashboard">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">Powerful Features</h2>
                <p class="lead text-muted">Everything you need to build and maintain healthy habits</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-tasks fa-3x text-primary"></i>
                            </div>
                            <h4 class="card-title">Habit Management</h4>
                            <p class="card-text">Create, track, and manage daily or weekly habits with reminders and progress tracking.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-fire fa-3x text-danger"></i>
                            </div>
                            <h4 class="card-title">Calorie Tracker</h4>
                            <p class="card-text">Monitor your calorie intake and expenditure with detailed analytics and charts.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-bell fa-3x text-warning"></i>
                            </div>
                            <h4 class="card-title">Smart Notifications</h4>
                            <p class="card-text">Get email and SMS reminders for habits, progress reports, and motivational alerts.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-5 bg-white">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="stat-number">95%</div>
                    <p class="text-muted">Success Rate</p>
                </div>
                <div class="col-md-3">
                    <div class="stat-number">10K+</div>
                    <p class="text-muted">Active Users</p>
                </div>
                <div class="col-md-3">
                    <div class="stat-number">50K+</div>
                    <p class="text-muted">Habits Tracked</p>
                </div>
                <div class="col-md-3">
                    <div class="stat-number">24/7</div>
                    <p class="text-muted">Support</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5 bg-primary text-white">
        <div class="container text-center">
            <h2 class="display-5 fw-bold mb-4">Ready to Transform Your Habits?</h2>
            <p class="lead mb-4">Join thousands of users who have improved their lives with HabitTracker</p>
            <a href="views/auth/register.php" class="btn btn-light btn-lg cta-button">
                <i class="fas fa-user-plus me-2"></i>Sign Up Now - It's Free!
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><i class="fas fa-chart-line me-2"></i>HabitTracker</h5>
                    <p>Building better habits, one day at a time.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#home" class="text-white-50 text-decoration-none">Home</a></li>
                        <li><a href="views/auth/login.php" class="text-white-50 text-decoration-none">Login</a></li>
                        <li><a href="views/auth/register.php" class="text-white-50 text-decoration-none">Register</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact</h5>
                    <p><i class="fas fa-envelope me-2"></i>support@habittracker.com</p>
                    <p><i class="fas fa-phone me-2"></i>+880 1234 567890</p>
                </div>
            </div>
            <hr class="bg-white">
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> HabitTracker. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>