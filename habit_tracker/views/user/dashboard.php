<?php
session_start();
require_once '../../includes/auth-check.php';
require_once '../../config/database.php';
require_once '../../models/Habit.php';
require_once '../../models/Calorie.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$habit = new Habit($conn);
$calorie = new Calorie($conn);


// Get today's habits
$today_habits = $habit->getTodayHabits($user_id);
$completed_habits = $habit->getCompletedHabitsToday($user_id);
$completion_rate = count($today_habits) > 0 ? (count($completed_habits) / count($today_habits)) * 100 : 0;

// Get calorie data
$today_calories = $calorie->getTodayCalories($user_id);
$burned_calories = $calorie->getTodayBurnedCalories($user_id);

// Get streak
$streak = $habit->getCurrentStreak($user_id);

// Get weekly progress
$weekly_progress = $habit->getWeeklyProgress($user_id);


// Determine profile picture path
$profilePic = !empty($_SESSION['profile_picture']) 
    ? "../../assets/images/uploads/" . $_SESSION['profile_picture']
    : "../../assets/images/uploads/default.png";

// Fetch notifications (example: latest 5)
$notifications = $habit->getRecentActivity($user_id, 5); // or a dedicated notification function
$unreadCount = count($notifications);

?>
<!DOCTYPE html>
<html lang="en" <?php echo $_SESSION['dark_mode'] ? 'data-bs-theme="dark"' : ''; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Habit Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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
            stroke-dasharray: 226;
            stroke-dashoffset: calc(226 - (226 * <?php echo $completion_rate; ?>) / 100);
            transition: stroke-dashoffset 1s ease;
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
            <!-- Sidebar -->
            <div class="col-lg-2 col-md-3 sidebar d-none d-md-block">
                <div class="sidebar-sticky">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold"><i class="fas fa-chart-line me-2"></i>HabitTracker</h3>
                        <p class="small opacity-75">Build Better Habits</p>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="habits.php">
                                <i class="fas fa-tasks"></i> Habits
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="calorie-tracker.php">
                                <i class="fas fa-fire"></i> Calorie Tracker
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link btn btn-outline-light" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                    
                    <div class="mt-5 px-3">
                        <div class="progress-circle mx-auto mb-3">
                            <svg width="80" height="80" viewBox="0 0 100 100">
                                <circle class="progress-circle-bg" cx="50" cy="50" r="36"></circle>
                                <circle class="progress-circle-fill" cx="50" cy="50" r="36"></circle>
                            </svg>
                            <div class="position-absolute top-50 start-50 translate-middle text-center">
                                <span class="fw-bold"><?php echo round($completion_rate); ?>%</span>
                            </div>
                        </div>
                        <p class="text-center small opacity-75">Today's Progress</p>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-10 col-md-9 ms-auto">
                <!-- Top Navbar -->
   
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm py-3 px-4 rounded-3">
    <div class="container-fluid">
        <!-- Sidebar toggle for mobile -->
        <button class="btn btn-outline-primary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Brand -->
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="fas fa-chart-line me-2"></i>HabitTracker
        </a>

        <div class="d-flex align-items-center ms-auto">
            <!-- Notifications -->
            <div class="dropdown me-3">
                <button class="btn btn-outline-secondary position-relative dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-bell"></i>
                    <?php if($unreadCount > 0): ?>
                        <span class="notification-badge"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" style="width: 300px;">
                    <li class="dropdown-header fw-bold">Notifications</li>
                    <?php if($unreadCount > 0): ?>
                        <?php foreach($notifications as $notif): ?>
                            <li>
                                <a class="dropdown-item d-flex justify-content-between align-items-start" href="#">
                                    <div>
                                        <strong><?php echo htmlspecialchars($notif['habit_name']); ?></strong>
                                        <div class="small text-muted"><?php echo date('M d, h:i A', strtotime($notif['completed_at'])); ?></div>
                                    </div>
                                    <span class="badge bg-<?php echo strtolower($notif['status'])=='complete' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($notif['status']); ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><span class="dropdown-item text-muted">No notifications</span></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-center fw-bold" href="reports.php">View All</a></li>
                </ul>
            </div>

            <!-- Dark mode toggle -->
            <button class="btn btn-outline-secondary me-3" id="darkModeToggle">
                <i class="fas fa-moon"></i>
            </button>

            <!-- Profile -->
            <div class="dropdown">
                <button class="btn btn-link text-decoration-none d-flex align-items-center dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <img src="<?php echo $profilePic; ?>" class="profile-img me-2">
                    <div class="text-start">
                        <span class="fw-bold"><?php echo $_SESSION['user_name']; ?></span>
                        <br>
                        <small class="text-muted"><?php echo $_SESSION['user_email']; ?></small>
                    </div>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>





                
                <!-- Dashboard Content -->
                <div class="main-content rounded-3">
                    <!-- Welcome Banner -->
                    <div class="alert alert-primary alert-dismissible fade show mb-4" role="alert">
                        <h4 class="alert-heading">Welcome back, <?php echo $_SESSION['user_name']; ?>! 👋</h4>
                        <p>You have <?php echo count($today_habits); ?> habits to complete today. Keep up the good work!</p>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    
                    <!-- Stats Row -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card border-start border-primary border-4">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted fw-semibold">Today's Habits</h6>
                                            <h2 class="fw-bold"><?php echo count($completed_habits); ?>/<?php echo count($today_habits); ?></h2>
                                            <span class="badge bg-primary">Progress</span>
                                        </div>
                                        <div class="stat-icon bg-primary">
                                            <i class="fas fa-tasks"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card border-start border-success border-4">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted fw-semibold">Current Streak</h6>
                                            <h2 class="fw-bold"><?php echo $streak; ?> days</h2>
                                            <span class="badge bg-success">🔥 Keep going!</span>
                                        </div>
                                        <div class="stat-icon bg-success">
                                            <i class="fas fa-fire streak-fire"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card border-start border-info border-4">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted fw-semibold">Calories Today</h6>
                                            <h2 class="fw-bold"><?php echo $today_calories; ?> cal</h2>
                                            <span class="badge bg-info"><?php echo $burned_calories; ?> burned</span>
                                        </div>
                                        <div class="stat-icon bg-info">
                                            <i class="fas fa-fire"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card border-start border-warning border-4">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted fw-semibold">Weekly Progress</h6>
                                            <h2 class="fw-bold">
    <?php 
    echo isset($weekly_progress['completion_rate']) ? $weekly_progress['completion_rate'] : 0; 
    ?>%
</h2>
<span class="badge bg-warning">
    <?php 
    $completed = isset($weekly_progress['completed']) ? $weekly_progress['completed'] : 0;
    $total = isset($weekly_progress['total']) ? $weekly_progress['total'] : 0;
    echo "{$completed}/{$total} habits"; 
    ?>
</span>

                                        </div>
                                        <div class="stat-icon bg-warning">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Main Content Row -->
                    <div class="row">
                        <!-- Today's Habits -->
                        <div class="col-lg-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-calendar-day me-2"></i>Today's Habits</h5>
                                    <a href="habits.php" class="btn btn-sm btn-primary">View All</a>
                                </div>
                                <div class="card-body">
                                    <?php if (count($today_habits) > 0): ?>
                                        <?php foreach ($today_habits as $habit_item): ?>
                                            <div class="habit-item <?php echo $habit_item['completed'] ? 'completed' : ''; ?>">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($habit_item['name']); ?></h6>
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock me-1"></i><?php echo date('h:i A', strtotime($habit_item['reminder_time'])); ?>
                                                            <span class="ms-2 badge bg-secondary"><?php echo $habit_item['category']; ?></span>
                                                        </small>
                                                    </div>
                                                    <?php if (!$habit_item['completed']): ?>
                                                        <button class="btn btn-sm btn-success mark-complete" data-habit-id="<?php echo $habit_item['id']; ?>">
                                                            <i class="fas fa-check"></i> Mark Done
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Completed</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No habits scheduled for today</p>
                                            <a href="habits.php?action=add" class="btn btn-primary">Add Your First Habit</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Stats & Actions -->
                        <div class="col-lg-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3 quick-actions mb-4">
                                        <div class="col-6">
                                            <a href="habits.php?action=add" class="btn btn-primary w-100">
                                                <i class="fas fa-plus me-2"></i>Add Habit
                                            </a>
                                        </div>
                                        <div class="col-6">
                                            <a href="calorie-tracker.php?action=add-food" class="btn btn-success w-100">
                                                <i class="fas fa-utensils me-2"></i>Add Food
                                            </a>
                                        </div>
                                        <div class="col-6">
                                            <a href="calorie-tracker.php?action=add-activity" class="btn btn-info w-100">
                                                <i class="fas fa-running me-2"></i>Add Activity
                                            </a>
                                        </div>
                                        <div class="col-6">
                                            <a href="reports.php" class="btn btn-warning w-100">
                                                <i class="fas fa-chart-pie me-2"></i>View Reports
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <!-- Progress Chart -->
                                    <div class="mb-4">
                                        <h6 class="mb-3">Weekly Habit Completion</h6>
                                        <canvas id="weeklyChart" height="150"></canvas>
                                    </div>
                                    
                                    <!-- Calorie Summary -->
                                    <div>
                                        <h6 class="mb-3">Today's Calorie Balance</h6>
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="flex-grow-1">
                                                <div class="progress" style="height: 10px;">
                                                    <?php 
                                                    $total_calories = 2000; // Default goal
                                                    $percent = min(100, ($today_calories / $total_calories) * 100);
                                                    ?>
                                                    <div class="progress-bar bg-success" style="width: <?php echo $percent; ?>%"></div>
                                                </div>
                                            </div>
                                            <div class="ms-3">
                                                <span class="fw-bold"><?php echo $today_calories; ?>/<?php echo $total_calories; ?></span>
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?php 
                                            $balance = $today_calories - $burned_calories;
                                            if ($balance > 500) {
                                                echo "<span class='text-danger'><i class='fas fa-exclamation-triangle'></i> You're over by " . abs($balance) . " calories</span>";
                                            } elseif ($balance < -500) {
                                                echo "<span class='text-info'><i class='fas fa-check-circle'></i> You're under by " . abs($balance) . " calories</span>";
                                            } else {
                                                echo "<span class='text-success'><i class='fas fa-thumbs-up'></i> Good balance</span>";
                                            }
                                            ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activity & Calendar -->
                    <div class="row">
                        <div class="col-lg-8 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Habit</th>
                                                    <th>Status</th>
                                                    <th>Time</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $recent_activity = $habit->getRecentActivity($user_id, 5);
                                                foreach ($recent_activity as $activity):
                                                ?>
                                                    <tr>
                                                        <td><?php echo date('M d, Y', strtotime($activity['completed_at'])); ?></td>
                                                        <td><?php echo htmlspecialchars($activity['habit_name']); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $activity['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                                                <?php echo ucfirst($activity['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('h:i A', strtotime($activity['completed_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>This Month</h5>
                                </div>
                                <div class="card-body">
                                    <div id="mini-calendar"></div>
                                    <div class="mt-3">
                                        <h6>Monthly Summary</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Habits Completed:</span>
                                            <strong><?php echo $habit->getMonthlyCompleted($user_id); ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Total Calories:</span>
                                            <strong><?php echo $calorie->getMonthlyCalories($user_id); ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Success Rate:</span>
                                            <strong><?php echo $habit->getMonthlySuccessRate($user_id); ?>%</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile Sidebar -->
    <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="sidebar">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">HabitTracker</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="habits.php"><i class="fas fa-tasks me-2"></i>Habits</a></li>
                <li class="nav-item"><a class="nav-link" href="calorie-tracker.php"><i class="fas fa-fire me-2"></i>Calorie Tracker</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                <li class="nav-item"><a class="nav-link" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                <li class="nav-item mt-3"><a class="nav-link btn btn-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dark mode toggle
        document.getElementById('darkModeToggle').addEventListener('click', function() {
            const html = document.documentElement;
            const isDark = html.getAttribute('data-bs-theme') === 'dark';
            const icon = this.querySelector('i');
            
            if (isDark) {
                html.removeAttribute('data-bs-theme');
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            } else {
                html.setAttribute('data-bs-theme', 'dark');
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            }
            
            // Save preference via AJAX
            fetch('update-theme.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ dark_mode: !isDark })
            });
        });
        
        // Mark habit as complete
        document.querySelectorAll('.mark-complete').forEach(button => {
            button.addEventListener('click', function() {
                const habitId = this.dataset.habitId;
                const button = this;
                
                fetch('complete-habit.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ habit_id: habitId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        button.closest('.habit-item').classList.add('completed');
                        button.outerHTML = '<span class="badge bg-success">Completed</span>';
                        
                        // Update stats
                        location.reload();
                    }
                });
            });
        });
        
        // Weekly chart
        const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
        new Chart(weeklyCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Habits Completed',
                    data: [12, 19, 15, 17, 14, 16, 18],
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 20
                    }
                }
            }
        });
        
        // Mini calendar
        const today = new Date();
        const month = today.toLocaleString('default', { month: 'long' });
        const year = today.getFullYear();
        
        let calendarHTML = `
            <div class="text-center mb-2">
                <h5>${month} ${year}</h5>
            </div>
            <div class="calendar-grid">
                <div class="row text-center">
                    <div class="col"><small>Sun</small></div>
                    <div class="col"><small>Mon</small></div>
                    <div class="col"><small>Tue</small></div>
                    <div class="col"><small>Wed</small></div>
                    <div class="col"><small>Thu</small></div>
                    <div class="col"><small>Fri</small></div>
                    <div class="col"><small>Sat</small></div>
                </div>
                <div class="row text-center mt-2">
        `;
        
        // Simple calendar days
        for (let i = 1; i <= 31; i++) {
            if (i === today.getDate()) {
                calendarHTML += `<div class="col p-1"><span class="badge bg-primary rounded-circle">${i}</span></div>`;
            } else {
                calendarHTML += `<div class="col p-1"><small>${i}</small></div>`;
            }
            
            if (i % 7 === 0) {
                calendarHTML += '</div><div class="row text-center">';
            }
        }
        
        calendarHTML += '</div></div>';
        document.getElementById('mini-calendar').innerHTML = calendarHTML;
    </script>


<script>
    // Dark mode toggle
    document.getElementById('darkModeToggle').addEventListener('click', function() {
        const body = document.body;
        const isDark = body.getAttribute('data-bs-theme') === 'dark';
        const icon = this.querySelector('i');

        if (isDark) {
            body.removeAttribute('data-bs-theme');
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        } else {
            body.setAttribute('data-bs-theme', 'dark');
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        }

        // Save preference
        fetch('update-theme.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ dark_mode: !isDark })
        });
    });

    // Ensure icon matches current theme on page load
    window.addEventListener('DOMContentLoaded', function() {
        const isDark = document.body.getAttribute('data-bs-theme') === 'dark';
        const icon = document.querySelector('#darkModeToggle i');
        if (isDark) {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        }
    });
</script>

    
<?php include '../../includes/footer.php'; ?>
</body>

</html>