<?php
session_start();
require_once '../../includes/auth-check.php';
require_once '../../config/database.php';
require_once '../../config/mailer.php';

// Check if admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ../auth/login.php');
    exit();
}

// Handle notification sending
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_notification'])) {
        $type = $_POST['type'];
        $subject = $conn->real_escape_string($_POST['subject']);
        $message = $conn->real_escape_string($_POST['message']);
        $recipients = $_POST['recipients'];
        
        // Get users based on selection
        if ($recipients === 'all') {
            $users_result = $conn->query("SELECT id, name, email, phone FROM users WHERE is_admin = 0 AND is_active = 1");
        } elseif ($recipients === 'active') {
            $users_result = $conn->query("SELECT DISTINCT u.id, u.name, u.email, u.phone 
                                         FROM users u 
                                         JOIN habit_completions hc ON u.id = hc.user_id 
                                         WHERE u.is_admin = 0 AND u.is_active = 1 
                                         AND hc.completion_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        } elseif ($recipients === 'inactive') {
            $users_result = $conn->query("SELECT u.id, u.name, u.email, u.phone 
                                         FROM users u 
                                         LEFT JOIN habit_completions hc ON u.id = hc.user_id 
                                         AND hc.completion_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                                         WHERE u.is_admin = 0 AND u.is_active = 1 
                                         AND hc.id IS NULL");
        }
        
        $sent_count = 0;
        $failed_count = 0;
        
        while ($user = $users_result->fetch_assoc()) {
            if ($type === 'email' || $type === 'both') {
                try {
                    $mailer = new Mailer();
                    $mailer->mail->addAddress($user['email'], $user['name']);
                    $mailer->mail->Subject = $subject;
                    $mailer->mail->Body = nl2br($message);
                    $mailer->mail->AltBody = strip_tags($message);
                    
                    if ($mailer->mail->send()) {
                        $sent_count++;
                        // Log the notification
                        $conn->query("INSERT INTO notifications (user_id, type, title, message, sent_at, status) 
                                     VALUES ({$user['id']}, 'Email', '$subject', '$message', NOW(), 'Sent')");
                    } else {
                        $failed_count++;
                        $conn->query("INSERT INTO notifications (user_id, type, title, message, sent_at, status) 
                                     VALUES ({$user['id']}, 'Email', '$subject', '$message', NOW(), 'Failed')");
                    }
                } catch (Exception $e) {
                    $failed_count++;
                    error_log("Email sending failed: " . $e->getMessage());
                }
            }
            
            if (($type === 'sms' || $type === 'both') && !empty($user['phone'])) {
                // Implement SMS sending logic here
                // This would require an SMS API integration
                $conn->query("INSERT INTO notifications (user_id, type, title, message, sent_at, status) 
                             VALUES ({$user['id']}, 'SMS', '$subject', '$message', NOW(), 'Pending')");
            }
        }
        
        $_SESSION['message'] = "Notification sent! Success: $sent_count, Failed: $failed_count";
        header('Location: notifications.php');
        exit();
    }
}

// Get recent notifications
$notifications = $conn->query("SELECT * FROM notifications ORDER BY sent_at DESC LIMIT 50");

// Get notification stats
$stats = [
    'total_sent' => $conn->query("SELECT COUNT(*) as count FROM notifications WHERE status = 'Sent'")->fetch_assoc()['count'],
    'total_failed' => $conn->query("SELECT COUNT(*) as count FROM notifications WHERE status = 'Failed'")->fetch_assoc()['count'],
    'today_sent' => $conn->query("SELECT COUNT(*) as count FROM notifications WHERE DATE(sent_at) = CURDATE() AND status = 'Sent'")->fetch_assoc()['count'],
    'email_count' => $conn->query("SELECT COUNT(*) as count FROM notifications WHERE type = 'Email'")->fetch_assoc()['count'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .notification-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .stat-badge-notification {
            font-size: 0.9rem;
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .notification-item {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #4CAF50;
            background-color: #f8f9fa;
        }
        
        .notification-item.email { border-left-color: #4CAF50; }
        .notification-item.sms { border-left-color: #2196F3; }
        .notification-item.failed { border-left-color: #dc3545; }
        
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #4CAF50;
            box-shadow: none;
        }
        
        .tab-content {
            padding: 20px 0;
        }
        
        .nav-tabs-notification .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 500;
            padding: 12px 25px;
            border-radius: 0;
        }
        
        .nav-tabs-notification .nav-link.active {
            color: #4CAF50;
            border-bottom-color: #4CAF50;
            background: transparent;
        }
        
        .recipient-badge {
            background-color: #e3f2fd;
            color: #2196F3;
            padding: 5px 15px;
            border-radius: 20px;
            margin-right: 5px;
            margin-bottom: 5px;
            display: inline-block;
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
                            <a class="nav-link active" href="notifications.php">
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
                                <i class="fas fa-bell me-2"></i>Notification Management
                            </h5>
                            <span class="badge bg-primary ms-3">Total: <?php echo $stats['total_sent']; ?> sent</span>
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
                            <div class="notification-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted fw-semibold">Total Sent</h6>
                                        <h2 class="fw-bold"><?php echo $stats['total_sent']; ?></h2>
                                        <span class="text-success small">
                                            <i class="fas fa-check-circle"></i> Success rate
                                        </span>
                                    </div>
                                    <div class="stat-icon-admin bg-gradient-1">
                                        <i class="fas fa-paper-plane"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="notification-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted fw-semibold">Failed</h6>
                                        <h2 class="fw-bold"><?php echo $stats['total_failed']; ?></h2>
                                        <span class="text-danger small">
                                            <i class="fas fa-exclamation-circle"></i> Needs attention
                                        </span>
                                    </div>
                                    <div class="stat-icon-admin bg-gradient-2">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="notification-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted fw-semibold">Sent Today</h6>
                                        <h2 class="fw-bold"><?php echo $stats['today_sent']; ?></h2>
                                        <span class="text-info small">
                                            <i class="fas fa-calendar-day"></i> Daily average
                                        </span>
                                    </div>
                                    <div class="stat-icon-admin bg-gradient-3">
                                        <i class="fas fa-calendar"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="notification-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted fw-semibold">Email Notifications</h6>
                                        <h2 class="fw-bold"><?php echo $stats['email_count']; ?></h2>
                                        <span class="text-primary small">
                                            <i class="fas fa-envelope"></i> Primary channel
                                        </span>
                                    </div>
                                    <div class="stat-icon-admin bg-gradient-4">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                            <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Notification Tabs -->
                    <ul class="nav nav-tabs-notification mb-4" id="notificationTabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#send">
                                <i class="fas fa-paper-plane me-2"></i>Send Notification
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#history">
                                <i class="fas fa-history me-2"></i>Notification History
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#templates">
                                <i class="fas fa-file-alt me-2"></i>Templates
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#settings">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="notificationTabContent">
                        <!-- Send Notification Tab -->
                        <div class="tab-pane fade show active" id="send">
                            <div class="notification-card">
                                <h4 class="mb-4"><i class="fas fa-paper-plane me-2"></i>Send New Notification</h4>
                                
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Notification Type *</label>
                                            <select class="form-select" name="type" required>
                                                <option value="">Select Type</option>
                                                <option value="email">Email Only</option>
                                                <option value="sms">SMS Only</option>
                                                <option value="both">Both Email & SMS</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Recipients *</label>
                                            <select class="form-select" name="recipients" required>
                                                <option value="">Select Recipients</option>
                                                <option value="all">All Users</option>
                                                <option value="active">Active Users (Last 7 days)</option>
                                                <option value="inactive">Inactive Users (30+ days)</option>
                                                <option value="custom">Custom Selection</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">Subject *</label>
                                            <input type="text" class="form-control" name="subject" 
                                                   placeholder="Enter notification subject" required>
                                        </div>
                                        
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">Message *</label>
                                            <textarea class="form-control" name="message" rows="8" 
                                                      placeholder="Enter your message here..." required></textarea>
                                            <div class="mt-2">
                                                <small class="text-muted">You can use variables: {name}, {email}, {streak}</small>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-12 mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="send_now" value="1" checked>
                                                <label class="form-check-label">
                                                    Send immediately
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="save_template" value="1">
                                                <label class="form-check-label">
                                                    Save as template
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-12">
                                            <div class="d-flex justify-content-between">
                                                <button type="button" class="btn btn-secondary" id="previewBtn">
                                                    <i class="fas fa-eye me-2"></i>Preview
                                                </button>
                                                <button type="submit" name="send_notification" class="btn btn-primary">
                                                    <i class="fas fa-paper-plane me-2"></i>Send Notification
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                
                                <!-- Recipient Preview -->
                                <div class="mt-4">
                                    <h6>Recipient Preview</h6>
                                    <div id="recipientPreview">
                                        <span class="recipient-badge">All Users (<?php 
                                            $total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_admin = 0")->fetch_assoc()['count'];
                                            echo $total_users;
                                        ?>)</span>
                                        <span class="recipient-badge">Active: <?php 
                                            $active_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_admin = 0 AND is_active = 1")->fetch_assoc()['count'];
                                            echo $active_users;
                                        ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- History Tab -->
                        <div class="tab-pane fade" id="history">
                            <div class="notification-card">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h4 class="mb-0"><i class="fas fa-history me-2"></i>Notification History</h4>
                                    <div class="d-flex gap-2">
                                        <input type="date" class="form-control" id="filterDate" style="width: auto;">
                                        <select class="form-select" id="filterType" style="width: auto;">
                                            <option value="">All Types</option>
                                            <option value="Email">Email</option>
                                            <option value="SMS">SMS</option>
                                        </select>
                                        <select class="form-select" id="filterStatus" style="width: auto;">
                                            <option value="">All Status</option>
                                            <option value="Sent">Sent</option>
                                            <option value="Failed">Failed</option>
                                            <option value="Pending">Pending</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-hover" id="notificationsTable">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>User</th>
                                                <th>Type</th>
                                                <th>Title</th>
                                                <th>Sent At</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($notification = $notifications->fetch_assoc()): 
                                                $user = $conn->query("SELECT name, email FROM users WHERE id = {$notification['user_id']}")->fetch_assoc();
                                            ?>
                                                <tr>
                                                    <td>#<?php echo str_pad($notification['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($user['name']); ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo $user['email']; ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?php echo $notification['type'] === 'Email' ? 'bg-success' : 'bg-primary'; ?>">
                                                            <?php echo $notification['type']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($notification['title']); ?></td>
                                                    <td><?php echo date('M d, Y H:i', strtotime($notification['sent_at'])); ?></td>
                                                    <td>
                                                        <?php if ($notification['status'] === 'Sent'): ?>
                                                            <span class="badge bg-success">Sent</span>
                                                        <?php elseif ($notification['status'] === 'Failed'): ?>
                                                            <span class="badge bg-danger">Failed</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-info view-notification" 
                                                                data-notification='<?php echo json_encode($notification); ?>'
                                                                data-user-name="<?php echo htmlspecialchars($user['name']); ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if ($notification['status'] === 'Failed'): ?>
                                                            <button class="btn btn-sm btn-outline-warning resend-notification" 
                                                                    data-id="<?php echo $notification['id']; ?>">
                                                                <i class="fas fa-redo"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <nav aria-label="Notification pagination">
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
                        
                        <!-- Templates Tab -->
                        <div class="tab-pane fade" id="templates">
                            <div class="notification-card">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h4 class="mb-0"><i class="fas fa-file-alt me-2"></i>Notification Templates</h4>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                                        <i class="fas fa-plus me-2"></i>Add Template
                                    </button>
                                </div>
                                
                                <div class="row">
                                    <?php 
                                    $templates = [
                                        [
                                            'name' => 'Welcome Email',
                                            'type' => 'Email',
                                            'subject' => 'Welcome to Habit Tracker!',
                                            'content' => 'Hi {name}, welcome to Habit Tracker! Start building better habits today.',
                                            'variables' => ['{name}', '{email}']
                                        ],
                                        [
                                            'name' => 'Weekly Progress Report',
                                            'type' => 'Email',
                                            'subject' => 'Your Weekly Progress Report',
                                            'content' => 'Hi {name}, here is your weekly progress report. You completed {completed} out of {total} habits this week!',
                                            'variables' => ['{name}', '{completed}', '{total}', '{streak}']
                                        ],
                                        [
                                            'name' => 'Habit Reminder',
                                            'type' => 'SMS',
                                            'subject' => 'Habit Reminder',
                                            'content' => 'Hi {name}, don\'t forget to complete your habits today! Current streak: {streak} days.',
                                            'variables' => ['{name}', '{streak}']
                                        ],
                                        [
                                            'name' => 'Account Verification',
                                            'type' => 'Email',
                                            'subject' => 'Verify Your Email',
                                            'content' => 'Hi {name}, please verify your email address to get started.',
                                            'variables' => ['{name}', '{verification_link}']
                                        ]
                                    ];
                                    
                                    foreach ($templates as $template): ?>
                                        <div class="col-md-6 mb-4">
                                            <div class="card h-100">
                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0"><?php echo $template['name']; ?></h6>
                                                    <span class="badge <?php echo $template['type'] === 'Email' ? 'bg-success' : 'bg-primary'; ?>">
                                                        <?php echo $template['type']; ?>
                                                    </span>
                                                </div>
                                                <div class="card-body">
                                                    <h6 class="card-subtitle mb-2 text-muted">Subject:</h6>
                                                    <p class="card-text"><?php echo $template['subject']; ?></p>
                                                    
                                                    <h6 class="card-subtitle mb-2 text-muted">Content:</h6>
                                                    <p class="card-text"><?php echo nl2br($template['content']); ?></p>
                                                    
                                                    <h6 class="card-subtitle mb-2 text-muted">Available Variables:</h6>
                                                    <div class="mb-3">
                                                        <?php foreach ($template['variables'] as $var): ?>
                                                            <span class="badge bg-light text-dark"><?php echo $var; ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <div class="card-footer bg-transparent">
                                                    <div class="d-flex justify-content-between">
                                                        <button class="btn btn-sm btn-outline-primary use-template" 
                                                                data-template='<?php echo json_encode($template); ?>'>
                                                            <i class="fas fa-paper-plane me-1"></i>Use Template
                                                        </button>
                                                        <div>
                                                            <button class="btn btn-sm btn-outline-secondary">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Settings Tab -->
                        <div class="tab-pane fade" id="settings">
                            <div class="notification-card">
                                <h4 class="mb-4"><i class="fas fa-cog me-2"></i>Notification Settings</h4>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0"><i class="fas fa-envelope me-2"></i>Email Settings</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <label class="form-label">SMTP Host</label>
                                                    <input type="text" class="form-control" value="smtp.gmail.com">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">SMTP Port</label>
                                                    <input type="number" class="form-control" value="587">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">From Email</label>
                                                    <input type="email" class="form-control" value="noreply@habittracker.com">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">From Name</label>
                                                    <input type="text" class="form-control" value="Habit Tracker">
                                                </div>
                                                <button class="btn btn-primary">Save Email Settings</button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0"><i class="fas fa-sms me-2"></i>SMS Settings</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <label class="form-label">SMS Provider</label>
                                                    <select class="form-select">
                                                        <option value="twilio">Twilio</option>
                                                        <option value="nexmo">Vonage (Nexmo)</option>
                                                        <option value="plivo">Plivo</option>
                                                        <option value="custom">Custom API</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">API Key</label>
                                                    <input type="password" class="form-control" placeholder="Enter API key">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">API Secret</label>
                                                    <input type="password" class="form-control" placeholder="Enter API secret">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Sender ID</label>
                                                    <input type="text" class="form-control" placeholder="e.g., HABITTR">
                                                </div>
                                                <button class="btn btn-primary">Save SMS Settings</button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Automated Notifications</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="dailyDigest" checked>
                                                            <label class="form-check-label" for="dailyDigest">
                                                                Daily Progress Digest
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Send daily summary emails at 8:00 PM</small>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="weeklyReport" checked>
                                                            <label class="form-check-label" for="weeklyReport">
                                                                Weekly Reports
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Send weekly reports every Monday</small>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="inactiveReminder">
                                                            <label class="form-check-label" for="inactiveReminder">
                                                                Inactive User Reminders
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Remind users after 7 days of inactivity</small>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="streakReminder" checked>
                                                            <label class="form-check-label" for="streakReminder">
                                                                Streak Reminders
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Notify users about their streaks</small>
                                                    </div>
                                                </div>
                                                <button class="btn btn-primary">Save Automation Settings</button>
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
    
    <!-- View Notification Modal -->
    <div class="modal fade" id="viewNotificationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Notification Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="notificationDetails">
                    <!-- Content will be loaded via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Template Modal -->
    <div class="modal fade" id="addTemplateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Template Name *</label>
                                <input type="text" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Type *</label>
                                <select class="form-select" required>
                                    <option value="Email">Email</option>
                                    <option value="SMS">SMS</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Subject *</label>
                                <input type="text" class="form-control" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Content *</label>
                                <textarea class="form-control" rows="6" required></textarea>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Available Variables</label>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <span class="badge bg-light text-dark variable-tag" onclick="insertVariable('{name}')">{name}</span>
                                    <span class="badge bg-light text-dark variable-tag" onclick="insertVariable('{email}')">{email}</span>
                                    <span class="badge bg-light text-dark variable-tag" onclick="insertVariable('{streak}')">{streak}</span>
                                    <span class="badge bg-light text-dark variable-tag" onclick="insertVariable('{completed}')">{completed}</span>
                                    <span class="badge bg-light text-dark variable-tag" onclick="insertVariable('{total}')">{total}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Template</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // View notification details
        document.querySelectorAll('.view-notification').forEach(button => {
            button.addEventListener('click', function() {
                const notification = JSON.parse(this.dataset.notification);
                const userName = this.dataset.userName;
                
                const details = document.getElementById('notificationDetails');
                details.innerHTML = `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6>User</h6>
                            <p>${userName}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6>Type</h6>
                            <span class="badge ${notification.type === 'Email' ? 'bg-success' : 'bg-primary'}">
                                ${notification.type}
                            </span>
                        </div>
                        <div class="col-md-12 mb-3">
                            <h6>Title</h6>
                            <p>${notification.title}</p>
                        </div>
                        <div class="col-md-12 mb-3">
                            <h6>Message</h6>
                            <div class="bg-light p-3 rounded">
                                ${notification.message.replace(/\n/g, '<br>')}
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6>Sent At</h6>
                            <p>${new Date(notification.sent_at).toLocaleString()}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6>Status</h6>
                            <span class="badge ${notification.status === 'Sent' ? 'bg-success' : 
                                               notification.status === 'Failed' ? 'bg-danger' : 'bg-warning'}">
                                ${notification.status}
                            </span>
                        </div>
                    </div>
                `;
                
                const modal = new bootstrap.Modal(document.getElementById('viewNotificationModal'));
                modal.show();
            });
        });
        
        // Use template
        document.querySelectorAll('.use-template').forEach(button => {
            button.addEventListener('click', function() {
                const template = JSON.parse(this.dataset.template);
                
                // Fill the send notification form
                document.querySelector('select[name="type"]').value = template.type.toLowerCase();
                document.querySelector('input[name="subject"]').value = template.subject;
                document.querySelector('textarea[name="message"]').value = template.content;
                
                // Switch to send tab
                const sendTab = document.querySelector('a[href="#send"]');
                new bootstrap.Tab(sendTab).show();
                
                // Scroll to form
                document.querySelector('#send').scrollIntoView({ behavior: 'smooth' });
            });
        });
        
        // Resend notification
        document.querySelectorAll('.resend-notification').forEach(button => {
            button.addEventListener('click', function() {
                const notificationId = this.dataset.id;
                
                if (confirm('Resend this notification?')) {
                    fetch(`resend-notification.php?id=${notificationId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Notification resent successfully!');
                                location.reload();
                            } else {
                                alert('Failed to resend notification.');
                            }
                        });
                }
            });
        });
        
        // Preview notification
        document.getElementById('previewBtn').addEventListener('click', function() {
            const subject = document.querySelector('input[name="subject"]').value;
            const message = document.querySelector('textarea[name="message"]').value;
            
            const previewModal = new bootstrap.Modal(new bootstrap.Modal(document.createElement('div')));
            
            const modalContent = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Notification Preview</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="card">
                                <div class="card-header">
                                    <strong>Subject:</strong> ${subject}
                                </div>
                                <div class="card-body">
                                    ${message.replace(/\n/g, '<br>')}
                                </div>
                            </div>
                            <div class="mt-3">
                                <small class="text-muted">This is a preview. Variables will be replaced with actual values when sent.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            `;
            
            // Create and show modal
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = modalContent;
            document.body.appendChild(modal);
            
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            modal.addEventListener('hidden.bs.modal', function() {
                document.body.removeChild(modal);
            });
        });
        
        // Filter notifications table
        const filterDate = document.getElementById('filterDate');
        const filterType = document.getElementById('filterType');
        const filterStatus = document.getElementById('filterStatus');
        
        function filterNotifications() {
            const date = filterDate.value;
            const type = filterType.value;
            const status = filterStatus.value;
            
            // Implement filtering logic here
            console.log('Filtering by:', { date, type, status });
        }
        
        if (filterDate) filterDate.addEventListener('change', filterNotifications);
        if (filterType) filterType.addEventListener('change', filterNotifications);
        if (filterStatus) filterStatus.addEventListener('change', filterNotifications);
        
        // Insert variable into template
        function insertVariable(variable) {
            const textarea = document.querySelector('#addTemplateModal textarea');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            
            textarea.value = text.substring(0, start) + variable + text.substring(end);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + variable.length;
        }
    </script>
</body>
</html>