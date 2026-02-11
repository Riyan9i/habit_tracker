<?php
session_start();
require_once '../../includes/auth-check.php';
require_once '../../config/database.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_dark_mode'])) {
        $dark_mode = $_POST['dark_mode'] == '1' ? 1 : 0;
        $conn->query("UPDATE users SET dark_mode = $dark_mode WHERE id = $user_id");
        $_SESSION['dark_mode'] = $dark_mode;
        $_SESSION['message'] = "Theme updated successfully!";
        header('Location: settings.php');
        exit();
    }
    
    if (isset($_POST['update_language'])) {
        $language = $_POST['language'];
        $conn->query("UPDATE users SET language = '$language' WHERE id = $user_id");
        $_SESSION['language'] = $language;
        $_SESSION['message'] = "Language updated successfully!";
        header('Location: settings.php');
        exit();
    }
    
    if (isset($_POST['delete_account'])) {
        // Delete account logic here
        // You might want to add confirmation and backup first
        $conn->query("DELETE FROM users WHERE id = $user_id");
        session_destroy();
        header('Location: ../../index.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en" <?php echo $_SESSION['dark_mode'] ? 'data-bs-theme="dark"' : ''; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Habit Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        [data-bs-theme="dark"] .settings-card {
            background-color: #2d2d2d;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .settings-card .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 20px;
        }
        
        .settings-item {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            transition: background-color 0.3s;
        }
        
        [data-bs-theme="dark"] .settings-item {
            border-bottom-color: #404040;
        }
        
        .settings-item:hover {
            background-color: #f8f9fa;
        }
        
        [data-bs-theme="dark"] .settings-item:hover {
            background-color: #3d3d3d;
        }
        
        .settings-item:last-child {
            border-bottom: none;
        }
        
        .form-switch .form-check-input {
            width: 3em;
            height: 1.5em;
        }
        
        .danger-zone {
            border-left: 4px solid #dc3545;
            background-color: rgba(220, 53, 69, 0.1);
        }
        
        [data-bs-theme="dark"] .danger-zone {
            background-color: rgba(220, 53, 69, 0.2);
        }
        
        .theme-preview {
            width: 60px;
            height: 40px;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .theme-preview:hover {
            transform: scale(1.1);
        }
        
        .theme-preview.active {
            border-color: #4CAF50;
        }
        
        .theme-light {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }
        
        .theme-dark {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
        }
        
        .theme-blue {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
        }
        
        .theme-green {
            background: linear-gradient(135deg, #4CAF50 0%, #388E3C 100%);
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
                        <i class="fas fa-cog me-2"></i>Settings
                    </h1>
                </div>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- App Settings -->
                <div class="settings-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Application Settings</h5>
                    </div>
                    <div class="card-body p-0">
                        <!-- Theme Settings -->
                        <div class="settings-item">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1">
                                        <i class="fas fa-palette me-2"></i>Theme Settings
                                    </h6>
                                    <p class="text-muted mb-0">Choose your preferred theme</p>
                                </div>
                                <div class="col-md-4">
                                    <form method="POST" action="" class="text-end">
                                        <div class="d-flex justify-content-end gap-3 mb-3">
                                            <div class="theme-preview theme-light <?php echo !$_SESSION['dark_mode'] ? 'active' : ''; ?>" 
                                                 onclick="setTheme('light')"></div>
                                            <div class="theme-preview theme-dark <?php echo $_SESSION['dark_mode'] ? 'active' : ''; ?>" 
                                                 onclick="setTheme('dark')"></div>
                                            <div class="theme-preview theme-blue" onclick="setTheme('blue')"></div>
                                            <div class="theme-preview theme-green" onclick="setTheme('green')"></div>
                                        </div>
                                        <input type="hidden" name="dark_mode" id="darkModeInput" value="<?php echo $_SESSION['dark_mode']; ?>">
                                        <input type="hidden" name="toggle_dark_mode" value="1">
                                        <button type="submit" class="btn btn-sm btn-primary">Apply Theme</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Language Settings -->
                        <div class="settings-item">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1">
                                        <i class="fas fa-language me-2"></i>Language
                                    </h6>
                                    <p class="text-muted mb-0">Select your preferred language</p>
                                </div>
                                <div class="col-md-4">
                                    <form method="POST" action="">
                                        <div class="input-group">
                                            <select class="form-select" name="language">
                                                <option value="en" selected>English</option>
                                                <option value="bn">Bengali</option>
                                                <option value="es">Spanish</option>
                                                <option value="fr">French</option>
                                                <option value="de">German</option>
                                            </select>
                                            <button type="submit" name="update_language" class="btn btn-primary">
                                                <i class="fas fa-save"></i>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Date Format -->
                        <div class="settings-item">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1">
                                        <i class="fas fa-calendar-alt me-2"></i>Date Format
                                    </h6>
                                    <p class="text-muted mb-0">Choose how dates are displayed</p>
                                </div>
                                <div class="col-md-4">
                                    <select class="form-select">
                                        <option value="MM/DD/YYYY">MM/DD/YYYY</option>
                                        <option value="DD/MM/YYYY">DD/MM/YYYY</option>
                                        <option value="YYYY-MM-DD">YYYY-MM-DD</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Time Format -->
                        <div class="settings-item">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1">
                                        <i class="fas fa-clock me-2"></i>Time Format
                                    </h6>
                                    <p class="text-muted mb-0">Choose 12-hour or 24-hour format</p>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="timeFormatSwitch" checked>
                                        <label class="form-check-label" for="timeFormatSwitch">
                                            <span class="switch-label">12-hour</span>
                                            <span class="switch-label d-none">24-hour</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Notification Settings -->
                <div class="settings-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Settings</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="settings-item">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
                                <label class="form-check-label" for="emailNotifications">
                                    <h6 class="mb-1">Email Notifications</h6>
                                    <p class="text-muted mb-0">Receive notifications via email</p>
                                </label>
                            </div>
                        </div>
                        
                        <div class="settings-item">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="smsNotifications">
                                <label class="form-check-label" for="smsNotifications">
                                    <h6 class="mb-1">SMS Notifications</h6>
                                    <p class="text-muted mb-0">Receive notifications via SMS</p>
                                </label>
                            </div>
                        </div>
                        
                        <div class="settings-item">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="pushNotifications" checked>
                                <label class="form-check-label" for="pushNotifications">
                                    <h6 class="mb-1">Push Notifications</h6>
                                    <p class="text-muted mb-0">Receive in-app notifications</p>
                                </label>
                            </div>
                        </div>
                        
                        <div class="settings-item">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="mb-1">Notification Sound</h6>
                                    <p class="text-muted mb-0">Choose notification sound</p>
                                </div>
                                <div class="col-md-4">
                                    <select class="form-select">
                                        <option value="default" selected>Default</option>
                                        <option value="chime">Chime</option>
                                        <option value="bell">Bell</option>
                                        <option value="alert">Alert</option>
                                        <option value="none">None</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Privacy Settings -->
                <div class="settings-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Privacy & Security</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="settings-item">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="profileVisibility" checked>
                                <label class="form-check-label" for="profileVisibility">
                                    <h6 class="mb-1">Public Profile</h6>
                                    <p class="text-muted mb-0">Allow others to view your profile</p>
                                </label>
                            </div>
                        </div>
                        
                        <div class="settings-item">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="activityVisibility">
                                <label class="form-check-label" for="activityVisibility">
                                    <h6 class="mb-1">Activity Sharing</h6>
                                    <p class="text-muted mb-0">Share your activity with friends</p>
                                </label>
                            </div>
                        </div>
                        
                        <div class="settings-item">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="dataCollection" checked>
                                <label class="form-check-label" for="dataCollection">
                                    <h6 class="mb-1">Data Collection</h6>
                                    <p class="text-muted mb-0">Allow anonymous data collection for improvement</p>
                                </label>
                            </div>
                        </div>
                        
                        <div class="settings-item">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="mb-1">Auto Logout</h6>
                                    <p class="text-muted mb-0">Automatically logout after inactivity</p>
                                </div>
                                <div class="col-md-4">
                                    <select class="form-select">
                                        <option value="15">15 minutes</option>
                                        <option value="30" selected>30 minutes</option>
                                        <option value="60">1 hour</option>
                                        <option value="0">Never</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Data Management -->
                <div class="settings-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-database me-2"></i>Data Management</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="settings-item">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1">Export Data</h6>
                                    <p class="text-muted mb-0">Download all your data in CSV format</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button class="btn btn-outline-primary">
                                        <i class="fas fa-download me-2"></i>Export All Data
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-item">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1">Clear Cache</h6>
                                    <p class="text-muted mb-0">Clear temporary files and cache</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button class="btn btn-outline-secondary">
                                        <i class="fas fa-broom me-2"></i>Clear Cache
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-item">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1">Reset Data</h6>
                                    <p class="text-muted mb-0">Reset all habits and progress (keeps account)</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetDataModal">
                                        <i class="fas fa-redo me-2"></i>Reset Data
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Danger Zone -->
                <div class="settings-card danger-zone">
                    <div class="card-header bg-danger">
                        <h5 class="mb-0 text-white"><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="settings-item">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1 text-danger">Delete Account</h6>
                                    <p class="text-muted mb-0">Permanently delete your account and all data</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                        <i class="fas fa-trash me-2"></i>Delete Account
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- About Section -->
                <div class="settings-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>About</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h3><i class="fas fa-chart-line text-primary"></i> HabitTracker</h3>
                            <p class="text-muted">Version 2.0.0</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6>Developed By</h6>
                                <p class="text-muted">Your Company Name</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6>Contact</h6>
                                <p class="text-muted">support@habittracker.com</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6>Website</h6>
                                <p class="text-muted">www.habittracker.com</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6>Terms & Conditions</h6>
                                <a href="#" class="text-decoration-none">View Terms</a>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button class="btn btn-outline-secondary">
                                <i class="fas fa-star me-2"></i>Rate This App
                            </button>
                            <button class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-share-alt me-2"></i>Share
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Reset Data Modal -->
    <div class="modal fade" id="resetDataModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Reset Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Warning:</strong> This will delete all your habits, progress, and calorie data.
                    </div>
                    <p>Are you sure you want to reset all your data? This action cannot be undone.</p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmReset">
                        <label class="form-check-label" for="confirmReset">
                            I understand this will delete all my data
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="confirmResetBtn" disabled>
                        <i class="fas fa-redo me-2"></i>Reset All Data
                    </button>
                </div>
            </div>
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
                        <strong>Danger:</strong> This will permanently delete your account!
                    </div>
                    <p>Are you absolutely sure? This will:</p>
                    <ul>
                        <li>Delete your account permanently</li>
                        <li>Remove all your habits and progress</li>
                        <li>Delete all your calorie data</li>
                        <li>Remove your profile information</li>
                    </ul>
                    <div class="mb-3">
                        <label class="form-label">Type "DELETE" to confirm:</label>
                        <input type="text" class="form-control" id="deleteConfirmation">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="" class="d-inline">
                        <input type="hidden" name="delete_account" value="1">
                        <button type="submit" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                            <i class="fas fa-trash me-2"></i>Delete Account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Theme selection
        function setTheme(theme) {
            const previews = document.querySelectorAll('.theme-preview');
            previews.forEach(p => p.classList.remove('active'));
            
            const selected = document.querySelector(`.theme-${theme}`);
            selected.classList.add('active');
            
            // Set dark mode input
            document.getElementById('darkModeInput').value = theme === 'dark' ? '1' : '0';
            
            // Preview theme change
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            } else {
                document.documentElement.removeAttribute('data-bs-theme');
            }
        }
        
        // Time format switch
        const timeFormatSwitch = document.getElementById('timeFormatSwitch');
        const switchLabels = timeFormatSwitch.parentElement.querySelectorAll('.switch-label');
        
        timeFormatSwitch.addEventListener('change', function() {
            switchLabels.forEach(label => label.classList.toggle('d-none'));
        });
        
        // Reset data confirmation
        const confirmReset = document.getElementById('confirmReset');
        const confirmResetBtn = document.getElementById('confirmResetBtn');
        
        if (confirmReset && confirmResetBtn) {
            confirmReset.addEventListener('change', function() {
                confirmResetBtn.disabled = !this.checked;
            });
            
            confirmResetBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to reset all data?')) {
                    // Implement reset logic here
                    alert('Data reset successful!');
                    window.location.reload();
                }
            });
        }
        
        // Delete account confirmation
        const deleteConfirmation = document.getElementById('deleteConfirmation');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        
        if (deleteConfirmation && confirmDeleteBtn) {
            deleteConfirmation.addEventListener('input', function() {
                confirmDeleteBtn.disabled = this.value !== 'DELETE';
            });
        }
        
        // Toggle switches
        document.querySelectorAll('.form-check-input').forEach(switchEl => {
            switchEl.addEventListener('change', function() {
                const settingName = this.id;
                const value = this.checked;
                
                // Save setting via AJAX
                fetch('save-setting.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        setting: settingName,
                        value: value
                    })
                });
            });
        });
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>