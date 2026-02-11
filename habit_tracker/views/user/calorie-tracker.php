<?php
session_start();
require_once '../../includes/auth-check.php';
require_once '../../config/database.php';
require_once '../../models/Calorie.php';

$user_id = $_SESSION['user_id'];
$calorie = new Calorie($conn);


// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_food'])) {
        $result = $calorie->addFoodEntry($user_id, $_POST);
        if ($result['success']) {
            $_SESSION['message'] = $result['message'];
            header('Location: calorie-tracker.php');
            exit();
        }
    } elseif (isset($_POST['add_activity'])) {
        $result = $calorie->addActivityEntry($user_id, $_POST);
        $_SESSION['message'] = $result['message'];
        header('Location: calorie-tracker.php');
        exit();
    } elseif (isset($_POST['delete_entry'])) {
        $result = $calorie->deleteEntry($_POST['id'], $_POST['type']);
        $_SESSION['message'] = $result['message'];
        header('Location: calorie-tracker.php');
        exit();
    }

    elseif (isset($_POST['log_water'])) {   // <-- এখানে নতুন ব্লক
        $amount = floatval($_POST['water_amount']);
        $result = $calorie->addWaterEntry($user_id, $amount);
        $_SESSION['message'] = $result['message'];
        header('Location: calorie-tracker.php');
        exit();
    }
}

// Get today's data
$today_food = $calorie->getTodayFoodEntries($user_id);
$today_activities = $calorie->getTodayActivityEntries($user_id);
$total_calories = $calorie->getTodayTotalCalories($user_id);
$total_burned = $calorie->getTodayBurnedCalories($user_id);
$calorie_balance = $total_calories - $total_burned;

// Get weekly data for chart
$weekly_data = $calorie->getWeeklyCalorieData($user_id);

// Get user weight goal
$weight_goal = $_SESSION['weight_goal'] ?? 'Maintain';
$daily_calorie_goal = $calorie->calculateDailyCalorieGoal($user_id, $weight_goal);
$today_water = $calorie->getTodayWaterIntake($user_id);  // লিটার
$daily_water_goal = 2.5; // ধরলো ২.৫ লিটার গোল

?>
<!DOCTYPE html>
<html lang="en" <?php echo $_SESSION['dark_mode'] ? 'data-bs-theme="dark"' : ''; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calorie Tracker - Habit Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .calorie-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .calorie-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        
        .calorie-number {
            font-size: 2.5rem;
            font-weight: bold;
        }
        
        .food-item, .activity-item {
            border-left: 4px solid #4CAF50;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 10px;
            background: #f8f9fa;
            transition: all 0.3s;
        }
        
        [data-bs-theme="dark"] .food-item,
        [data-bs-theme="dark"] .activity-item {
            background: #2d2d2d;
        }
        
        .food-item:hover, .activity-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .food-item.food { border-left-color: #4CAF50; }
        .activity-item.activity { border-left-color: #2196F3; }
        
        .calorie-badge {
            font-size: 0.9rem;
            padding: 5px 15px;
            border-radius: 20px;
        }
        
        .progress-calories {
            height: 20px;
            border-radius: 10px;
            background-color: #e9ecef;
            overflow: hidden;
        }
        
        [data-bs-theme="dark"] .progress-calories {
            background-color: #3d3d3d;
        }
        
        .progress-bar-intake {
            background: linear-gradient(90deg, #4CAF50, #45a049);
        }
        
        .progress-bar-burned {
            background: linear-gradient(90deg, #2196F3, #0d8bf2);
        }
        
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 500;
            padding: 12px 25px;
            border-radius: 0;
        }
        
        .nav-tabs .nav-link.active {
            color: #4CAF50;
            border-bottom-color: #4CAF50;
            background: transparent;
        }
        
        .quick-add-btn {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .quick-add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }


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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-fire me-2"></i>Calorie Tracker
                    </h1>
                    <div class="btn-group">
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addFoodModal">
                            <i class="fas fa-utensils me-2"></i>Add Food
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
                            <i class="fas fa-running me-2"></i>Add Activity
                        </button>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Calorie Summary -->
                <div class="calorie-summary">
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center">
                            <div class="calorie-circle">
                                <span class="calorie-number"><?php echo $calorie_balance; ?></span>
                                <small>Net Calories</small>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <h5><i class="fas fa-arrow-down text-success me-2"></i>Consumed</h5>
                                    <h3 class="fw-bold"><?php echo $total_calories; ?> cal</h3>
                                    <div class="progress-calories mt-2">
                                        <div class="progress-bar progress-bar-intake" 
                                             style="width: <?php echo min(100, ($total_calories / $daily_calorie_goal) * 100); ?>%"></div>
                                    </div>
                                    <small class="text-white-50">Goal: <?php echo $daily_calorie_goal; ?> cal</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h5><i class="fas fa-arrow-up text-info me-2"></i>Burned</h5>
                                    <h3 class="fw-bold"><?php echo $total_burned; ?> cal</h3>
                                    <div class="progress-calories mt-2">
                                        <div class="progress-bar progress-bar-burned" 
                                             style="width: <?php echo min(100, ($total_burned / 1000) * 100); ?>%"></div>
                                    </div>
                                    <small class="text-white-50">Target: 1000 cal</small>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span class="badge bg-light text-dark me-2">
                                    <i class="fas fa-weight me-1"></i> Goal: <?php echo $weight_goal; ?>
                                </span>
                                <span class="badge <?php echo $calorie_balance > 500 ? 'bg-danger' : ($calorie_balance < -500 ? 'bg-info' : 'bg-success'); ?>">
                                    <?php 
                                    if ($calorie_balance > 500) {
                                        echo '<i class="fas fa-exclamation-triangle me-1"></i> Over by ' . $calorie_balance . ' cal';
                                    } elseif ($calorie_balance < -500) {
                                        echo '<i class="fas fa-check-circle me-1"></i> Under by ' . abs($calorie_balance) . ' cal';
                                    } else {
                                        echo '<i class="fas fa-thumbs-up me-1"></i> Good Balance';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Add Buttons -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title mb-3">Quick Add Common Items</h6>
                                <div class="row g-2">
                                    <?php 
                                    $quick_foods = [
                                        ['name' => 'Apple', 'calories' => 95, 'icon' => 'fa-apple-alt'],
                                        ['name' => 'Banana', 'calories' => 105, 'icon' => 'fa-lemon'],
                                        ['name' => 'Chicken Breast', 'calories' => 165, 'icon' => 'fa-drumstick-bite'],
                                        ['name' => 'Rice (1 cup)', 'calories' => 205, 'icon' => 'fa-bowl-rice'],
                                        ['name' => 'Egg', 'calories' => 78, 'icon' => 'fa-egg'],
                                        ['name' => 'Bread (slice)', 'calories' => 79, 'icon' => 'fa-bread-slice']
                                    ];
                                    
                                    foreach ($quick_foods as $food): ?>
                                        <div class="col-md-2 col-sm-4 col-6">
                                            <button class="btn btn-outline-primary w-100 quick-add-btn" 
                                                    data-food-name="<?php echo $food['name']; ?>"
                                                    data-food-calories="<?php echo $food['calories']; ?>">
                                                <i class="fas <?php echo $food['icon']; ?> me-2"></i>
                                                <?php echo $food['name']; ?>
                                                <small class="d-block"><?php echo $food['calories']; ?> cal</small>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs for Food & Activities -->
                <ul class="nav nav-tabs mb-4" id="calorieTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#food">
                            <i class="fas fa-utensils me-2"></i>Food Intake
                            <span class="badge bg-success ms-2"><?php echo count($today_food); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#activities">
                            <i class="fas fa-running me-2"></i>Activities
                            <span class="badge bg-primary ms-2"><?php echo count($today_activities); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#weekly">
                            <i class="fas fa-chart-line me-2"></i>Weekly Chart
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content" id="calorieTabContent">
                    <!-- Food Tab -->
                    <div class="tab-pane fade show active" id="food">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Today's Food Intake</h5>
                                        <span class="badge bg-success">Total: <?php echo $total_calories; ?> cal</span>
                                    </div>
                                    <div class="card-body">
                                        <?php if (count($today_food) > 0): ?>
                                            <?php 
                                            $meals = ['Breakfast' => [], 'Lunch' => [], 'Dinner' => [], 'Snack' => []];
                                            foreach ($today_food as $food) {
                                                $meals[$food['meal_type']][] = $food;
                                            }
                                            
                                            foreach ($meals as $meal_type => $meal_foods):
                                                if (!empty($meal_foods)):
                                            ?>
                                                <h6 class="mt-3 mb-2">
                                                    <i class="fas fa-<?php 
                                                        switch($meal_type) {
                                                            case 'Breakfast': echo 'sun'; break;
                                                            case 'Lunch': echo 'sun'; break;
                                                            case 'Dinner': echo 'moon'; break;
                                                            default: echo 'cookie-bite';
                                                        }
                                                    ?> me-2"></i><?php echo $meal_type; ?>
                                                    <small class="text-muted">
                                                        (<?php echo array_sum(array_column($meal_foods, 'calories')); ?> cal)
                                                    </small>
                                                </h6>
                                                <?php foreach ($meal_foods as $food): ?>
                                                    <div class="food-item food">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <h6 class="mb-1"><?php echo htmlspecialchars($food['food_name']); ?></h6>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-weight me-1"></i><?php echo $food['quantity']; ?>
                                                                    <span class="ms-2">
                                                                        <i class="fas fa-clock me-1"></i>
                                                                        <?php echo date('h:i A', strtotime($food['created_at'])); ?>
                                                                    </span>
                                                                </small>
                                                            </div>
                                                            <div class="text-end">
                                                                <span class="calorie-badge bg-success">
                                                                    <?php echo $food['calories']; ?> cal
                                                                </span>
                                                                <button class="btn btn-sm btn-outline-danger ms-2 delete-entry" 
                                                                        data-id="<?php echo $food['id']; ?>"
                                                                        data-type="food"
                                                                        data-name="<?php echo htmlspecialchars($food['food_name']); ?>">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php 
                                                endif;
                                            endforeach;
                                            ?>
                                        <?php else: ?>
                                            <div class="text-center py-5">
                                                <i class="fas fa-utensils fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No food entries for today</p>
                                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFoodModal">
                                                    <i class="fas fa-plus me-2"></i>Add Your First Food
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Daily Nutrition -->
                            <div class="col-lg-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Daily Nutrition</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small>Protein</small>
                                                <small>65g / 120g</small>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-success" style="width: 54%"></div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small>Carbs</small>
                                                <small>180g / 300g</small>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-warning" style="width: 60%"></div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small>Fat</small>
                                                <small>45g / 80g</small>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-danger" style="width: 56%"></div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small>Fiber</small>
                                                <small>22g / 30g</small>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-info" style="width: 73%"></div>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                            <h6>Water Intake</h6>
                                            <div class="water-tracker mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small><?php echo $today_water; ?>L today</small>
                                                    <small><?php echo $today_water; ?>L / <?php echo $daily_water_goal; ?>L</small>
                                                </div>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-info" 
                                                         style="width: <?php echo min(100, ($today_water / $daily_water_goal) * 100); ?>%">
                                                    </div>
                                                </div>
                                            </div>



                                        
                                        <button class="btn btn-outline-info w-100 mt-2" data-bs-toggle="modal" data-bs-target="#logWaterModal">
                                         <i class="fas fa-tint me-2"></i>Log Water
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    
                    <!-- Activities Tab -->
                    <div class="tab-pane fade" id="activities">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Today's Activities</h5>
                                        <span class="badge bg-primary">Total Burned: <?php echo $total_burned; ?> cal</span>
                                    </div>
                                    <div class="card-body">
                                        <?php if (count($today_activities) > 0): ?>
                                            <?php foreach ($today_activities as $activity): ?>
                                                <div class="activity-item activity">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($activity['activity_name']); ?></h6>
                                                            <small class="text-muted">
                                                                <i class="fas fa-clock me-1"></i><?php echo $activity['duration']; ?> min
                                                                <span class="ms-2">
                                                                    <i class="fas fa-calendar me-1"></i>
                                                                    <?php echo date('h:i A', strtotime($activity['created_at'])); ?>
                                                                </span>
                                                            </small>
                                                        </div>
                                                        <div class="text-end">
                                                            <span class="calorie-badge bg-primary">
                                                                <i class="fas fa-fire me-1"></i><?php echo $activity['calories_burned']; ?> cal
                                                            </span>
                                                            <button class="btn btn-sm btn-outline-danger ms-2 delete-entry" 
                                                                    data-id="<?php echo $activity['id']; ?>"
                                                                    data-type="activity"
                                                                    data-name="<?php echo htmlspecialchars($activity['activity_name']); ?>">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-center py-5">
                                                <i class="fas fa-running fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No activities logged for today</p>
                                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
                                                    <i class="fas fa-plus me-2"></i>Log Your First Activity
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Activity Suggestions -->
                            <div class="col-lg-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Activity Suggestions</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php 
                                        $suggestions = [
                                            ['name' => 'Brisk Walking', 'duration' => '30 min', 'calories' => 150],
                                            ['name' => 'Cycling', 'duration' => '30 min', 'calories' => 250],
                                            ['name' => 'Yoga', 'duration' => '30 min', 'calories' => 120],
                                            ['name' => 'Swimming', 'duration' => '30 min', 'calories' => 300]
                                        ];
                                        
                                        foreach ($suggestions as $suggestion): ?>
                                            <div class="mb-3 p-3 border rounded">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo $suggestion['name']; ?></h6>
                                                        <small class="text-muted"><?php echo $suggestion['duration']; ?></small>
                                                    </div>
                                                    <div>
                                                        <span class="badge bg-light text-dark">
                                                            <?php echo $suggestion['calories']; ?> cal
                                                        </span>
                                                        <button class="btn btn-sm btn-outline-primary ms-2 quick-add-activity"
                                                                data-activity-name="<?php echo $suggestion['name']; ?>"
                                                                data-activity-calories="<?php echo $suggestion['calories']; ?>">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Weekly Chart Tab -->
                    <div class="tab-pane fade" id="weekly">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Weekly Calorie Trends</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="weeklyCalorieChart" height="100"></canvas>
                            </div>
                        </div>
                        
                        <!-- Weekly Summary -->
                        <div class="row mt-4">
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-success"><?php echo $weekly_data['avg_intake'] ?? 0; ?></h3>
                                        <p class="text-muted mb-0">Avg Daily Intake</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-primary"><?php echo $weekly_data['avg_burned'] ?? 0; ?></h3>
                                        <p class="text-muted mb-0">Avg Daily Burned</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-info"><?php echo $weekly_data['total_intake'] ?? 0; ?></h3>
                                        <p class="text-muted mb-0">Weekly Total Intake</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-warning"><?php echo $weekly_data['total_burned'] ?? 0; ?></h3>
                                        <p class="text-muted mb-0">Weekly Total Burned</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Food Modal -->
    <div class="modal fade" id="addFoodModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-utensils me-2"></i>Add Food Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Food Name *</label>
                                <input type="text" class="form-control" name="food_name" placeholder="e.g., Apple, Rice, Chicken" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Meal Type *</label>
                                <select class="form-select" name="meal_type" required>
                                    <option value="">Select Meal</option>
                                    <option value="Breakfast">Breakfast</option>
                                    <option value="Lunch">Lunch</option>
                                    <option value="Dinner">Dinner</option>
                                    <option value="Snack">Snack</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Quantity *</label>
                                <input type="text" class="form-control" name="quantity" placeholder="e.g., 1 cup, 100g, 2 pieces" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Calories *</label>
                                <input type="number" class="form-control" name="calories" placeholder="Calories" required min="1">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Serving Size</label>
                                <input type="text" class="form-control" name="serving_size" placeholder="Optional">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Notes (Optional)</label>
                                <textarea class="form-control" name="notes" rows="2" placeholder="Any additional notes..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_food" class="btn btn-success">Add Food</button>
                    </div>
                </form>
            </div>
        </div>
    </div>



        <!-- Log Water Modal -->
<<div class="modal fade" id="logWaterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-tint me-2"></i>Log Water Intake</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Amount (in liters)</label>
                        <input type="number" step="0.1" min="0.1" class="form-control" name="water_amount" placeholder="e.g., 0.25" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="log_water" class="btn btn-info">Log Water</button>
                </div>
            </form>
        </div>
    </div>
</div>





    
    <!-- Add Activity Modal -->
    <div class="modal fade" id="addActivityModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-running me-2"></i>Add Activity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Activity Name *</label>
                                <input type="text" class="form-control" name="activity_name" placeholder="e.g., Running, Gym, Yoga" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Duration (minutes) *</label>
                                <input type="number" class="form-control" name="duration" placeholder="e.g., 30" required min="1">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Calories Burned *</label>
                                <input type="number" class="form-control" name="calories_burned" placeholder="Estimated calories" required min="1">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Activity Date</label>
                                <input type="date" class="form-control" name="activity_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Activity Type</label>
                                <select class="form-select" name="activity_type">
                                    <option value="Cardio">Cardio</option>
                                    <option value="Strength">Strength Training</option>
                                    <option value="Flexibility">Flexibility</option>
                                    <option value="Sports">Sports</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Notes (Optional)</label>
                                <textarea class="form-control" name="notes" rows="2" placeholder="How did it go?"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_activity" class="btn btn-primary">Add Activity</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteEntryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Delete Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="deleteEntryId">
                        <input type="hidden" name="type" id="deleteEntryType">
                        <p>Are you sure you want to delete "<strong id="deleteEntryName"></strong>"?</p>
                        <p class="text-danger">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_entry" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Quick add food buttons
        document.querySelectorAll('.quick-add-btn').forEach(button => {
            button.addEventListener('click', function() {
                const foodName = this.dataset.foodName;
                const foodCalories = this.dataset.foodCalories;
                
                // Fill the add food modal
                document.querySelector('input[name="food_name"]').value = foodName;
                document.querySelector('input[name="calories"]').value = foodCalories;
                
                // Show the modal
                const modal = new bootstrap.Modal(document.getElementById('addFoodModal'));
                modal.show();
            });
        });
        
        // Quick add activity buttons
        document.querySelectorAll('.quick-add-activity').forEach(button => {
            button.addEventListener('click', function() {
                const activityName = this.dataset.activityName;
                const activityCalories = this.dataset.activityCalories;
                
                // Fill the add activity modal
                document.querySelector('input[name="activity_name"]').value = activityName;
                document.querySelector('input[name="calories_burned"]').value = activityCalories;
                document.querySelector('input[name="duration"]').value = 30;
                
                // Show the modal
                const modal = new bootstrap.Modal(document.getElementById('addActivityModal'));
                modal.show();
            });
        });
        
        // Delete entry
        document.querySelectorAll('.delete-entry').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('deleteEntryId').value = this.dataset.id;
                document.getElementById('deleteEntryType').value = this.dataset.type;
                document.getElementById('deleteEntryName').textContent = this.dataset.name;
                
                const modal = new bootstrap.Modal(document.getElementById('deleteEntryModal'));
                modal.show();
            });
        });
        
        // Weekly calorie chart
        const weeklyCtx = document.getElementById('weeklyCalorieChart').getContext('2d');
        new Chart(weeklyCtx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [
                    {
                        label: 'Calories Consumed',
                        data: [1800, 2100, 1900, 2200, 2000, 2400, 2100],
                        backgroundColor: '#4CAF50',
                        borderRadius: 5
                    },
                    {
                        label: 'Calories Burned',
                        data: [800, 950, 850, 900, 1000, 1100, 950],
                        backgroundColor: '#2196F3',
                        borderRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Calories'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
        
        // Auto-tab switching based on URL parameter
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