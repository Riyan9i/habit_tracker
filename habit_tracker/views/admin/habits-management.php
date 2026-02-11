<?php
session_start();
require_once '../../includes/auth-check.php';
require_once '../../config/database.php';

// Check if admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ../auth/login.php');
    exit();
}

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $habit_id = $_GET['id'] ?? 0;
    
    switch ($action) {
        case 'feature':
            $conn->query("UPDATE habits SET is_featured = 1 WHERE id = $habit_id");
            $_SESSION['message'] = "Habit featured successfully!";
            break;
        case 'unfeature':
            $conn->query("UPDATE habits SET is_featured = 0 WHERE id = $habit_id");
            $_SESSION['message'] = "Habit unfeatured successfully!";
            break;
        case 'delete':
            $conn->query("DELETE FROM habits WHERE id = $habit_id");
            $_SESSION['message'] = "Habit deleted successfully!";
            break;
    }
    
    header('Location: habits-management.php');
    exit();
}

// Get all habits with user info
$habits = $conn->query("
    SELECT h.*, u.name as user_name, u.email as user_email,
           (SELECT COUNT(*) FROM habit_completions WHERE habit_id = h.id) as completions,
           (SELECT COUNT(DISTINCT DATE(completion_date)) FROM habit_completions WHERE habit_id = h.id) as active_days
    FROM habits h
    JOIN users u ON h.user_id = u.id
    ORDER BY h.created_at DESC
");

// Get habit statistics
$total_habits = $conn->query("SELECT COUNT(*) as count FROM habits")->fetch_assoc()['count'];
$active_habits = $conn->query("SELECT COUNT(*) as count FROM habits WHERE is_active = 1")->fetch_assoc()['count'];
$featured_habits = $conn->query("SELECT COUNT(*) as count FROM habits WHERE is_featured = 1")->fetch_assoc()['count'];
$today_completions = $conn->query("SELECT COUNT(*) as count FROM habit_completions WHERE DATE(completion_date) = CURDATE()")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Habit Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .habit-management-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .habit-item-admin {
            border-left: 4px solid #4CAF50;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 10px;
            background-color: #f8f9fa;
            transition: all 0.3s;
        }
        
        .habit-item-admin:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .habit-category-badge {
            font-size: 0.8rem;
            padding: 5px 12px;
            border-radius: 20px;
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .completion-rate {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .featured-badge {
            background: linear-gradient(45deg, #FFC107, #FF9800);
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            display: inline-block;
        }
        
        .stats-card-admin {
            text-align: center;
            padding: 20px;
            border-radius: 15px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            height: 100%;
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #4CAF50;
            display: block;
        }
        
        .tab-content {
            padding: 20px 0;
        }
        
        .nav-tabs-habit .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 500;
            padding: 12px 25px;
            border-radius: 0;
        }
        
        .nav-tabs-habit .nav-link.active {
            color: #4CAF50;
            border-bottom-color: #4CAF50;
            background: transparent;
        }
        
        .category-filter {
            margin-bottom: 20px;
        }
        
        .category-filter .btn {
            border-radius: 20px;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .category-filter .btn.active {
            background-color: #4CAF50;
            color: white;
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
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i> User Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="habits-management.php">
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
                            <h5 class="mb-0">
                                <i class="fas fa-tasks me-2"></i>Habit Management
                            </h5>
                            <span class="badge bg-primary ms-3">Total: <?php echo $total_habits; ?> habits</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="me-3">Welcome, <?php echo $_SESSION['user_name']; ?></span>
                            <img src="../../assets/images/uploads/<?php echo $_SESSION['profile_picture'] ?? 'default.png'; ?>" 
                                 class="rounded-circle" width="40" height="40">
                        </div>
                    </div>
                </nav>
                
                <div class="container-fluid mt-4">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                            <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stats-card-admin">
                                <div class="stat-icon-admin bg-gradient-1 mb-3">
                                    <i class="fas fa-tasks"></i>
                                </div>
                                <span class="stats-number"><?php echo $total_habits; ?></span>
                                <p class="text-muted mb-0">Total Habits</p>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stats-card-admin">
                                <div class="stat-icon-admin bg-gradient-2 mb-3">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <span class="stats-number"><?php echo $today_completions; ?></span>
                                <p class="text-muted mb-0">Today's Completions</p>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stats-card-admin">
                                <div class="stat-icon-admin bg-gradient-3 mb-3">
                                    <i class="fas fa-star"></i>
                                </div>
                                <span class="stats-number"><?php echo $featured_habits; ?></span>
                                <p class="text-muted mb-0">Featured Habits</p>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stats-card-admin">
                                <div class="stat-icon-admin bg-gradient-4 mb-3">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <span class="stats-number"><?php echo $active_habits; ?></span>
                                <p class="text-muted mb-0">Active Habits</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Habit Management Tabs -->
                    <ul class="nav nav-tabs-habit mb-4" id="habitTabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#allHabits">
                                <i class="fas fa-list me-2"></i>All Habits
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#featuredHabits">
                                <i class="fas fa-star me-2"></i>Featured Habits
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#categories">
                                <i class="fas fa-tags me-2"></i>Categories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#analytics">
                                <i class="fas fa-chart-bar me-2"></i>Analytics
                            </a>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="habitTabContent">
                        <!-- All Habits Tab -->
                        <div class="tab-pane fade show active" id="allHabits">
                            <div class="habit-management-card">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h4 class="mb-0">All Habits</h4>
                                    <div class="d-flex gap-2">
                                        <input type="text" class="form-control" placeholder="Search habits..." 
                                               style="width: 250px;" id="searchHabits">
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDefaultHabitModal">
                                            <i class="fas fa-plus me-2"></i>Add Default Habit
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Category Filter -->
                                <div class="category-filter">
                                    <?php
                                    $categories = $conn->query("SELECT DISTINCT category FROM habits ORDER BY category");
                                    ?>
                                    <button class="btn btn-sm btn-outline-secondary active" data-category="all">
                                        All Categories
                                    </button>
                                    <?php while ($cat = $categories->fetch_assoc()): ?>
                                        <button class="btn btn-sm btn-outline-secondary" data-category="<?php echo $cat['category']; ?>">
                                            <?php echo $cat['category']; ?>
                                        </button>
                                    <?php endwhile; ?>
                                </div>
                                
                                <!-- Habits List -->
                                <div id="habitsList">
                                    <?php while ($habit = $habits->fetch_assoc()): 
                                        $days_since_creation = max(1, (strtotime(date('Y-m-d')) - strtotime($habit['created_at'])) / (60*60*24));
                                        $success_rate = $habit['active_days'] > 0 ? round(($habit['active_days'] / $days_since_creation) * 100) : 0;
                                        $category_color = getCategoryColor($habit['category']);
                                    ?>
                                        <div class="habit-item-admin" data-category="<?php echo $habit['category']; ?>">
                                            <div class="row align-items-center">
                                                <div class="col-md-8">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <h6 class="mb-0 me-2"><?php echo htmlspecialchars($habit['name']); ?></h6>
                                                        <?php if ($habit['is_featured']): ?>
                                                            <span class="featured-badge">
                                                                <i class="fas fa-star me-1"></i>Featured
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!$habit['is_active']): ?>
                                                            <span class="badge bg-danger ms-2">Inactive</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="mb-2">
                                                        <span class="habit-category-badge" style="background-color: <?php echo $category_color; ?>;">
                                                            <?php echo $habit['category']; ?>
                                                        </span>
                                                        <span class="badge bg-light text-dark">
                                                            <i class="fas fa-<?php echo $habit['frequency'] === 'Daily' ? 'calendar-day' : 'calendar-week'; ?> me-1"></i>
                                                            <?php echo $habit['frequency']; ?>
                                                        </span>
                                                        <?php if ($habit['reminder_time']): ?>
                                                            <span class="badge bg-light text-dark">
                                                                <i class="fas fa-clock me-1"></i>
                                                                <?php echo date('h:i A', strtotime($habit['reminder_time'])); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="mb-2">
                                                        <small class="text-muted">
                                                            <i class="fas fa-user me-1"></i>
                                                            <?php echo htmlspecialchars($habit['user_name']); ?>
                                                            (<?php echo $habit['user_email']; ?>)
                                                        </small>
                                                        <small class="text-muted ms-3">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            Created: <?php echo date('M d, Y', strtotime($habit['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                    
                                                    <div class="completion-rate">
                                                        <span class="text-success">
                                                            <i class="fas fa-chart-line me-1"></i>
                                                            Success Rate: <?php echo $success_rate; ?>%
                                                        </span>
                                                        <span class="text-muted ms-3">
                                                            Completions: <?php echo $habit['completions']; ?>
                                                        </span>
                                                        <span class="text-muted ms-3">
                                                            Active Days: <?php echo $habit['active_days']; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-4 text-end">
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline-info view-habit-details" 
                                                                data-habit-id="<?php echo $habit['id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if ($habit['is_featured']): ?>
                                                            <a href="?action=unfeature&id=<?php echo $habit['id']; ?>" 
                                                               class="btn btn-sm btn-outline-warning"
                                                               onclick="return confirm('Unfeature this habit?')">
                                                                <i class="fas fa-star"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="?action=feature&id=<?php echo $habit['id']; ?>" 
                                                               class="btn btn-sm btn-outline-secondary"
                                                               onclick="return confirm('Feature this habit?')">
                                                                <i class="far fa-star"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="?action=delete&id=<?php echo $habit['id']; ?>" 
                                                           class="btn btn-sm btn-outline-danger"
                                                           onclick="return confirm('Delete this habit? All completion data will be lost!')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                                
                                <!-- Pagination -->
                                <nav aria-label="Habit pagination">
                                    <ul class="pagination justify-content-center mt-4">
                                        <li class="page-item disabled">
                                            <a class="page-link" href="#" tabindex="-1">Previous</a>
                                        </li>
                                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                                        <li class="page-item">
                                            <a class="page-link" href="#">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                        
                        <!-- Featured Habits Tab -->
                        <div class="tab-pane fade" id="featuredHabits">
                            <div class="habit-management-card">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h4 class="mb-0">Featured Habits</h4>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="autoFeature" checked>
                                        <label class="form-check-label" for="autoFeature">
                                            Auto-feature popular habits
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <?php
                                    $featured_habits = $conn->query("
                                        SELECT h.*, u.name as user_name,
                                               (SELECT COUNT(*) FROM habit_completions WHERE habit_id = h.id) as completions
                                        FROM habits h
                                        JOIN users u ON h.user_id = u.id
                                        WHERE h.is_featured = 1
                                        ORDER BY h.created_at DESC
                                        LIMIT 12
                                    ");
                                    
                                    while ($habit = $featured_habits->fetch_assoc()):
                                        $category_color = getCategoryColor($habit['category']);
                                    ?>
                                        <div class="col-md-6 mb-4">
                                            <div class="card h-100">
                                                <div class="card-header d-flex justify-content-between align-items-center"
                                                     style="border-left: 5px solid <?php echo $category_color; ?>;">
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($habit['name']); ?></h6>
                                                    <span class="featured-badge">
                                                        <i class="fas fa-star"></i>
                                                    </span>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <span class="badge" style="background-color: <?php echo $category_color; ?>;">
                                                            <?php echo $habit['category']; ?>
                                                        </span>
                                                        <span class="badge bg-light text-dark">
                                                            <?php echo $habit['frequency']; ?>
                                                        </span>
                                                    </div>
                                                    <p class="card-text small text-muted">
                                                        <?php echo $habit['description'] ?: 'No description provided.'; ?>
                                                    </p>
                                                    <div class="mb-3">
                                                        <small class="text-muted">
                                                            <i class="fas fa-user me-1"></i>
                                                            <?php echo htmlspecialchars($habit['user_name']); ?>
                                                        </small>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="text-success">
                                                            <i class="fas fa-check-circle me-1"></i>
                                                            <?php echo $habit['completions']; ?> completions
                                                        </span>
                                                        <div class="btn-group">
                                                            <a href="?action=unfeature&id=<?php echo $habit['id']; ?>" 
                                                               class="btn btn-sm btn-outline-warning"
                                                               onclick="return confirm('Unfeature this habit?')">
                                                                <i class="fas fa-star"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                    
                                    <?php if ($featured_habits->num_rows == 0): ?>
                                        <div class="col-12 text-center py-5">
                                            <i class="fas fa-star fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No featured habits yet</h5>
                                            <p class="text-muted">Feature habits to highlight them for all users</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Categories Tab -->
                        <div class="tab-pane fade" id="categories">
                            <div class="habit-management-card">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h4 class="mb-0">Habit Categories</h4>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                        <i class="fas fa-plus me-2"></i>Add Category
                                    </button>
                                </div>
                                
                                <div class="row">
                                    <?php
                                    $categories = $conn->query("
                                        SELECT 
                                            category,
                                            COUNT(*) as total_habits,
                                            SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured_habits,
                                            AVG((SELECT COUNT(*) FROM habit_completions WHERE habit_id = h.id)) as avg_completions
                                        FROM habits h
                                        GROUP BY category
                                        ORDER BY total_habits DESC
                                    ");
                                    
                                    while ($cat = $categories->fetch_assoc()):
                                        $color = getCategoryColor($cat['category']);
                                    ?>
                                        <div class="col-md-4 mb-4">
                                            <div class="card h-100">
                                                <div class="card-body text-center">
                                                    <div class="mb-3" style="font-size: 3rem; color: <?php echo $color; ?>">
                                                        <i class="fas fa-<?php echo getCategoryIcon($cat['category']); ?>"></i>
                                                    </div>
                                                    <h5><?php echo $cat['category']; ?></h5>
                                                    <div class="mb-3">
                                                        <span class="badge bg-primary"><?php echo $cat['total_habits']; ?> habits</span>
                                                        <?php if ($cat['featured_habits'] > 0): ?>
                                                            <span class="badge bg-warning"><?php echo $cat['featured_habits']; ?> featured</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="text-muted small mb-3">
                                                        Average completions: <?php echo round($cat['avg_completions']); ?>
                                                    </p>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline-primary edit-category" 
                                                                data-category="<?php echo $cat['category']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger delete-category"
                                                                data-category="<?php echo $cat['category']; ?>"
                                                                <?php echo $cat['total_habits'] > 0 ? 'disabled' : ''; ?>>
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Analytics Tab -->
                        <div class="tab-pane fade" id="analytics">
                            <div class="habit-management-card">
                                <h4 class="mb-4">Habit Analytics</h4>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0">Category Distribution</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="categoryChart" height="200"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0">Completion Trends</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="completionTrendChart" height="200"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0">Top Performing Habits</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Habit</th>
                                                                <th>Category</th>
                                                                <th>Success Rate</th>
                                                                <th>Completions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $top_habits = $conn->query("
                                                                SELECT h.name, h.category,
                                                                       COUNT(hc.id) as completions,
                                                                       ROUND((COUNT(hc.id) / (DATEDIFF(CURDATE(), DATE(h.created_at)) + 1)) * 100, 2) as success_rate
                                                                FROM habits h
                                                                LEFT JOIN habit_completions hc ON h.id = hc.habit_id
                                                                GROUP BY h.id, h.name, h.category, h.created_at
                                                                HAVING success_rate > 0
                                                                ORDER BY success_rate DESC
                                                                LIMIT 5
                                                            ");
                                                            
                                                            while ($habit = $top_habits->fetch_assoc()):
                                                            ?>
                                                                <tr>
                                                                    <td><?php echo htmlspecialchars($habit['name']); ?></td>
                                                                    <td>
                                                                        <span class="badge" style="background-color: <?php echo getCategoryColor($habit['category']); ?>;">
                                                                            <?php echo $habit['category']; ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <div class="progress" style="height: 6px;">
                                                                            <div class="progress-bar bg-success" style="width: <?php echo $habit['success_rate']; ?>%"></div>
                                                                        </div>
                                                                        <small><?php echo $habit['success_rate']; ?>%</small>
                                                                    </td>
                                                                    <td><?php echo $habit['completions']; ?></td>
                                                                </tr>
                                                            <?php endwhile; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0">Habit Statistics</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-6 mb-3">
                                                        <div class="text-center">
                                                            <h3 class="text-primary">
                                                                <?php 
                                                                $avg_completion = $conn->query("
                                                                    SELECT ROUND(AVG(completion_rate), 2) as rate FROM (
                                                                        SELECT h.id,
                                                                               (COUNT(hc.id) / (DATEDIFF(CURDATE(), DATE(h.created_at)) + 1)) * 100 as completion_rate
                                                                        FROM habits h
                                                                        LEFT JOIN habit_completions hc ON h.id = hc.habit_id
                                                                        GROUP BY h.id
                                                                    ) as rates
                                                                ")->fetch_assoc()['rate'];
                                                                echo $avg_completion ?? '0';
                                                                ?>%
                                                            </h3>
                                                            <small class="text-muted">Average Success Rate</small>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <div class="text-center">
                                                            <h3 class="text-success">
                                                                <?php
                                                                $avg_duration = $conn->query("
                                                                    SELECT ROUND(AVG(DATEDIFF(CURDATE(), DATE(created_at))), 1) as days
                                                                    FROM habits
                                                                ")->fetch_assoc()['days'];
                                                                echo $avg_duration ?? '0';
                                                                ?>
                                                            </h3>
                                                            <small class="text-muted">Avg Habit Age (days)</small>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <div class="text-center">
                                                            <h3 class="text-warning">
                                                                <?php
                                                                $active_rate = $conn->query("
                                                                    SELECT ROUND(COUNT(DISTINCT user_id) * 100.0 / 
                                                                          (SELECT COUNT(DISTINCT id) FROM users WHERE is_admin = 0), 2) as rate
                                                                    FROM habits
                                                                    WHERE is_active = 1
                                                                ")->fetch_assoc()['rate'];
                                                                echo $active_rate ?? '0';
                                                                ?>%
                                                            </h3>
                                                            <small class="text-muted">Users with Active Habits</small>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <div class="text-center">
                                                            <h3 class="text-info">
                                                                <?php
                                                                $avg_habits = $conn->query("
                                                                    SELECT ROUND(AVG(habit_count), 1) as avg_habits FROM (
                                                                        SELECT user_id, COUNT(*) as habit_count
                                                                        FROM habits
                                                                        GROUP BY user_id
                                                                    ) as user_habits
                                                                ")->fetch_assoc()['avg_habits'];
                                                                echo $avg_habits ?? '0';
                                                                ?>
                                                            </h3>
                                                            <small class="text-muted">Avg Habits per User</small>
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
            </div>
        </div>
    </div>
    
    <!-- Add Default Habit Modal -->
    <div class="modal fade" id="addDefaultHabitModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Default Habit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="add-default-habit.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Habit Name *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-select" name="category" required>
                                <option value="Health">Health</option>
                                <option value="Fitness">Fitness</option>
                                <option value="Study">Study</option>
                                <option value="Personal">Personal</option>
                                <option value="Work">Work</option>
                                <option value="Social">Social</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Frequency</label>
                            <select class="form-select" name="frequency">
                                <option value="Daily">Daily</option>
                                <option value="Weekly">Weekly</option>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_featured" value="1">
                            <label class="form-check-label">
                                Mark as featured habit
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Habit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-tag me-2"></i>Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Category Name *</label>
                            <input type="text" class="form-control" name="category_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Icon</label>
                            <select class="form-select" name="icon">
                                <option value="fa-heart">❤️ Heart</option>
                                <option value="fa-dumbbell">💪 Fitness</option>
                                <option value="fa-book">📚 Study</option>
                                <option value="fa-briefcase">💼 Work</option>
                                <option value="fa-users">👥 Social</option>
                                <option value="fa-cog">⚙️ Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Color</label>
                            <input type="color" class="form-control-color" value="#4CAF50" style="width: 60px; height: 40px;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" rows="3" placeholder="Describe this category..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Habit Details Modal -->
    <div class="modal fade" id="viewHabitModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Habit Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="habitDetails">
                    <!-- Content will be loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchHabits').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const habits = document.querySelectorAll('.habit-item-admin');
            
            habits.forEach(habit => {
                const text = habit.textContent.toLowerCase();
                habit.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Category filter
        document.querySelectorAll('.category-filter .btn').forEach(button => {
            button.addEventListener('click', function() {
                // Update active button
                document.querySelectorAll('.category-filter .btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                this.classList.add('active');
                
                const category = this.dataset.category;
                const habits = document.querySelectorAll('.habit-item-admin');
                
                habits.forEach(habit => {
                    if (category === 'all' || habit.dataset.category === category) {
                        habit.style.display = '';
                    } else {
                        habit.style.display = 'none';
                    }
                });
            });
        });
        
        // View habit details
        document.querySelectorAll('.view-habit-details').forEach(button => {
            button.addEventListener('click', function() {
                const habitId = this.dataset.habitId;
                
                fetch(`get-habit-details.php?id=${habitId}`)
                    .then(response => response.json())
                    .then(data => {
                        const details = document.getElementById('habitDetails');
                        details.innerHTML = `
                            <div class="row">
                                <div class="col-md-8">
                                    <h4>${data.name}</h4>
                                    <p class="text-muted">${data.description || 'No description provided.'}</p>
                                    
                                    <div class="mb-3">
                                        <span class="badge" style="background-color: ${getCategoryColor(data.category)}">
                                            ${data.category}
                                        </span>
                                        <span class="badge bg-light text-dark ms-2">
                                            <i class="fas fa-${data.frequency === 'Daily' ? 'calendar-day' : 'calendar-week'} me-1"></i>
                                            ${data.frequency}
                                        </span>
                                        ${data.is_featured ? '<span class="badge bg-warning ms-2">Featured</span>' : ''}
                                        ${!data.is_active ? '<span class="badge bg-danger ms-2">Inactive</span>' : ''}
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Created</small>
                                            <p>${new Date(data.created_at).toLocaleDateString()}</p>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">User</small>
                                            <p>${data.user_name} (${data.user_email})</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">Statistics</h6>
                                            <div class="mb-2">
                                                <small class="text-muted">Total Completions</small>
                                                <h5>${data.completions}</h5>
                                            </div>
                                            <div class="mb-2">
                                                <small class="text-muted">Active Days</small>
                                                <h5>${data.active_days}</h5>
                                            </div>
                                            <div class="mb-2">
                                                <small class="text-muted">Success Rate</small>
                                                <div class="progress" style="height: 10px;">
                                                    <div class="progress-bar bg-success" style="width: ${data.success_rate}%"></div>
                                                </div>
                                                <small>${data.success_rate}%</small>
                                            </div>
                                            ${data.reminder_time ? `
                                                <div class="mb-2">
                                                    <small class="text-muted">Reminder Time</small>
                                                    <p>${new Date('1970-01-01T' + data.reminder_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                                                </div>
                                            ` : ''}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h6>Recent Completions</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody id="habitCompletions">
                                            <!-- Completions will be loaded via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        `;
                        
                        // Load completions
                        return fetch(`get-habit-completions.php?id=${habitId}`);
                    })
                    .then(response => response.json())
                    .then(completions => {
                        const completionsBody = document.getElementById('habitCompletions');
                        completionsBody.innerHTML = completions.map(c => `
                            <tr>
                                <td>${new Date(c.completion_date).toLocaleDateString()}</td>
                                <td>${new Date(c.completed_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</td>
                                <td>${c.notes || '-'}</td>
                            </tr>
                        `).join('');
                    });
                
                const modal = new bootstrap.Modal(document.getElementById('viewHabitModal'));
                modal.show();
            });
        });
        
        // Initialize charts
        function initializeCharts() {
            // Category Distribution Chart
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Health', 'Fitness', 'Study', 'Personal', 'Work', 'Social', 'Other'],
                    datasets: [{
                        data: [25, 20, 15, 12, 10, 8, 10],
                        backgroundColor: [
                            '#4CAF50', '#2196F3', '#FF5722', '#9C27B0',
                            '#FFC107', '#00BCD4', '#795548'
                        ]
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
            
            // Completion Trend Chart
            const trendCtx = document.getElementById('completionTrendChart').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Habit Completions',
                        data: [120, 150, 180, 200, 170, 140, 160],
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
                            title: {
                                display: true,
                                text: 'Completions'
                            }
                        }
                    }
                }
            });
        }
        
        // Category color mapping
        function getCategoryColor(category) {
            const colors = {
                'Health': '#4CAF50',
                'Fitness': '#2196F3',
                'Study': '#FF5722',
                'Personal': '#9C27B0',
                'Work': '#FFC107',
                'Social': '#00BCD4',
                'Other': '#795548'
            };
            return colors[category] || '#6c757d';
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', initializeCharts);
    </script>
</body>
</html>

<?php
// Helper functions
function getCategoryColor($category) {
    $colors = [
        'Health' => '#4CAF50',
        'Fitness' => '#2196F3',
        'Study' => '#FF5722',
        'Personal' => '#9C27B0',
        'Work' => '#FFC107',
        'Social' => '#00BCD4',
        'Other' => '#795548'
    ];
    return $colors[$category] ?? '#6c757d';
}

function getCategoryIcon($category) {
    $icons = [
        'Health' => 'heart',
        'Fitness' => 'dumbbell',
        'Study' => 'book',
        'Personal' => 'user',
        'Work' => 'briefcase',
        'Social' => 'users',
        'Other' => 'cog'
    ];
    return $icons[$category] ?? 'circle';
}
?>