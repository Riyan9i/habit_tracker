<?php
session_start();
require_once '../../includes/auth-check.php';
require_once '../../config/database.php';
require_once '../../models/User.php';

$user_id = $_SESSION['user_id'];
$user_model = new User($conn);

// Get user data
$user_data = $user_model->getUserById($user_id);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $result = $user_model->updateProfile($user_id, $_POST, $_FILES);
        
        if ($result['success']) {
            // Update session data
            $_SESSION['user_name'] = $result['user']['name'];
            $_SESSION['user_email'] = $result['user']['email'];
            $_SESSION['profile_picture'] = $result['user']['profile_picture'];
            $_SESSION['weight_goal'] = $result['user']['weight_goal'];
            
            $_SESSION['message'] = $result['message'];
            header('Location: profile.php');
            exit();
        } else {
            $error = $result['message'];
        }
    } elseif (isset($_POST['change_password'])) {
        $result = $user_model->changePassword($user_id, $_POST);
        
        if ($result['success']) {
            $_SESSION['message'] = $result['message'];
            header('Location: profile.php');
            exit();
        } else {
            $error = $result['message'];
        }
    } elseif (isset($_POST['update_notifications'])) {
        $result = $user_model->updateNotificationPref($user_id, $_POST);
        
        if ($result['success']) {
            $_SESSION['message'] = $result['message'];
            header('Location: profile.php');
            exit();
        } else {
            $error = $result['message'];
        }
    }
}

// Calculate BMI
$bmi = null;
if ($user_data['height'] && $user_data['weight']) {
    $height_m = $user_data['height'] / 100; // Convert cm to m
    $bmi = $user_data['weight'] / ($height_m * $height_m);
}
?>
<!DOCTYPE html>
<html lang="en" <?php echo $_SESSION['dark_mode'] ? 'data-bs-theme="dark"' : ''; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Habit Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 40px;
            margin-bottom: 30px;
            position: relative;
        }
        
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 5px solid white;
            object-fit: cover;
            margin-bottom: 20px;
        }
        
        .profile-stats {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        [data-bs-theme="dark"] .profile-stats {
            background: #2d2d2d;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #4CAF50;
            display: block;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .profile-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 500;
            padding: 12px 25px;
            border-radius: 0;
        }
        
        .profile-tabs .nav-link.active {
            color: #4CAF50;
            border-bottom-color: #4CAF50;
            background: transparent;
        }
        
        .form-profile {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        [data-bs-theme="dark"] .form-profile {
            background: #2d2d2d;
        }
        
        .bmi-meter {
            height: 100%;
            border-radius: 10px;
            background: linear-gradient(90deg, #4CAF50, #FFC107, #F44336);
            position: relative;
        }
        
        .bmi-indicator {
            position: absolute;
            top: -10px;
            width: 2px;
            height: 40px;
            background: #000;
            transform: translateX(-50%);
        }
        
        .goal-card {
            border-left: 4px solid #4CAF50;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            background: #f8f9fa;
        }
        
        [data-bs-theme="dark"] .goal-card {
            background: #2d2d2d;
        }
        
        .notification-item {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-left: 4px solid transparent;
        }
        
        [data-bs-theme="dark"] .notification-item {
            background: #2d2d2d;
        }
        
        .notification-item.email { border-left-color: #4CAF50; }
        .notification-item.sms { border-left-color: #2196F3; }

             /* === SAME STYLE AS DASHBOARD === */
        :root {
            --primary-color: #4CAF50;
            --secondary-color: #2196F3;
            --danger-color: #f44336;
            --warning-color: #ff9800;
            --dark-bg: #1a1a1a;
            --dark-card: #2d2d2d;
        }

        body[data-bs-theme="dark"] {
            background-color: var(--dark-bg);
            color: #e0e0e0;
        }

        body[data-bs-theme="dark"] .card {
            background-color: var(--dark-card);
            border-color: #404040;
        }

        .sidebar {
            background: linear-gradient(180deg, var(--primary-color) 0%, #45a049 100%);
            min-height: 100vh;
            color: white;
            transition: all 0.3s;
        }

        .sidebar-sticky {
            position: sticky;
            top: 0;
            height: 100vh;
            padding-top: 20px;
            overflow-y: auto;
        }

        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 10px;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }

        .main-content {
            padding: 20px;
            background-color: #f8f9fa;
        }

        body[data-bs-theme="dark"] .main-content {
            background-color: var(--dark-bg);
        }

        .stat-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            overflow: hidden;
        }

        body[data-bs-theme="dark"] .stat-card {
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .streak-fire {
            color: #ff6b35;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .habit-item {
            border-left: 4px solid var(--primary-color);
            padding-left: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .habit-item.completed {
            border-left-color: #4CAF50;
            opacity: 0.7;
        }

        .habit-item:hover {
            transform: translateX(5px);
        }

        .progress-circle {
            width: 80px;
            height: 80px;
            position: relative;
        }

        .progress-circle svg {
            transform: rotate(-90deg);
        }

        .progress-circle-bg {
            fill: none;
            stroke: #e0e0e0;
            stroke-width: 8;
        }

        .progress-circle-fill {
            fill: none;
            stroke: var(--primary-color);
            stroke-width: 8;
            stroke-linecap: round;
        }

        .quick-actions .btn {
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .quick-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .navbar-custom {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 0;
        }

        body[data-bs-theme="dark"] .navbar-custom {
            background-color: var(--dark-card);
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center">
                            <img src="../../assets/images/uploads/<?php echo $user_data['profile_picture'] ?? 'default.png'; ?>" 
                                 class="profile-avatar" alt="Profile Picture" id="profileImagePreview">
                            <div class="mt-2">
                                <label for="profileImageUpload" class="btn btn-light btn-sm">
                                    <i class="fas fa-camera me-2"></i>Change Photo
                                </label>
                                <input type="file" id="profileImageUpload" accept="image/*" style="display: none;">
                            </div>
                        </div>
                        <div class="col-md-9">
                            <h1 class="display-5 fw-bold"><?php echo htmlspecialchars($user_data['name']); ?></h1>
                            <p class="lead mb-4">
                                <i class="fas fa-envelope me-2"></i><?php echo $user_data['email']; ?>
                                <?php if ($user_data['phone']): ?>
                                    <span class="ms-3"><i class="fas fa-phone me-2"></i><?php echo $user_data['phone']; ?></span>
                                <?php endif; ?>
                            </p>
                            <div class="row">
                                <div class="col-md-4">
                                    <h5><i class="fas fa-calendar-alt me-2"></i>Member Since</h5>
                                    <p><?php echo date('F d, Y', strtotime($user_data['created_at'])); ?></p>
                                </div>
                                <div class="col-md-4">
                                    <h5><i class="fas fa-bullseye me-2"></i>Weight Goal</h5>
                                    <p>
                                        <span class="badge bg-<?php 
                                            echo $user_data['weight_goal'] == 'Loss' ? 'danger' : 
                                                  ($user_data['weight_goal'] == 'Gain' ? 'warning' : 'success'); 
                                        ?>">
                                            <?php echo $user_data['weight_goal']; ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <h5><i class="fas fa-user-tag me-2"></i>Account Type</h5>
                                    <p>
                                        <span class="badge bg-primary">
                                            <?php echo $user_data['is_admin'] ? 'Admin' : 'Standard User'; ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Stats Cards -->
                <div class="profile-stats">
                    <div class="row">
                        <div class="col-md-3 col-6">
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $user_model->getTotalHabits($user_id); ?></span>
                                <span class="stat-label">Total Habits</span>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $user_model->getCurrentStreak($user_id); ?></span>
                                <span class="stat-label">Current Streak</span>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $user_model->getCompletionRate($user_id); ?>%</span>
                                <span class="stat-label">Success Rate</span>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-item">
                                <?php if ($bmi): ?>
                                    <span class="stat-value"><?php echo number_format($bmi, 1); ?></span>
                                    <span class="stat-label">BMI Score</span>
                                <?php else: ?>
                                    <span class="stat-value">--</span>
                                    <span class="stat-label">BMI (Update Profile)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs profile-tabs mb-4" id="profileTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#personal">
                            <i class="fas fa-user me-2"></i>Personal Info
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#health">
                            <i class="fas fa-heartbeat me-2"></i>Health Data
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#notifications">
                            <i class="fas fa-bell me-2"></i>Notifications
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#password">
                            <i class="fas fa-lock me-2"></i>Password
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>Danger Zone
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content" id="profileTabContent">
                    <!-- Personal Info Tab -->
                    <div class="tab-pane fade show active" id="personal">
                        <div class="form-profile">
                            <h4 class="mb-4"><i class="fas fa-user-edit me-2"></i>Edit Personal Information</h4>
                            <form method="POST" action="" enctype="multipart/form-data">
                                <input type="hidden" name="update_profile" value="1">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" name="name" 
                                               value="<?php echo htmlspecialchars($user_data['name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" name="email" 
                                               value="<?php echo $user_data['email']; ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone" 
                                               value="<?php echo $user_data['phone']; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Gender</label>
                                        <select class="form-select" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?php echo $user_data['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo $user_data['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo $user_data['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" name="dob" 
                                               value="<?php echo $user_data['dob'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Profile Picture</label>
                                        <input type="file" class="form-control" name="profile_picture" accept="image/*">
                                        <small class="text-muted">Max size: 2MB | Formats: JPG, PNG, GIF</small>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Bio / About Me</label>
                                        <textarea class="form-control" name="bio" rows="3" 
                                                  placeholder="Tell us about yourself..."><?php echo $user_data['bio'] ?? ''; ?></textarea>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Health Data Tab -->
                    <div class="tab-pane fade" id="health">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-profile">
                                    <h4 class="mb-4"><i class="fas fa-heartbeat me-2"></i>Health & Fitness Data</h4>
                                    <form method="POST" action="">
                                        <input type="hidden" name="update_profile" value="1">
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Age</label>
                                                <input type="number" class="form-control" name="age" 
                                                       value="<?php echo $user_data['age'] ?? ''; ?>" min="1" max="120">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Weight (kg)</label>
                                                <input type="number" step="0.1" class="form-control" name="weight" 
                                                       value="<?php echo $user_data['weight'] ?? ''; ?>" min="1" max="500">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Height (cm)</label>
                                                <input type="number" class="form-control" name="height" 
                                                       value="<?php echo $user_data['height'] ?? ''; ?>" min="50" max="300">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Weight Goal</label>
                                                <select class="form-select" name="weight_goal">
                                                    <option value="Maintain" <?php echo $user_data['weight_goal'] == 'Maintain' ? 'selected' : ''; ?>>Maintain Weight</option>
                                                    <option value="Loss" <?php echo $user_data['weight_goal'] == 'Loss' ? 'selected' : ''; ?>>Lose Weight</option>
                                                    <option value="Gain" <?php echo $user_data['weight_goal'] == 'Gain' ? 'selected' : ''; ?>>Gain Weight</option>
                                                </select>
                                            </div>
                                            <div class="col-md-12 mb-3">
                                                <label class="form-label">Target Weight (kg)</label>
                                                <input type="number" step="0.1" class="form-control" name="target_weight" 
                                                       value="<?php echo $user_data['target_weight'] ?? ''; ?>">
                                            </div>
                                            <div class="col-md-12 mb-3">
                                                <label class="form-label">Weekly Activity Level</label>
                                                <select class="form-select" name="activity_level">
                                                    <option value="sedentary" <?php echo ($user_data['activity_level'] ?? '') == 'sedentary' ? 'selected' : ''; ?>>Sedentary (little or no exercise)</option>
                                                    <option value="light" <?php echo ($user_data['activity_level'] ?? '') == 'light' ? 'selected' : ''; ?>>Lightly active (1-3 days/week)</option>
                                                    <option value="moderate" <?php echo ($user_data['activity_level'] ?? '') == 'moderate' ? 'selected' : ''; ?>>Moderately active (3-5 days/week)</option>
                                                    <option value="very" <?php echo ($user_data['activity_level'] ?? '') == 'very' ? 'selected' : ''; ?>>Very active (6-7 days/week)</option>
                                                    <option value="extra" <?php echo ($user_data['activity_level'] ?? '') == 'extra' ? 'selected' : ''; ?>>Extra active (very hard exercise/sports)</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-heartbeat me-2"></i>Update Health Data
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="col-lg-6">
                                <!-- BMI Calculator -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>BMI Calculator</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($bmi): ?>
                                            <div class="text-center mb-4">
                                                <h1 class="display-3 fw-bold"><?php echo number_format($bmi, 1); ?></h1>
                                                <h5 class="mb-3">
                                                    <?php
                                                    if ($bmi < 18.5) {
                                                        echo '<span class="badge bg-warning">Underweight</span>';
                                                    } elseif ($bmi < 25) {
                                                        echo '<span class="badge bg-success">Normal</span>';
                                                    } elseif ($bmi < 30) {
                                                        echo '<span class="badge bg-warning">Overweight</span>';
                                                    } else {
                                                        echo '<span class="badge bg-danger">Obese</span>';
                                                    }
                                                    ?>
                                                </h5>
                                                <p class="text-muted">Body Mass Index</p>
                                            </div>
                                            
                                            <div class="mb-4">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <small>Underweight</small>
                                                    <small>Normal</small>
                                                    <small>Overweight</small>
                                                    <small>Obese</small>
                                                </div>
                                                <div class="bmi-meter" style="height: 20px; position: relative;">
                                                    <div class="bmi-indicator" style="left: <?php echo min(100, max(0, ($bmi - 15) * (100/30))); ?>%;"></div>
                                                </div>
                                                <div class="d-flex justify-content-between mt-1">
                                                    <small>15</small>
                                                    <small>18.5</small>
                                                    <small>25</small>
                                                    <small>30</small>
                                                    <small>40+</small>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-weight fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">Enter your height and weight to calculate BMI</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Daily Calorie Goal -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-fire me-2"></i>Daily Calorie Goal</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        require_once '../../models/Calorie.php';
                                        $calorie_model = new Calorie($conn);
                                        $daily_goal = $calorie_model->calculateDailyCalorieGoal($user_id, $user_data['weight_goal']);
                                        ?>
                                        <div class="text-center">
                                            <h1 class="display-4 fw-bold text-primary"><?php echo $daily_goal; ?></h1>
                                            <p class="text-muted">Calories per day</p>
                                            <div class="mt-3">
                                                <p class="small">
                                                    Based on your goal to <strong><?php echo strtolower($user_data['weight_goal']); ?> weight</strong>
                                                    with <strong><?php echo $user_data['activity_level'] ?? 'moderate'; ?> activity</strong> level.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notifications Tab -->
                    <div class="tab-pane fade" id="notifications">
                        <div class="form-profile">
                            <h4 class="mb-4"><i class="fas fa-bell me-2"></i>Notification Preferences</h4>
                            <form method="POST" action="">
                                <input type="hidden" name="update_notifications" value="1">
                                
                                <div class="mb-4">
                                    <h5 class="mb-3">Notification Channels</h5>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <div class="notification-item email">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="email_notifications" 
                                                           value="1" <?php echo $user_data['notification_pref'] == 'Email' || $user_data['notification_pref'] == 'Both' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label">
                                                        <i class="fas fa-envelope me-2"></i>Email Notifications
                                                    </label>
                                                </div>
                                                <small class="text-muted">Habit reminders, progress reports</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="notification-item sms">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="sms_notifications" 
                                                           value="1" <?php echo $user_data['notification_pref'] == 'SMS' || $user_data['notification_pref'] == 'Both' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label">
                                                        <i class="fas fa-sms me-2"></i>SMS Notifications
                                                    </label>
                                                </div>
                                                <small class="text-muted">Important alerts, OTP verification</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="notification-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="push_notifications" value="1" checked>
                                                    <label class="form-check-label">
                                                        <i class="fas fa-mobile-alt me-2"></i>Push Notifications
                                                    </label>
                                                </div>
                                                <small class="text-muted">In-app notifications</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h5 class="mb-3">Notification Types</h5>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="habit_reminders" value="1" checked>
                                                <label class="form-check-label">
                                                    Habit Reminders
                                                </label>
                                                <small class="text-muted d-block">Get reminded about your daily habits</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="progress_reports" value="1" checked>
                                                <label class="form-check-label">
                                                    Progress Reports
                                                </label>
                                                <small class="text-muted d-block">Weekly and monthly progress summaries</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="motivational_messages" value="1" checked>
                                                <label class="form-check-label">
                                                    Motivational Messages
                                                </label>
                                                <small class="text-muted d-block">Encouraging messages to keep you going</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="system_updates" value="1" checked>
                                                <label class="form-check-label">
                                                    System Updates
                                                </label>
                                                <small class="text-muted d-block">Important announcements about the app</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h5 class="mb-3">Reminder Schedule</h5>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Daily Digest Time</label>
                                            <input type="time" class="form-control" name="digest_time" value="20:00">
                                            <small class="text-muted">Time to receive daily summary</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Weekly Report Day</label>
                                            <select class="form-select" name="weekly_report_day">
                                                <option value="Monday">Monday</option>
                                                <option value="Tuesday">Tuesday</option>
                                                <option value="Wednesday">Wednesday</option>
                                                <option value="Thursday">Thursday</option>
                                                <option value="Friday" selected>Friday</option>
                                                <option value="Saturday">Saturday</option>
                                                <option value="Sunday">Sunday</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-bell me-2"></i>Update Preferences
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Password Tab -->
                    <div class="tab-pane fade" id="password">
                        <div class="form-profile">
                            <h4 class="mb-4"><i class="fas fa-lock me-2"></i>Change Password</h4>
                            <form method="POST" action="">
                                <input type="hidden" name="change_password" value="1">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Current Password *</label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">New Password *</label>
                                        <input type="password" class="form-control" name="new_password" id="newPassword" required>
                                        <div class="password-strength mt-2" id="passwordStrength"></div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Confirm New Password *</label>
                                        <input type="password" class="form-control" name="confirm_password" required>
                                        <div class="text-danger small mt-1" id="passwordMatch"></div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Password must be at least 8 characters long and contain at least one number and one special character.
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Danger Zone Tab -->
                    <div class="tab-pane fade" id="danger">
                        <div class="alert alert-danger">
                            <h5><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</h5>
                            <p class="mb-0">These actions are irreversible. Please proceed with caution.</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-danger">
                                    <div class="card-body">
                                        <h5 class="card-title text-danger">
                                            <i class="fas fa-file-export me-2"></i>Export Data
                                        </h5>
                                        <p class="card-text">Download all your data including habits, calorie logs, and progress history.</p>
                                        <button class="btn btn-outline-danger">
                                            <i class="fas fa-download me-2"></i>Export All Data
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card border-danger">
                                    <div class="card-body">
                                        <h5 class="card-title text-danger">
                                            <i class="fas fa-trash-alt me-2"></i>Delete Account
                                        </h5>
                                        <p class="card-text">Permanently delete your account and all associated data.</p>
                                        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                            <i class="fas fa-trash me-2"></i>Delete Account
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card border-warning mt-4">
                            <div class="card-body">
                                <h5 class="card-title text-warning">
                                    <i class="fas fa-ban me-2"></i>Deactivate Account
                                </h5>
                                <p class="card-text">Temporarily deactivate your account. You can reactivate it later by logging in.</p>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="confirmDeactivate">
                                    <label class="form-check-label" for="confirmDeactivate">
                                        I understand that my account will be temporarily disabled
                                    </label>
                                </div>
                                <button class="btn btn-warning" id="deactivateBtn" disabled>
                                    <i class="fas fa-ban me-2"></i>Deactivate Account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Delete Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone!
                    </div>
                    <p>Are you absolutely sure you want to delete your account? This will:</p>
                    <ul>
                        <li>Permanently delete all your habits and progress data</li>
                        <li>Remove all calorie logs and activity history</li>
                        <li>Delete your profile information</li>
                        <li>Cancel any active subscriptions</li>
                    </ul>
                    <div class="mb-3">
                        <label class="form-label">Please type "DELETE" to confirm:</label>
                        <input type="text" class="form-control" id="deleteConfirmation">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteAccount" disabled>
                        <i class="fas fa-trash me-2"></i>Delete My Account
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Profile image preview
        document.getElementById('profileImageUpload').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profileImagePreview').src = e.target.result;
                }
                reader.readAsDataURL(file);
                
                // Submit form automatically
                const form = new FormData();
                form.append('profile_picture', file);
                form.append('update_profile', '1');
                
                fetch('update-profile.php', {
                    method: 'POST',
                    body: form
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        });
        
        // Password strength indicator
        const newPasswordInput = document.getElementById('newPassword');
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
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
        }
        
        // Confirm deactivate checkbox
        const confirmDeactivate = document.getElementById('confirmDeactivate');
        const deactivateBtn = document.getElementById('deactivateBtn');
        
        if (confirmDeactivate && deactivateBtn) {
            confirmDeactivate.addEventListener('change', function() {
                deactivateBtn.disabled = !this.checked;
            });
            
            deactivateBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to deactivate your account?')) {
                    fetch('deactivate-account.php', {
                        method: 'POST'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = '../auth/login.php';
                        }
                    });
                }
            });
        }
        
        // Delete account confirmation
        const deleteConfirmation = document.getElementById('deleteConfirmation');
        const confirmDeleteBtn = document.getElementById('confirmDeleteAccount');
        
        if (deleteConfirmation && confirmDeleteBtn) {
            deleteConfirmation.addEventListener('input', function() {
                confirmDeleteBtn.disabled = this.value !== 'DELETE';
            });
            
            confirmDeleteBtn.addEventListener('click', function() {
                if (confirm('This is your final warning! Are you absolutely sure?')) {
                    fetch('delete-account.php', {
                        method: 'POST'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = '../auth/login.php';
                        }
                    });
                }
            });
        }
        
        // Tab switching based on URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        if (tabParam) {
            const tab = new bootstrap.Tab(document.querySelector(`a[href="#${tabParam}"]`));
            tab.show();
        }
    </script>
    <?php include '../../includes/footer.php'; ?>
</body>
</html>