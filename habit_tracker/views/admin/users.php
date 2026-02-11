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
    $user_id = $_GET['id'] ?? 0;
    
    switch ($action) {
        case 'block':
            $conn->query("UPDATE users SET is_active = 0 WHERE id = $user_id");
            $_SESSION['message'] = "User blocked successfully!";
            break;
        case 'unblock':
            $conn->query("UPDATE users SET is_active = 1 WHERE id = $user_id");
            $_SESSION['message'] = "User unblocked successfully!";
            break;
        case 'delete':
            $conn->query("DELETE FROM users WHERE id = $user_id AND is_admin = 0");
            $_SESSION['message'] = "User deleted successfully!";
            break;
        case 'reset_password':
            // Generate temporary password
            $temp_password = substr(md5(uniqid()), 0, 8);
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password = '$hashed_password' WHERE id = $user_id");
            $_SESSION['message'] = "Password reset! Temporary password: $temp_password";
            break;
    }
    
    header('Location: users.php');
    exit();
}

// Get all users (non-admin)
$users = $conn->query("SELECT * FROM users WHERE is_admin = 0 ORDER BY created_at DESC");

// Get user statistics
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_admin = 0")->fetch_assoc()['count'];
$active_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1 AND is_admin = 0")->fetch_assoc()['count'];
$new_today = $conn->query("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE() AND is_admin = 0")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        .admin-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .stat-badge {
            font-size: 0.9rem;
            padding: 5px 15px;
            border-radius: 20px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .action-dropdown .dropdown-menu {
            min-width: 180px;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box .form-control {
            padding-left: 40px;
            border-radius: 25px;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .pagination .page-link {
            border-radius: 5px;
            margin: 0 3px;
        }
        
        .modal-dialog-user {
            max-width: 800px;
        }
        
        .tab-content {
            padding: 20px 0;
        }
        
        .nav-tabs-user .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 500;
            padding: 12px 25px;
            border-radius: 0;
        }
        
        .nav-tabs-user .nav-link.active {
            color: #4CAF50;
            border-bottom-color: #4CAF50;
            background: transparent;
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
                            <a class="nav-link active" href="users.php">
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
                            <h5 class="mb-0">
                                <i class="fas fa-users me-2"></i>User Management
                            </h5>
                            <span class="badge bg-primary ms-3">Total: <?php echo $total_users; ?> users</span>
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
                            <div class="admin-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted fw-semibold">Total Users</h6>
                                        <h2 class="fw-bold"><?php echo $total_users; ?></h2>
                                        <span class="text-success small">
                                            <i class="fas fa-arrow-up"></i> 12% growth
                                        </span>
                                    </div>
                                    <div class="stat-icon-admin bg-gradient-1">
                                        <i class="fas fa-users"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="admin-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted fw-semibold">Active Users</h6>
                                        <h2 class="fw-bold"><?php echo $active_users; ?></h2>
                                        <span class="text-success small">
                                            <?php echo $total_users > 0 ? round(($active_users / $total_users) * 100) : 0; ?>% active rate
                                        </span>
                                    </div>
                                    <div class="stat-icon-admin bg-gradient-2">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="admin-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted fw-semibold">New Today</h6>
                                        <h2 class="fw-bold"><?php echo $new_today; ?></h2>
                                        <span class="text-success small">
                                            <i class="fas fa-calendar-day"></i> Daily signups
                                        </span>
                                    </div>
                                    <div class="stat-icon-admin bg-gradient-3">
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="admin-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted fw-semibold">Blocked Users</h6>
                                        <h2 class="fw-bold"><?php echo $total_users - $active_users; ?></h2>
                                        <span class="text-danger small">
                                            Needs attention
                                        </span>
                                    </div>
                                    <div class="stat-icon-admin bg-gradient-4">
                                        <i class="fas fa-user-slash"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Management -->
                    <div class="admin-card">
                        <?php if (isset($_SESSION['message'])): ?>
                            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="mb-0">All Users</h4>
                            <div class="d-flex gap-2">
                                <div class="search-box">
                                    <i class="fas fa-search"></i>
                                    <input type="text" class="form-control" placeholder="Search users..." id="searchInput">
                                </div>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                    <i class="fas fa-user-plus me-2"></i>Add User
                                </button>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover" id="usersTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Joined</th>
                                        <th>Status</th>
                                        <th>Habits</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($user = $users->fetch_assoc()): 
                                        $habits_count = $conn->query("SELECT COUNT(*) as count FROM habits WHERE user_id = {$user['id']}")->fetch_assoc()['count'];
                                        $last_login = $user['last_login'] ? date('M d, Y', strtotime($user['last_login'])) : 'Never';
                                    ?>
                                        <tr>
                                            <td>#<?php echo str_pad($user['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="../../assets/images/uploads/<?php echo $user['profile_picture'] ?? 'default.png'; ?>" 
                                                         class="user-avatar me-3" alt="<?php echo htmlspecialchars($user['name']); ?>">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">Last login: <?php echo $last_login; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo $user['email']; ?></td>
                                            <td><?php echo $user['phone'] ?: 'N/A'; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <?php if ($user['email_verified']): ?>
                                                    <span class="badge bg-success">Verified</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Unverified</span>
                                                <?php endif; ?>
                                                
                                                <?php if ($user['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Blocked</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="stat-badge bg-info"><?php echo $habits_count; ?> habits</span>
                                            </td>
                                            <td>
                                                <div class="dropdown action-dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                            type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" 
                                                               data-bs-target="#viewUserModal" data-user-id="<?php echo $user['id']; ?>">
                                                                <i class="fas fa-eye me-2"></i>View Details
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" 
                                                               data-bs-target="#editUserModal" data-user='<?php echo json_encode($user); ?>'>
                                                                <i class="fas fa-edit me-2"></i>Edit User
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <?php if ($user['is_active']): ?>
                                                            <li>
                                                                <a class="dropdown-item text-warning" 
                                                                   href="users.php?action=block&id=<?php echo $user['id']; ?>"
                                                                   onclick="return confirm('Block this user?')">
                                                                    <i class="fas fa-ban me-2"></i>Block User
                                                                </a>
                                                            </li>
                                                        <?php else: ?>
                                                            <li>
                                                                <a class="dropdown-item text-success" 
                                                                   href="users.php?action=unblock&id=<?php echo $user['id']; ?>"
                                                                   onclick="return confirm('Unblock this user?')">
                                                                    <i class="fas fa-check me-2"></i>Unblock User
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <a class="dropdown-item text-info" 
                                                               href="users.php?action=reset_password&id=<?php echo $user['id']; ?>"
                                                               onclick="return confirm('Reset password for this user?')">
                                                                <i class="fas fa-key me-2"></i>Reset Password
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" 
                                                               href="users.php?action=delete&id=<?php echo $user['id']; ?>"
                                                               onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone!')">
                                                                <i class="fas fa-trash me-2"></i>Delete User
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <nav aria-label="User pagination">
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
            </div>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-user">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="process-user.php">
                    <div class="modal-body">
                        <ul class="nav nav-tabs-user mb-3" id="addUserTabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#basicInfo">Basic Info</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#healthInfo">Health Data</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#settingsInfo">Settings</a>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="addUserTabContent">
                            <!-- Basic Info Tab -->
                            <div class="tab-pane fade show active" id="basicInfo">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" name="email" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Password *</label>
                                        <input type="password" class="form-control" name="password" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Gender</label>
                                        <select class="form-select" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" name="dob">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Health Data Tab -->
                            <div class="tab-pane fade" id="healthInfo">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Age</label>
                                        <input type="number" class="form-control" name="age" min="1" max="120">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Weight (kg)</label>
                                        <input type="number" step="0.1" class="form-control" name="weight" min="1" max="500">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Height (cm)</label>
                                        <input type="number" class="form-control" name="height" min="50" max="300">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Weight Goal</label>
                                        <select class="form-select" name="weight_goal">
                                            <option value="Maintain">Maintain Weight</option>
                                            <option value="Loss">Lose Weight</option>
                                            <option value="Gain">Gain Weight</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Settings Tab -->
                            <div class="tab-pane fade" id="settingsInfo">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Account Status</label>
                                        <select class="form-select" name="is_active">
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email Verified</label>
                                        <select class="form-select" name="email_verified">
                                            <option value="1">Verified</option>
                                            <option value="0">Not Verified</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Notification Preference</label>
                                        <select class="form-select" name="notification_pref">
                                            <option value="Both">Email & SMS</option>
                                            <option value="Email">Email Only</option>
                                            <option value="SMS">SMS Only</option>
                                            <option value="None">None</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Dark Mode</label>
                                        <select class="form-select" name="dark_mode">
                                            <option value="0">Light Mode</option>
                                            <option value="1">Dark Mode</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user me-2"></i>User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetails">
                    <!-- Content will be loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-user">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="process-user.php">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="name" id="editUserName">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email" id="editUserEmail">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone" id="editUserPhone">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Status</label>
                                <select class="form-select" name="is_active" id="editUserStatus">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="send_welcome" value="1">
                                    <label class="form-check-label">
                                        Send welcome email to user
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // View user details via AJAX
        const viewUserModal = document.getElementById('viewUserModal');
        if (viewUserModal) {
            viewUserModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const userId = button.dataset.userId;
                
                fetch(`get-user-details.php?id=${userId}`)
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('userDetails').innerHTML = `
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <img src="../../assets/images/uploads/${data.profile_picture || 'default.png'}" 
                                         class="rounded-circle mb-3" width="120" height="120">
                                    <h4>${data.name}</h4>
                                    <p class="text-muted">${data.email}</p>
                                    <span class="badge ${data.is_active ? 'bg-success' : 'bg-danger'}">
                                        ${data.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                </div>
                                <div class="col-md-8">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <h6>Phone Number</h6>
                                            <p>${data.phone || 'N/A'}</p>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <h6>Gender</h6>
                                            <p>${data.gender || 'Not specified'}</p>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <h6>Member Since</h6>
                                            <p>${new Date(data.created_at).toLocaleDateString()}</p>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <h6>Last Login</h6>
                                            <p>${data.last_login ? new Date(data.last_login).toLocaleDateString() : 'Never'}</p>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <h6>Email Status</h6>
                                            <span class="badge ${data.email_verified ? 'bg-success' : 'bg-warning'}">
                                                ${data.email_verified ? 'Verified' : 'Unverified'}
                                            </span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <h6>Notification Preference</h6>
                                            <p>${data.notification_pref}</p>
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <h6>Health Data</h6>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <small>Weight</small>
                                                    <p>${data.weight || 'N/A'} kg</p>
                                                </div>
                                                <div class="col-md-4">
                                                    <small>Height</small>
                                                    <p>${data.height || 'N/A'} cm</p>
                                                </div>
                                                <div class="col-md-4">
                                                    <small>Goal</small>
                                                    <p>${data.weight_goal || 'N/A'}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
            });
        }
        
        // Edit user modal
        const editUserModal = document.getElementById('editUserModal');
        if (editUserModal) {
            editUserModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const user = JSON.parse(button.dataset.user);
                
                document.getElementById('editUserId').value = user.id;
                document.getElementById('editUserName').value = user.name;
                document.getElementById('editUserEmail').value = user.email;
                document.getElementById('editUserPhone').value = user.phone || '';
                document.getElementById('editUserStatus').value = user.is_active;
            });
        }
        
        // Initialize DataTable
        $(document).ready(function() {
            $('#usersTable').DataTable({
                pageLength: 10,
                responsive: true,
                language: {
                    search: "Search users:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ users",
                    paginate: {
                        previous: "Previous",
                        next: "Next"
                    }
                }
            });
        });
    </script>
</body>
</html>