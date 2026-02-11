<?php
session_start();
require_once '../../includes/auth-check.php';
require_once '../../config/database.php';

// Check if admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ../auth/login.php');
    exit();
}

// Get stats
$stats = [];
$queries = [
    'total_users' => "SELECT COUNT(*) as count FROM users WHERE is_admin = 0",
    'active_today' => "SELECT COUNT(DISTINCT user_id) as count FROM habit_completions WHERE DATE(completed_at) = CURDATE()",
    'total_habits' => "SELECT COUNT(*) as count FROM habits",
    'avg_completion' => "SELECT ROUND(AVG(completion_rate), 2) as rate FROM (
        SELECT h.user_id, 
               (COUNT(hc.id) / (DATEDIFF(CURDATE(), MIN(h.created_at)) + 1)) * 100 as completion_rate
        FROM habits h
        LEFT JOIN habit_completions hc ON h.id = hc.habit_id AND DATE(hc.completion_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY h.user_id
    ) as rates"
];

foreach ($queries as $key => $sql) {
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $stats[$key] = $row['count'] ?? $row['rate'] ?? 0;
}

// Get recent users
$recent_users = $conn->query("SELECT * FROM users WHERE is_admin = 0 ORDER BY created_at DESC LIMIT 5");

// Get habit statistics
$habit_stats = $conn->query("
    SELECT category, COUNT(*) as count 
    FROM habits 
    GROUP BY category 
    ORDER BY count DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Habit Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .admin-sidebar {
            background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
            min-height: 100vh;
            color: white;
        }
        
        .admin-nav .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .admin-nav .nav-link:hover, .admin-nav .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .admin-nav .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .stat-card-admin {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            overflow: hidden;
        }
        
        .stat-card-admin:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon-admin {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }
        
        .bg-gradient-1 { background: linear-gradient(45deg, #667eea, #764ba2); }
        .bg-gradient-2 { background: linear-gradient(45deg, #f093fb, #f5576c); }
        .bg-gradient-3 { background: linear-gradient(45deg, #4facfe, #00f2fe); }
        .bg-gradient-4 { background: linear-gradient(45deg, #43e97b, #38f9d7); }
        
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Admin Sidebar -->
            <div class="col-lg-2 col-md-3 admin-sidebar">
                <div class="sidebar-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4><i class="fas fa-chart-line me-2"></i>Admin Panel</h4>
                        <p class="small opacity-75">Habit Tracker Management</p>
                    </div>
                    
                    <ul class="nav flex-column admin-nav">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i> User Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="habits-management.php">
                                <i class="fas fa-tasks"></i> Habit Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="notifications.php">
                                <i class="fas fa-bell"></i> Notifications
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cogs"></i> Settings
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link btn btn-outline-light" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-10 col-md-9">
                <!-- Top Bar -->
                <nav class="navbar navbar-light bg-light border-bottom py-3">
                    <div class="container-fluid">
                        <div class="d-flex align-items-center">
                            <h5 class="mb-0">Admin Dashboard</h5>
                            <span class="badge bg-primary ms-3">Super Admin</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="me-3">Welcome, <?php echo $_SESSION['user_name']; ?></span>
                            <img src="../../assets/images/uploads/<?php echo $_SESSION['profile_picture'] ?? 'default.png'; ?>" 
                                 class="rounded-circle" width="40" height="40">
                        </div>
                    </div>
                </nav>
                
                <div class="container-fluid mt-4">
                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card-admin">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted fw-semibold">Total Users</h6>
                                            <h2 class="fw-bold"><?php echo $stats['total_users']; ?></h2>
                                            <span class="text-success small">
                                                <i class="fas fa-arrow-up"></i> 12% from last month
                                            </span>
                                        </div>
                                        <div class="stat-icon-admin bg-gradient-1">
                                            <i class="fas fa-users"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card-admin">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted fw-semibold">Active Today</h6>
                                            <h2 class="fw-bold"><?php echo $stats['active_today']; ?></h2>
                                            <span class="text-success small">
                                                <i class="fas fa-arrow-up"></i> 5% from yesterday
                                            </span>
                                        </div>
                                        <div class="stat-icon-admin bg-gradient-2">
                                            <i class="fas fa-user-check"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card-admin">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted fw-semibold">Total Habits</h6>
                                            <h2 class="fw-bold"><?php echo $stats['total_habits']; ?></h2>
                                            <span class="text-success small">
                                                <i class="fas fa-arrow-up"></i> 8% this week
                                            </span>
                                        </div>
                                        <div class="stat-icon-admin bg-gradient-3">
                                            <i class="fas fa-tasks"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card-admin">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted fw-semibold">Avg. Completion</h6>
                                            <h2 class="fw-bold"><?php echo $stats['avg_completion']; ?>%</h2>
                                            <span class="text-success small">
                                                <i class="fas fa-arrow-up"></i> 3% improvement
                                            </span>
                                        </div>
                                        <div class="stat-icon-admin bg-gradient-4">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Charts and Tables -->
                    <div class="row">
                        <!-- User Growth Chart -->
                        <div class="col-lg-8 mb-4">
                            <div class="chart-container">
                                <h5 class="mb-4">User Growth & Activity</h5>
                                <canvas id="userGrowthChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Recent Users -->
                        <div class="col-lg-4 mb-4">
                            <div class="chart-container">
                                <h5 class="mb-4">Recent Users</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Joined</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($user = $recent_users->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                                    <td><?php echo date('M d', strtotime($user['created_at'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Habit Categories & System Status -->
                    <div class="row">
                        <!-- Habit Categories -->
                        <div class="col-lg-6 mb-4">
                            <div class="chart-container">
                                <h5 class="mb-4">Habit Categories Distribution</h5>
                                <canvas id="habitCategoriesChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- System Status -->
                        <div class="col-lg-6 mb-4">
                            <div class="chart-container">
                                <h5 class="mb-4">System Status</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <i class="fas fa-database fa-2x text-primary mb-3"></i>
                                                <h5>Database</h5>
                                                <span class="badge bg-success">Online</span>
                                                <p class="small text-muted mt-2">24.5 MB used</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <i class="fas fa-envelope fa-2x text-warning mb-3"></i>
                                                <h5>Email Service</h5>
                                                <span class="badge bg-success">Active</span>
                                                <p class="small text-muted mt-2">98% success rate</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <i class="fas fa-sms fa-2x text-info mb-3"></i>
                                                <h5>SMS Service</h5>
                                                <span class="badge bg-success">Active</span>
                                                <p class="small text-muted mt-2">1000 credits left</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <i class="fas fa-server fa-2x text-secondary mb-3"></i>
                                                <h5>Server</h5>
                                                <span class="badge bg-success">Healthy</span>
                                                <p class="small text-muted mt-2">65% CPU usage</p>
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
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // User Growth Chart
        const growthCtx = document.getElementById('userGrowthChart').getContext('2d');
        new Chart(growthCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                datasets: [{
                    label: 'New Users',
                    data: [120, 190, 300, 500, 800, 1100, 1500],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Active Users',
                    data: [80, 150, 250, 400, 650, 900, 1200],
                    borderColor: '#f5576c',
                    backgroundColor: 'rgba(245, 87, 108, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
        
        // Habit Categories Chart
        const categoriesCtx = document.getElementById('habitCategoriesChart').getContext('2d');
        new Chart(categoriesCtx, {
            type: 'doughnut',
            data: {
                labels: ['Health', 'Study', 'Fitness', 'Work', 'Personal', 'Social', 'Other'],
                datasets: [{
                    data: [25, 20, 18, 15, 10, 7, 5],
                    backgroundColor: [
                        '#4CAF50',
                        '#2196F3',
                        '#FF5722',
                        '#9C27B0',
                        '#FFC107',
                        '#00BCD4',
                        '#795548'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });
    </script>
</body>
</html>