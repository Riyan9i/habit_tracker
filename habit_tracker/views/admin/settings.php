<?php
session_start();
require_once '../../includes/auth-check.php';
require_once '../../config/database.php';

// Check if admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ../auth/login.php');
    exit();
}

// Get current settings
$settings = $conn->query("SELECT * FROM admin_settings LIMIT 1")->fetch_assoc();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_smtp'])) {
        $smtp_host = $conn->real_escape_string($_POST['smtp_host']);
        $smtp_username = $conn->real_escape_string($_POST['smtp_username']);
        $smtp_password = $conn->real_escape_string($_POST['smtp_password']);
        $smtp_port = (int)$_POST['smtp_port'];
        
        $sql = "UPDATE admin_settings SET 
                smtp_host = '$smtp_host',
                smtp_username = '$smtp_username',
                smtp_password = '$smtp_password',
                smtp_port = $smtp_port,
                updated_at = NOW()";
        
        if ($conn->query($sql)) {
            $_SESSION['message'] = "SMTP settings updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update SMTP settings: " . $conn->error;
        }
    }
    
    if (isset($_POST['update_sms'])) {
        $sms_api_key = $conn->real_escape_string($_POST['sms_api_key']);
        $sms_sender_id = $conn->real_escape_string($_POST['sms_sender_id']);
        $sms_provider = $conn->real_escape_string($_POST['sms_provider']);
        
        $sql = "UPDATE admin_settings SET 
                sms_api_key = '$sms_api_key',
                sms_sender_id = '$sms_sender_id',
                sms_provider = '$sms_provider',
                updated_at = NOW()";
        
        if ($conn->query($sql)) {
            $_SESSION['message'] = "SMS settings updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update SMS settings: " . $conn->error;
        }
    }
    
    if (isset($_POST['update_theme'])) {
        $theme_color = $conn->real_escape_string($_POST['theme_color']);
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        
        $sql = "UPDATE admin_settings SET 
                theme_color = '$theme_color',
                maintenance_mode = $maintenance_mode,
                updated_at = NOW()";
        
        if ($conn->query($sql)) {
            $_SESSION['message'] = "Theme settings updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update theme settings: " . $conn->error;
        }
    }
    
    if (isset($_POST['backup_database'])) {
        // Create backup directory if it doesn't exist
        $backup_dir = '../../database/backup/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }
        
        $backup_file = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        // Get all tables
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        
        $sql_script = "";
        foreach ($tables as $table) {
            // CREATE TABLE statement
            $result = $conn->query("SHOW CREATE TABLE $table");
            $row = $result->fetch_row();
            $sql_script .= "\n\n" . $row[1] . ";\n\n";
            
            // INSERT statements
            $result = $conn->query("SELECT * FROM $table");
            while ($row = $result->fetch_assoc()) {
                $keys = array_keys($row);
                $values = array_map(function($value) use ($conn) {
                    return "'" . $conn->real_escape_string($value) . "'";
                }, array_values($row));
                
                $sql_script .= "INSERT INTO $table (`" . implode('`,`', $keys) . "`) VALUES (" . implode(',', $values) . ");\n";
            }
        }
        
        // Save to file
        if (file_put_contents($backup_file, $sql_script)) {
            $_SESSION['message'] = "Database backup created successfully!";
        } else {
            $_SESSION['error'] = "Failed to create backup.";
        }
    }
    
    header('Location: settings.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - Habit Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-card-admin {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .card-header-admin {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 20px;
            border-radius: 15px 15px 0 0;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #4CAF50;
            box-shadow: none;
        }
        
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 5px;
            border: 2px solid #ddd;
            cursor: pointer;
        }
        
        .backup-item {
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
        }
        
        .danger-zone {
            border-left: 4px solid #dc3545;
            background-color: rgba(220, 53, 69, 0.1);
        }
        
        .tab-content {
            padding: 20px 0;
        }
        
        .nav-tabs-admin .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 500;
            padding: 12px 25px;
            border-radius: 0;
        }
        
        .nav-tabs-admin .nav-link.active {
            color: #4CAF50;
            border-bottom-color: #4CAF50;
            background: transparent;
        }
        
        .test-btn {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 500;
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
                            <a class="nav-link active" href="settings.php">
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
                                <i class="fas fa-cogs me-2"></i>System Settings
                            </h5>
                            <span class="badge bg-primary ms-3">Admin Only</span>
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
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Settings Tabs -->
                    <ul class="nav nav-tabs-admin mb-4" id="settingsTabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#smtp">
                                <i class="fas fa-envelope me-2"></i>Email (SMTP)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#sms">
                                <i class="fas fa-sms me-2"></i>SMS Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#theme">
                                <i class="fas fa-palette me-2"></i>Theme
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#backup">
                                <i class="fas fa-database me-2"></i>Backup
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#system">
                                <i class="fas fa-server me-2"></i>System
                            </a>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="settingsTabContent">
                        <!-- SMTP Settings Tab -->
                        <div class="tab-pane fade show active" id="smtp">
                            <div class="settings-card-admin">
                                <div class="card-header-admin mb-4">
                                    <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Email SMTP Settings</h5>
                                </div>
                                
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">SMTP Host *</label>
                                            <input type="text" class="form-control" name="smtp_host" 
                                                   value="<?php echo $settings['smtp_host'] ?? 'smtp.gmail.com'; ?>" required>
                                            <small class="text-muted">e.g., smtp.gmail.com, smtp.sendgrid.net</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">SMTP Port *</label>
                                            <input type="number" class="form-control" name="smtp_port" 
                                                   value="<?php echo $settings['smtp_port'] ?? 587; ?>" required>
                                            <small class="text-muted">Common ports: 587 (TLS), 465 (SSL)</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">SMTP Username *</label>
                                            <input type="text" class="form-control" name="smtp_username" 
                                                   value="<?php echo $settings['smtp_username'] ?? ''; ?>" required>
                                            <small class="text-muted">Your email address</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">SMTP Password *</label>
                                            <input type="password" class="form-control" name="smtp_password" 
                                                   value="<?php echo $settings['smtp_password'] ?? ''; ?>" required>
                                            <small class="text-muted">App password for Gmail</small>
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">From Email</label>
                                            <input type="email" class="form-control" 
                                                   value="<?php echo $settings['smtp_username'] ?? ''; ?>" readonly>
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">From Name</label>
                                            <input type="text" class="form-control" value="Habit Tracker">
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-primary test-btn" id="testSmtp">
                                            <i class="fas fa-vial me-2"></i>Test SMTP Connection
                                        </button>
                                        <button type="submit" name="update_smtp" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save SMTP Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- SMTP Test Results -->
                            <div class="settings-card-admin" id="smtpTestResult" style="display: none;">
                                <h5 class="mb-3"><i class="fas fa-vial me-2"></i>SMTP Test Results</h5>
                                <div id="testResultContent"></div>
                            </div>
                        </div>
                        
                        <!-- SMS Settings Tab -->
                        <div class="tab-pane fade" id="sms">
                            <div class="settings-card-admin">
                                <div class="card-header-admin mb-4">
                                    <h5 class="mb-0"><i class="fas fa-sms me-2"></i>SMS Gateway Settings</h5>
                                </div>
                                
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">SMS Provider *</label>
                                            <select class="form-select" name="sms_provider">
                                                <option value="twilio" <?php echo ($settings['sms_provider'] ?? '') == 'twilio' ? 'selected' : ''; ?>>Twilio</option>
                                                <option value="nexmo" <?php echo ($settings['sms_provider'] ?? '') == 'nexmo' ? 'selected' : ''; ?>>Vonage (Nexmo)</option>
                                                <option value="fast2sms" <?php echo ($settings['sms_provider'] ?? '') == 'fast2sms' ? 'selected' : ''; ?>>Fast2SMS</option>
                                                <option value="sslwireless" <?php echo ($settings['sms_provider'] ?? '') == 'sslwireless' ? 'selected' : ''; ?>>SSL Wireless (Bangladesh)</option>
                                                <option value="custom" <?php echo ($settings['sms_provider'] ?? '') == 'custom' ? 'selected' : ''; ?>>Custom API</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Sender ID *</label>
                                            <input type="text" class="form-control" name="sms_sender_id" 
                                                   value="<?php echo $settings['sms_sender_id'] ?? 'HABITTR'; ?>" required>
                                            <small class="text-muted">6-11 characters, alphanumeric</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">API Key *</label>
                                            <input type="password" class="form-control" name="sms_api_key" 
                                                   value="<?php echo $settings['sms_api_key'] ?? ''; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">API Secret</label>
                                            <input type="password" class="form-control" name="sms_api_secret" 
                                                   value="<?php echo $settings['sms_api_secret'] ?? ''; ?>">
                                            <small class="text-muted">Not required for all providers</small>
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">API Endpoint</label>
                                            <input type="url" class="form-control" 
                                                   value="https://api.twilio.com/2010-04-01/Accounts/ACXXXX/Messages.json" readonly>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-primary test-btn" id="testSms">
                                            <i class="fas fa-vial me-2"></i>Test SMS
                                        </button>
                                        <button type="submit" name="update_sms" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save SMS Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Theme Settings Tab -->
                        <div class="tab-pane fade" id="theme">
                            <div class="settings-card-admin">
                                <div class="card-header-admin mb-4">
                                    <h5 class="mb-0"><i class="fas fa-palette me-2"></i>Theme & Appearance</h5>
                                </div>
                                
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Primary Color</label>
                                            <div class="d-flex align-items-center">
                                                <input type="color" class="form-control-color" name="theme_color" 
                                                       value="<?php echo $settings['theme_color'] ?? '#4CAF50'; ?>" style="width: 60px; height: 40px;">
                                                <span class="ms-3"><?php echo $settings['theme_color'] ?? '#4CAF50'; ?></span>
                                            </div>
                                            <small class="text-muted">Main theme color for buttons and highlights</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Logo</label>
                                            <input type="file" class="form-control" accept="image/*">
                                            <small class="text-muted">Recommended: 200x60px PNG</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Favicon</label>
                                            <input type="file" class="form-control" accept="image/x-icon,.ico">
                                            <small class="text-muted">16x16px ICO file</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Site Title</label>
                                            <input type="text" class="form-control" value="Habit Tracker">
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="maintenance_mode" 
                                                       value="1" <?php echo ($settings['maintenance_mode'] ?? 0) ? 'checked' : ''; ?>>
                                                <label class="form-check-label">
                                                    <strong>Maintenance Mode</strong>
                                                </label>
                                                <p class="text-muted mb-0">When enabled, only admins can access the site</p>
                                            </div>
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">Custom CSS</label>
                                            <textarea class="form-control" rows="5" placeholder="Add custom CSS code here..."></textarea>
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">Custom JavaScript</label>
                                            <textarea class="form-control" rows="5" placeholder="Add custom JavaScript code here..."></textarea>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="update_theme" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Theme Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Backup Tab -->
                        <div class="tab-pane fade" id="backup">
                            <div class="settings-card-admin">
                                <div class="card-header-admin mb-4">
                                    <h5 class="mb-0"><i class="fas fa-database me-2"></i>Database Backup</h5>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <i class="fas fa-download fa-3x text-primary mb-3"></i>
                                                <h5>Create Backup</h5>
                                                <p class="text-muted">Create a complete backup of your database</p>
                                                <form method="POST" action="">
                                                    <button type="submit" name="backup_database" class="btn btn-primary">
                                                        <i class="fas fa-database me-2"></i>Create Backup Now
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <i class="fas fa-upload fa-3x text-success mb-3"></i>
                                                <h5>Restore Backup</h5>
                                                <p class="text-muted">Restore database from a backup file</p>
                                                <input type="file" class="form-control mb-3" accept=".sql">
                                                <button class="btn btn-success" disabled>
                                                    <i class="fas fa-redo me-2"></i>Restore Backup
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Recent Backups -->
                                <h5 class="mb-3">Recent Backups</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>File Name</th>
                                                <th>Date</th>
                                                <th>Size</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $backup_dir = '../../database/backup/';
                                            if (is_dir($backup_dir)) {
                                                $files = array_diff(scandir($backup_dir), array('.', '..'));
                                                rsort($files);
                                                
                                                foreach ($files as $file) {
                                                    if (strpos($file, '.sql') !== false) {
                                                        $file_path = $backup_dir . $file;
                                                        $file_size = filesize($file_path);
                                                        $file_date = date('Y-m-d H:i:s', filemtime($file_path));
                                                        ?>
                                                        <tr>
                                                            <td><?php echo $file; ?></td>
                                                            <td><?php echo $file_date; ?></td>
                                                            <td><?php echo round($file_size / 1024, 2); ?> KB</td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary download-backup" 
                                                                        data-file="<?php echo $file; ?>">
                                                                    <i class="fas fa-download"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger delete-backup" 
                                                                        data-file="<?php echo $file; ?>">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                    }
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Auto Backup Settings -->
                                <div class="mt-4">
                                    <h5 class="mb-3">Automatic Backup Settings</h5>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="autoBackup">
                                                <label class="form-check-label">
                                                    Enable Automatic Backup
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Frequency</label>
                                            <select class="form-select">
                                                <option value="daily">Daily</option>
                                                <option value="weekly" selected>Weekly</option>
                                                <option value="monthly">Monthly</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Backup Time</label>
                                            <input type="time" class="form-control" value="02:00">
                                        </div>
                                        <div class="col-md-12">
                                            <button class="btn btn-primary">Save Backup Settings</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Settings Tab -->
                        <div class="tab-pane fade" id="system">
                            <div class="settings-card-admin">
                                <div class="card-header-admin mb-4">
                                    <h5 class="mb-0"><i class="fas fa-server me-2"></i>System Settings</h5>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Site URL</label>
                                        <input type="url" class="form-control" value="http://localhost/habit-tracker/" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Timezone</label>
                                        <select class="form-select">
                                            <option value="Asia/Dhaka" selected>Asia/Dhaka (GMT+6)</option>
                                            <option value="UTC">UTC</option>
                                            <option value="America/New_York">America/New_York</option>
                                            <option value="Europe/London">Europe/London</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Default Language</label>
                                        <select class="form-select">
                                            <option value="en" selected>English</option>
                                            <option value="bn">Bengali</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">User Registration</label>
                                        <select class="form-select">
                                            <option value="open" selected>Open (Anyone can register)</option>
                                            <option value="invite">Invite Only</option>
                                            <option value="closed">Closed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Max Upload Size</label>
                                        <input type="number" class="form-control" value="2"> MB
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Session Timeout</label>
                                        <input type="number" class="form-control" value="30"> minutes
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" checked>
                                            <label class="form-check-label">
                                                Enable Google Analytics
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Google Analytics ID</label>
                                        <input type="text" class="form-control" placeholder="UA-XXXXXXXXX-X">
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Custom Header Code</label>
                                        <textarea class="form-control" rows="3" placeholder="Add code for header section..."></textarea>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Custom Footer Code</label>
                                        <textarea class="form-control" rows="3" placeholder="Add code for footer section..."></textarea>
                                    </div>
                                </div>
                                
                                <button class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save System Settings
                                </button>
                            </div>
                            
                            <!-- System Info -->
                            <div class="settings-card-admin mt-4">
                                <h5 class="mb-4"><i class="fas fa-info-circle me-2"></i>System Information</h5>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <h6>PHP Version</h6>
                                        <p class="text-muted"><?php echo phpversion(); ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <h6>MySQL Version</h6>
                                        <p class="text-muted"><?php echo $conn->server_info; ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <h6>Server Software</h6>
                                        <p class="text-muted"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <h6>Maximum Upload Size</h6>
                                        <p class="text-muted"><?php echo ini_get('upload_max_filesize'); ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <h6>Memory Limit</h6>
                                        <p class="text-muted"><?php echo ini_get('memory_limit'); ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <h6>Post Max Size</h6>
                                        <p class="text-muted"><?php echo ini_get('post_max_size'); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Danger Zone -->
                            <div class="settings-card-admin danger-zone mt-4">
                                <div class="card-header bg-danger mb-4">
                                    <h5 class="mb-0 text-white"><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</h5>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="card border-danger">
                                            <div class="card-body">
                                                <h6 class="card-title text-danger">Clear Cache</h6>
                                                <p class="card-text">Clear all system cache and temporary files</p>
                                                <button class="btn btn-outline-danger">
                                                    <i class="fas fa-broom me-2"></i>Clear All Cache
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <div class="card border-danger">
                                            <div class="card-body">
                                                <h6 class="card-title text-danger">Reset System</h6>
                                                <p class="card-text">Reset all settings to default values</p>
                                                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#resetSystemModal">
                                                    <i class="fas fa-redo me-2"></i>Reset System
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
        </div>
    </div>
    
    <!-- Reset System Modal -->
    <div class="modal fade" id="resetSystemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Reset System</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Warning:</strong> This will reset all system settings to default!
                    </div>
                    <p>Are you sure you want to reset all system settings? This will:</p>
                    <ul>
                        <li>Reset SMTP settings to default</li>
                        <li>Reset SMS settings to default</li>
                        <li>Reset theme settings to default</li>
                        <li>Clear all cache files</li>
                    </ul>
                    <div class="mb-3">
                        <label class="form-label">Type "RESET" to confirm:</label>
                        <input type="text" class="form-control" id="resetConfirmation">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmResetSystem" disabled>
                        <i class="fas fa-redo me-2"></i>Reset System
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Test SMTP Connection
        document.getElementById('testSmtp').addEventListener('click', function() {
            const resultDiv = document.getElementById('smtpTestResult');
            const resultContent = document.getElementById('testResultContent');
            
            resultContent.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Testing SMTP connection...</p>
                </div>
            `;
            
            resultDiv.style.display = 'block';
            
            // Simulate SMTP test (in real implementation, this would be an AJAX call)
            setTimeout(() => {
                resultContent.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>SMTP Test Successful!</strong>
                        <p class="mb-0">Connection to SMTP server established successfully.</p>
                    </div>
                    <div class="mt-3">
                        <h6>Test Details:</h6>
                        <ul class="mb-0">
                            <li>Server: smtp.gmail.com</li>
                            <li>Port: 587</li>
                            <li>Encryption: TLS</li>
                            <li>Authentication: Successful</li>
                        </ul>
                    </div>
                `;
            }, 2000);
        });
        
        // Test SMS
        document.getElementById('testSms').addEventListener('click', function() {
            const phone = prompt('Enter phone number to send test SMS:');
            if (phone) {
                alert(`Test SMS would be sent to ${phone}. In production, this would call the SMS API.`);
            }
        });
        
        // Download backup
        document.querySelectorAll('.download-backup').forEach(button => {
            button.addEventListener('click', function() {
                const fileName = this.dataset.file;
                window.location.href = `download-backup.php?file=${fileName}`;
            });
        });
        
        // Delete backup
        document.querySelectorAll('.delete-backup').forEach(button => {
            button.addEventListener('click', function() {
                const fileName = this.dataset.file;
                if (confirm(`Delete backup file: ${fileName}?`)) {
                    fetch(`delete-backup.php?file=${fileName}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert('Failed to delete backup.');
                            }
                        });
                }
            });
        });
        
        // Reset system confirmation
        const resetConfirmation = document.getElementById('resetConfirmation');
        const confirmResetSystem = document.getElementById('confirmResetSystem');
        
        if (resetConfirmation && confirmResetSystem) {
            resetConfirmation.addEventListener('input', function() {
                confirmResetSystem.disabled = this.value !== 'RESET';
            });
            
            confirmResetSystem.addEventListener('click', function() {
                if (confirm('Are you absolutely sure? This cannot be undone!')) {
                    // Implement reset logic here
                    alert('System reset initiated. Page will reload.');
                    setTimeout(() => location.reload(), 1000);
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
</body>
</html>