<?php
// User sidebar for dashboard pages
?>
<div class="col-lg-2 col-md-3 sidebar d-none d-md-block">
    <div class="sidebar-sticky">
        <div class="text-center mb-4">
            <h3 class="fw-bold"><i class="fas fa-chart-line me-2"></i>HabitTracker</h3>
            <p class="small opacity-75">Build Better Habits</p>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                   href="dashboard.php">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'habits.php' ? 'active' : ''; ?>" 
                   href="habits.php">
                    <i class="fas fa-tasks"></i> Habits
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'calorie-tracker.php' ? 'active' : ''; ?>" 
                   href="calorie-tracker.php">
                    <i class="fas fa-fire"></i> Calorie Tracker
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" 
                   href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" 
                   href="profile.php">
                    <i class="fas fa-user"></i> Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" 
                   href="settings.php">
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
            <?php
            // Calculate today's progress
            require_once '../../config/database.php';
            require_once '../../models/Habit.php';
            
            $habit = new Habit($conn);
            $today_habits = $habit->getTodayHabits($_SESSION['user_id']);
            $completed_habits = array_filter($today_habits, function($h) {
                return $h['completed'];
            });
            $completion_rate = count($today_habits) > 0 ? (count($completed_habits) / count($today_habits)) * 100 : 0;
            ?>
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