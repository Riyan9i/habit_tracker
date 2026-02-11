<?php
session_start();
require_once '../../includes/auth-check.php';
require_once '../../config/database.php';
require_once '../../models/Habit.php';
require_once '../../models/Calorie.php';
require_once '../../models/User.php';


$user_id = $_SESSION['user_id'];
$habit = new Habit($conn);
$calorie = new Calorie($conn);
$user = new User($conn);


// Habit + Calorie Data for last 7 days
// ----------------------------
$weekDays = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
$habitsCompleted = [];
$caloriesConsumed = [];

foreach ($weekDays as $day) {
    // **Real queries example**
    $stmt = $conn->prepare("SELECT COUNT(*) AS completed FROM habit_completions 
                            WHERE id=? AND DAYNAME(completion_date)=?");
    $stmt->bind_param("is",$user_id,$day);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $habitsCompleted[] = $row['completed'] ?? 0;

    // Calories example (if you track calories)
    $stmt2 = $conn->prepare("SELECT SUM(calories) AS total_cal FROM food_entries 
                             WHERE user_id=? AND DAYNAME(entry_date)=?");
    $stmt2->bind_param("is",$user_id,$day);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $row2 = $res2->fetch_assoc();
    $caloriesConsumed[] = $row2['total_cal'] ?? 0;
}

// ----------------------------
// Heatmap Data for last 12 weeks
// ----------------------------
$heatmapData = [];
$startDate = new DateTime();
$startDate->modify('-12 weeks'); // 12 weeks back

for($i=0; $i<12*7; $i++){
    $dateStr = $startDate->format('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) AS completed FROM habit_completions 
                            WHERE id=? AND completion_date=?");
    $stmt->bind_param("is",$user_id,$dateStr);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $level = min(4, $row['completed']); // level 0-4
    $heatmapData[] = $level;

    $startDate->modify('+1 day'); // next day
}



$performances = $habit->getHabitPerformance($user_id);
// Get filter parameters
$period = $_GET['period'] ?? 'week';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$chart_type = $_GET['chart_type'] ?? 'line';

// Generate report data based on period
switch ($period) {
    case 'week':
        $report_data = $habit->getWeeklyReport($user_id);
        $calorie_data = $calorie->getWeeklyCalorieData($user_id);
        break;
    case 'month':
        $report_data = $habit->getMonthlyReport($user_id);
        $calorie_data = $calorie->getMonthlyCalorieData($user_id);
        break;
    case 'custom':
        $report_data = $habit->getCustomReport($user_id, $start_date, $end_date);
        $calorie_data = $calorie->getCustomCalorieData($user_id, $start_date, $end_date);
        break;
    default:
        $report_data = $habit->getWeeklyReport($user_id);
        $calorie_data = $calorie->getWeeklyCalorieData($user_id);
}

// Calculate statistics
$total_habits = count($performances);
$completed_habits_count = array_sum(array_map(fn($p) => $p['completed'], $performances));
$success_rate = $total_habits > 0 ? round(($completed_habits_count / $total_habits) * 100) : 0;

$current_streak = $habit->getCurrentStreak($user_id);
$longest_streak = $habit->getLongestStreak($user_id);



// --- Calculate Best Performance Day ---
$best_day = 'N/A';
$best_day_rate = 0; // default
$total_habits_stmt = $conn->prepare("SELECT COUNT(*) as total FROM habits WHERE user_id = ?");
$total_habits_stmt->bind_param("i", $user_id);
$total_habits_stmt->execute();
$total_habits_result = $total_habits_stmt->get_result();
$total_habits_row = $total_habits_result->fetch_assoc();
$total_habits = $total_habits_row['total'] ?? 0;

if ($total_habits > 0) {
    $stmt = $conn->prepare("
        SELECT hc.completion_date, COUNT(*) AS completed_count
        FROM habit_completions hc
        JOIN habits h ON hc.habit_id = h.id
        WHERE h.user_id = ?
        GROUP BY hc.completion_date
        ORDER BY completed_count DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $best_day = $row['completion_date'];
        $best_day_rate = round(($row['completed_count'] / $total_habits) * 100);
    }
}


?>
<!DOCTYPE html>
<html lang="en" <?php echo $_SESSION['dark_mode'] ? 'data-bs-theme="dark"' : ''; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Habit Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .stat-card-report {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            transition: transform 0.3s;
            height: 100%;
        }
        
        [data-bs-theme="dark"] .stat-card-report {
            background: #2d2d2d;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .stat-card-report:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon-report {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: white;
            margin: 0 auto 20px;
        }
        
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        [data-bs-theme="dark"] .chart-container {
            background: #2d2d2d;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .download-btn {
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .filter-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        [data-bs-theme="dark"] .filter-card {
            background: #2d2d2d;
        }
        
        .habit-category-tag {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .progress-report {
            height: 25px;
            border-radius: 12px;
            background-color: #e9ecef;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        [data-bs-theme="dark"] .progress-report {
            background-color: #3d3d3d;
        }
        
        .progress-bar-report {
            background: linear-gradient(90deg, #4CAF50, #45a049);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 500;
            color: white;
        }
        
        .heatmap-day {
            width: 30px;
            height: 30px;
            border-radius: 5px;
            margin: 2px;
            display: inline-block;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .heatmap-day:hover {
            transform: scale(1.2);
        }
        
        .heatmap-day-0 { background-color: #ebedf0; }
        .heatmap-day-1 { background-color: #c6e48b; }
        .heatmap-day-2 { background-color: #7bc96f; }
        .heatmap-day-3 { background-color: #239a3b; }
        .heatmap-day-4 { background-color: #196127; }


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
                <!-- Report Header -->
                <div class="report-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="display-5 fw-bold">Reports & Analytics</h1>
                            <p class="lead mb-0">Track your progress and analyze your habits</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="btn btn-light me-2" id="printReport">
                                <i class="fas fa-print me-2"></i>Print
                            </button>
                            <button class="btn btn-light" id="downloadPDF">
                                <i class="fas fa-download me-2"></i>Download PDF
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Controls -->
                <div class="filter-card">
                    <div class="row align-items-center">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Report Period</label>
                            <select class="form-select" id="periodSelect">
                                <option value="week" <?php echo $period == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="custom" <?php echo $period == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3" id="customDateRange" style="<?php echo $period != 'custom' ? 'display: none;' : ''; ?>">
                            <label class="form-label">Date Range</label>
                            <div class="input-group">
                                <input type="date" class="form-control" id="startDate" value="<?php echo $start_date; ?>">
                                <span class="input-group-text">to</span>
                                <input type="date" class="form-control" id="endDate" value="<?php echo $end_date; ?>">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Chart Type</label>
                            <select class="form-select" id="chartType">
                                <option value="line" <?php echo $chart_type == 'line' ? 'selected' : ''; ?>>Line Chart</option>
                                <option value="bar" <?php echo $chart_type == 'bar' ? 'selected' : ''; ?>>Bar Chart</option>
                                <option value="pie" <?php echo $chart_type == 'pie' ? 'selected' : ''; ?>>Pie Chart</option>
                            </select>
                        </div>
                        <div class="col-md-12 text-end">
                            <button class="btn btn-primary" id="applyFilters">
                                <i class="fas fa-filter me-2"></i>Apply Filters
                            </button>
                            <button class="btn btn-outline-secondary" id="resetFilters">
                                <i class="fas fa-redo me-2"></i>Reset
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card-report">
                            <div class="stat-icon-report bg-success">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="text-center">
                                <h2 class="fw-bold"><?php echo $success_rate; ?>%</h2>

                                <p class="text-muted mb-0">Habit Success Rate</p>
                                <small class="text-success">
                                    <?php echo count($completed_habits); ?> completed

                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card-report">
                            <div class="stat-icon-report bg-primary">
                                <i class="fas fa-fire"></i>
                            </div>
                            <div class="text-center">
                                <h2 class="fw-bold"><?php echo $current_streak; ?></h2>
                                <p class="text-muted mb-0">Current Streak</p>
                                <small class="text-primary">
                                    Longest: <?php echo $longest_streak; ?> days
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card-report">
                            <div class="stat-icon-report bg-info">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="text-center">
                                <h2 class="fw-bold"><?php echo $calorie_data['avg_intake'] ?? 0; ?></h2>
                                <p class="text-muted mb-0">Avg Daily Calories</p>
                                <small class="text-info">
                                    <?php echo $calorie_data['total_intake'] ?? 0; ?> total
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card-report">
                            <div class="stat-icon-report bg-warning">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div class="text-center">
                                
                                <p class="text-muted mb-0">Best Performance Day</p>
                                <h2 class="fw-bold"><?php echo $best_day; ?></h2>
                                <small class="text-warning"><?php echo $best_day_rate; ?>% completion</small>

                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="row">
                    <!-- Habit Completion Chart -->
                    <div class="col-lg-8 mb-4">
                        <div class="chart-container">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="mb-0">Habit Completion Trend</h4>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-secondary active" data-chart="habits">
                                        Habits
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" data-chart="calories">
                                        Calories
                                    </button>
                                </div>
                            </div>
                            <canvas id="completionChart" height="250"></canvas>
                        </div>
                    </div>
                    
                    <!-- Category Distribution -->
                    <div class="col-lg-4 mb-4">
                        <div class="chart-container">
                            <h4 class="mb-4">Habit Categories</h4>
                            <canvas id="categoryChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Detailed Reports -->
                <div class="row">
                    <!-- Habit Performance -->
                    <div class="col-lg-6 mb-4">
                        <div class="chart-container">
                            <h4 class="mb-4">Habit Performance</h4>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Habit</th>
                                            <th>Category</th>
                                            <th>Completion Rate</th>
                                            <th>Streak</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $habit_performance = $habit->getHabitPerformance($user_id);
                                        foreach ($habit_performance as $performance):
                                        ?>
                                            <tr>
                                                <td><?php foreach($performances as $performance): ?>
<tr>
    <td><?php echo htmlspecialchars($performance['name']); ?></td>
    <td><?php echo htmlspecialchars($performance['category']); ?></td>
    <td><?php echo htmlspecialchars($performance['completion_rate']); ?>%</td>
    <td><?php echo htmlspecialchars($performance['streak']); ?></td>
</tr>
<?php endforeach; ?>
                                                %
                                                    </td>

                                                <td>
                                                    <span class="habit-category-tag" 
                                                          style="background-color: <?php echo $habit->getCategoryColor($performance['category']); ?>">
                                                        <?php echo $performance['category']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress-report flex-grow-1 me-2">
                                                            <div class="progress-bar-report" 
                                                                 style="width: <?php echo $performance['completion_rate']; ?>%">
                                                                <?php echo $performance['completion_rate']; ?>%
                                                            </div>
                                                        </div>
                                                        <span class="small"><?php echo $performance['completed']; ?>/<?php echo $performance['total']; ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $performance['streak'] > 7 ? 'success' : ($performance['streak'] > 3 ? 'warning' : 'danger'); ?>">
                                                        <?php echo $performance['streak']; ?> days
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Calorie Analysis -->
                    <div class="col-lg-6 mb-4">
                        <div class="chart-container">
                            <h4 class="mb-4">Calorie Analysis</h4>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6>Daily Average</h6>
                                            <h3 class="text-success"><?php echo $calorie_data['avg_intake'] ?? 0; ?></h3>
                                            <small class="text-muted">Calories consumed per day</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6>Calories Burned</h6>
                                            <h3 class="text-primary"><?php echo $calorie_data['total_burned'] ?? 0; ?></h3>
                                            <small class="text-muted">Total burned this period</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6>Best Day</h6>
                                            <h3 class="text-warning"><?php echo $calorie_data['max_intake'] ?? 0; ?></h3>
                                            <small class="text-muted">Highest intake in a day</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6>Goal Achievement</h6>
                                            <h3 class="text-info"><?php echo $calorie_data['goal_rate'] ?? 0; ?>%</h3>
                                            <small class="text-muted">Days meeting calorie goal</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Meal Distribution -->
                            <h6 class="mt-4 mb-3">Meal Distribution</h6>
                            <div class="row">
                                <?php
                                $meal_distribution = $calorie->getMealDistribution($user_id, $start_date, $end_date);
                                $meal_colors = ['Breakfast' => '#FFC107', 'Lunch' => '#4CAF50', 'Dinner' => '#2196F3', 'Snack' => '#9C27B0'];
                                
                                foreach ($meal_distribution as $meal):
                                ?>
                                    <div class="col-md-3 col-6 mb-2">
                                        <div class="text-center">
                                            <div class="mb-2" style="font-size: 2rem; color: <?php echo $meal_colors[$meal['meal_type']]; ?>">
                                                <i class="fas fa-<?php 
                                                    switch($meal['meal_type']) {
                                                        case 'Breakfast': echo 'sun'; break;
                                                        case 'Lunch': echo 'sun'; break;
                                                        case 'Dinner': echo 'moon'; break;
                                                        default: echo 'cookie-bite';
                                                    }
                                                ?>"></i>
                                            </div>
                                            <h6><?php echo $meal['meal_type']; ?></h6>
                                            <small class="text-muted"><?php echo $meal['total_calories']; ?> cal</small>
                                            <br>
                                            <small class="text-muted"><?php echo $meal['percentage']; ?>%</small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Heatmap & Timeline -->
                <div class="row">
                    <!-- Habit Heatmap -->
                    <div class="col-lg-8 mb-4">
                        <div class="chart-container">
                            <h4 class="mb-4">Habit Consistency Heatmap</h4>
                            <div id="habitHeatmap"></div>
                        </div>
                    </div>
                    
                    <!-- Improvement Timeline -->
                    <div class="col-lg-4 mb-4">
                        <div class="chart-container">
                            <h4 class="mb-4">Progress Timeline</h4>
                            <div class="timeline">
                                <?php
                                $milestones = [
                                    ['date' => date('Y-m-d', strtotime('-30 days')), 'event' => 'Started tracking habits', 'icon' => 'fas fa-flag'],
                                    ['date' => date('Y-m-d', strtotime('-25 days')), 'event' => '7-day streak achieved', 'icon' => 'fas fa-fire'],
                                    ['date' => date('Y-m-d', strtotime('-20 days')), 'event' => 'Added calorie tracking', 'icon' => 'fas fa-utensils'],
                                    ['date' => date('Y-m-d', strtotime('-15 days')), 'event' => 'Reached 80% success rate', 'icon' => 'fas fa-chart-line'],
                                    ['date' => date('Y-m-d', strtotime('-10 days')), 'event' => '30-day milestone', 'icon' => 'fas fa-trophy'],
                                    ['date' => date('Y-m-d', strtotime('-5 days')), 'event' => 'Best performance day', 'icon' => 'fas fa-star'],
                                ];
                                
                                foreach ($milestones as $milestone):
                                ?>
                                    <div class="timeline-item mb-3">
                                        <div class="d-flex">
                                            <div class="timeline-icon me-3">
                                                <i class="<?php echo $milestone['icon']; ?> text-primary"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <h6 class="mb-1"><?php echo $milestone['event']; ?></h6>
                                                <small class="text-muted"><?php echo date('M d, Y', strtotime($milestone['date'])); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Insights & Recommendations -->
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="chart-container">
                            <h4 class="mb-4"><i class="fas fa-lightbulb me-2"></i>Insights & Recommendations</h4>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="card border-success">
                                        <div class="card-body">
                                            <h5 class="card-title text-success">
                                                <i class="fas fa-thumbs-up me-2"></i>What's Working
                                            </h5>
                                            <ul class="mb-0">
                                                <li>You're most consistent in the morning</li>
                                                <li>Health habits have <span> <?php echo $success_rate; ?>%</span> completion rate</li>
                                                <li>Weekly streaks are improving</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card border-warning">
                                        <div class="card-body">
                                            <h5 class="card-title text-warning">
                                                <i class="fas fa-exclamation-triangle me-2"></i>Areas to Improve
                                            </h5>
                                            <ul class="mb-0">
                                                <li>Weekend consistency is lower</li>
                                                <li>Evening habits need more attention</li>
                                                <li>Calorie intake varies widely</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card border-info">
                                        <div class="card-body">
                                            <h5 class="card-title text-info">
                                                <i class="fas fa-bullseye me-2"></i>Recommendations
                                            </h5>
                                            <ul class="mb-0">
                                                <li>Set reminders for weekend habits</li>
                                                <li>Add more evening routines</li>
                                                <li>Track calories more consistently</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {

    // -------------------------
    // Filter controls
    // -------------------------
    const periodSelect = document.getElementById('periodSelect');
    const customRange = document.getElementById('customDateRange');
    periodSelect.addEventListener('change', function() {
        customRange.style.display = this.value === 'custom' ? 'block' : 'none';
    });

    document.getElementById('applyFilters').addEventListener('click', function() {
        const period = periodSelect.value;
        const chartType = document.getElementById('chartType').value;
        let url = `reports.php?period=${period}&chart_type=${chartType}`;
        if (period === 'custom') {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            url += `&start_date=${startDate}&end_date=${endDate}`;
        }
        window.location.href = url;
    });

    document.getElementById('resetFilters').addEventListener('click', function() {
        window.location.href = 'reports.php';
    });

    // -------------------------
    // Charts
    // -------------------------
    let habitChart;
    let currentChart = 'habits'; // default

    const completionCtx = document.getElementById('completionChart').getContext('2d');

    habitChart = new Chart(completionCtx, {
        type: '<?php echo $chart_type; ?>',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Habits Completed',
                data: [5, 8, 7, 9, 6, 4, 7], // replace with PHP dynamic data if needed
                borderColor: '#4CAF50',
                backgroundColor: 'rgba(76, 175, 80, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 10,
                    title: { display: true, text: 'Habits Completed' }
                }
            }
        }
    });

    // Chart switcher buttons
    document.querySelectorAll('[data-chart]').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('[data-chart]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            currentChart = this.dataset.chart;
            updateChart();
        });
    });

    function updateChart() {
        if (currentChart === 'calories') {
            habitChart.data.datasets[0].label = 'Calories Consumed';
            habitChart.data.datasets[0].data = [1800, 2100, 1900, 2200, 2000, 2400, 2100]; // replace with dynamic data
            habitChart.options.scales.y.title.text = 'Calories';
            habitChart.options.scales.y.max = 3000;
        } else {
            habitChart.data.datasets[0].label = 'Habits Completed';
            habitChart.data.datasets[0].data = [5, 8, 7, 9, 6, 4, 7]; // dynamic data
            habitChart.options.scales.y.title.text = 'Habits Completed';
            habitChart.options.scales.y.max = 10;
        }
        habitChart.update();
    }

    // -------------------------
    // Heatmap generator
    // -------------------------
    function generateHeatmap() {
        const heatmapContainer = document.getElementById('habitHeatmap');
        let heatmapHTML = '<div class="text-center">';

        const weeks = 12; // 12 weeks
        const today = new Date();
        const startDate = new Date(today);
        startDate.setDate(startDate.getDate() - weeks * 7);

        for (let w = 0; w < weeks; w++) {
            heatmapHTML += '<div class="d-inline-block me-1">';
            for (let d = 0; d < 7; d++) {
                const randomLevel = Math.floor(Math.random() * 5); // replace with real data if available
                const date = new Date(startDate);
                date.setDate(date.getDate() + (w * 7 + d));
                heatmapHTML += `<div class="heatmap-day heatmap-day-${randomLevel}" title="${date.toLocaleDateString()} - Level ${randomLevel}"></div>`;
            }
            heatmapHTML += '</div>';
        }
        heatmapHTML += '</div>';
        heatmapContainer.innerHTML = heatmapHTML;
    }

    generateHeatmap();

    // -------------------------
    // Print & PDF download
    // -------------------------
    document.getElementById('printReport').addEventListener('click', function() {
        window.print();
    });

    document.getElementById('downloadPDF').addEventListener('click', function() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'pt', 'a4');
        const element = document.querySelector('main');

        html2canvas(element, { scale: 2, useCORS: true }).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const imgWidth = 595;
            const imgHeight = canvas.height * imgWidth / canvas.width;
            doc.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);
            doc.save('habit-tracker-report.pdf');
        });
    });

});
</script>



    <?php include '../../includes/footer.php'; ?>
</body>
</html>