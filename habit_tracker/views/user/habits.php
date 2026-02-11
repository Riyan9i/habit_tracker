<?php
session_start();
require_once '../../includes/auth-check.php';
require_once '../../config/database.php';
require_once '../../models/Habit.php';

$user_id = $_SESSION['user_id'];
$habit = new Habit($conn);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_habit'])) {
        $result = $habit->create($user_id, $_POST);
        if ($result['success']) {
            $_SESSION['message'] = $result['message'];
            header('Location: habits.php');
            exit();
        }
    } elseif (isset($_POST['edit_habit'])) {
        $result = $habit->update($_POST['id'], $_POST);
        $_SESSION['message'] = $result['message'];
        header('Location: habits.php');
        exit();
    } elseif (isset($_POST['delete_habit'])) {
        $result = $habit->delete($_POST['id']);
        $_SESSION['message'] = $result['message'];
        header('Location: habits.php');
        exit();
    }
}

// Get all user habits
$habits = $habit->getAll($user_id);
$categories = ['Health', 'Study', 'Fitness', 'Work', 'Personal', 'Social', 'Other'];
?>
<!DOCTYPE html>
<html lang="en" <?php echo $_SESSION['dark_mode'] ? 'data-bs-theme="dark"' : ''; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Habit Management - Habit Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css">
    <style>
        .habit-card {
            border-left: 5px solid #4CAF50;
            transition: all 0.3s;
            border-radius: 10px;
        }
        .habit-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .habit-card.health { border-left-color: #4CAF50; }
        .habit-card.study { border-left-color: #2196F3; }
        .habit-card.fitness { border-left-color: #FF5722; }
        .habit-card.work { border-left-color: #9C27B0; }
        .habit-card.personal { border-left-color: #FFC107; }
        .habit-card.social { border-left-color: #00BCD4; }
        .habit-card.other { border-left-color: #795548; }
        
        .badge-category {
            font-size: 0.75rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 0.25rem rgba(76, 175, 80, 0.25);
        }
        
        .calendar-day {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .calendar-day.completed {
            background-color: #4CAF50;
            color: white;
        }
        
        .calendar-day.today {
            border: 2px solid #2196F3;
        }
        
        .frequency-badge {
            background-color: #e3f2fd;
            color: #2196F3;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .completion-rate {
            font-size: 2rem;
            font-weight: bold;
            color: #4CAF50;
        }
        
        .streak-count {
            font-size: 1.5rem;
            color: #FF5722;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
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
                        <i class="fas fa-tasks me-2"></i>Habit Management
                    </h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHabitModal">
                        <i class="fas fa-plus me-2"></i>Add New Habit
                    </button>
                </div>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Stats Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3 class="completion-rate"><?php echo $habit->getOverallCompletionRate($user_id); ?>%</h3>
                                <p class="text-muted mb-0">Overall Completion Rate</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3 class="streak-count"><?php echo $habit->getCurrentStreak($user_id); ?> 🔥</h3>
                                <p class="text-muted mb-0">Current Streak</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3><?php echo count($habits); ?></h3>
                                <p class="text-muted mb-0">Total Habits</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3><?php echo $habit->getMonthlyCompleted($user_id); ?></h3>
                                <p class="text-muted mb-0">Completed This Month</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Habit List -->
                <div class="row">
                    <?php foreach ($habits as $h): 
                        $completion_rate = $habit->getHabitCompletionRate($h['id']);
                        $streak = $habit->getHabitStreak($h['id']);
                    ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card habit-card <?php echo strtolower($h['category']); ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($h['name']); ?></h5>
                                            <span class="badge-category" style="background-color: <?php echo $habit->getCategoryColor($h['category']); ?>;">
                                                <?php echo $h['category']; ?>
                                            </span>
                                            <span class="frequency-badge ms-2">
                                                <i class="fas fa-<?php echo $h['frequency'] === 'Daily' ? 'calendar-day' : 'calendar-week'; ?> me-1"></i>
                                                <?php echo $h['frequency']; ?>
                                            </span>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" 
                                                       data-bs-target="#editHabitModal" data-habit='<?php echo json_encode($h); ?>'>
                                                        <i class="fas fa-edit me-2"></i>Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" 
                                                       data-bs-target="#deleteHabitModal" data-habit-id="<?php echo $h['id']; ?>"
                                                       data-habit-name="<?php echo htmlspecialchars($h['name']); ?>">
                                                        <i class="fas fa-trash me-2"></i>Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <?php if ($h['reminder_time']): ?>
                                        <p class="mb-2">
                                            <i class="fas fa-clock me-2"></i>
                                            Reminder at <?php echo date('h:i A', strtotime($h['reminder_time'])); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <small>Completion Rate</small>
                                            <small><?php echo isset($getWeeklyProgress['completion_rate']) ? $getWeeklyProgress['completion_rate'] . '%' : '0%';;
 ?>%</small>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $completion_rate; ?>%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-fire me-1"></i> Streak: <?php echo $streak; ?> days
                                            </span>
                                        </div>
                                        <button class="btn btn-sm btn-success mark-habit-complete" 
                                                data-habit-id="<?php echo $h['id']; ?>"
                                                <?php echo $habit->getCompletedHabitsToday($h['id']) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-check me-1"></i>
                                            <?php echo $habit->getCompletedHabitsToday($h['id']) ? 'Completed Today' : 'Mark Complete'; ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($habits)): ?>
                        <div class="col-12 text-center py-5">
                            <i class="fas fa-tasks fa-4x text-muted mb-4"></i>
                            <h4 class="text-muted mb-3">No habits yet</h4>
                            <p class="text-muted mb-4">Start building your habits by adding your first one!</p>
                            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addHabitModal">
                                <i class="fas fa-plus me-2"></i>Add Your First Habit
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Habit Calendar -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Habit Calendar</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Habit</th>
                                        <?php
                                        $startDate = date('Y-m-d', strtotime('-7 days'));
                                        $endDate = date('Y-m-d');
                                        $dates = [];
                                        for ($i = 0; $i < 7; $i++) {
                                            $date = date('Y-m-d', strtotime($startDate . " +$i days"));
                                            $dates[] = $date;
                                        }
                                        foreach ($dates as $date): ?>
                                            <th class="text-center <?php echo $date === date('Y-m-d') ? 'bg-light' : ''; ?>">
                                                <?php echo date('D, M j', strtotime($date)); ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($habits as $h): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($h['name']); ?></td>
                                            <?php foreach ($dates as $date): ?>
                                                <td class="text-center">
                                                    <?php if ($habit->isCompletedOnDate($h['id'], $date)): ?>
                                                        <i class="fas fa-check-circle text-success" title="Completed"></i>
                                                    <?php else: ?>
                                                        <i class="far fa-circle text-muted" title="Not completed"></i>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Habit Modal -->
    <div class="modal fade" id="addHabitModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Habit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Habit Name *</label>
                                <input type="text" class="form-control" name="name" placeholder="e.g., Morning Exercise" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-select" name="category" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Frequency *</label>
                                <select class="form-select" name="frequency" required>
                                    <option value="Daily">Daily</option>
                                    <option value="Weekly">Weekly</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Reminder Time</label>
                                <input type="time" class="form-control" name="reminder_time">
                                <small class="text-muted">Leave empty for no reminder</small>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Description (Optional)</label>
                                <textarea class="form-control" name="description" rows="3" placeholder="Describe your habit..."></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="send_reminder" value="1" checked>
                                    <label class="form-check-label">Send reminder notifications</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_important" value="1">
                                    <label class="form-check-label">Mark as important habit</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_habit" class="btn btn-primary">Add Habit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Habit Modal -->
    <div class="modal fade" id="editHabitModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Habit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="editHabitId">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Habit Name *</label>
                                <input type="text" class="form-control" name="name" id="editHabitName" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-select" name="category" id="editHabitCategory" required>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Frequency *</label>
                                <select class="form-select" name="frequency" id="editHabitFrequency" required>
                                    <option value="Daily">Daily</option>
                                    <option value="Weekly">Weekly</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Reminder Time</label>
                                <input type="time" class="form-control" name="reminder_time" id="editHabitReminder">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_habit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Habit Modal -->
    <div class="modal fade" id="deleteHabitModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Delete Habit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="deleteHabitId">
                        <p>Are you sure you want to delete the habit "<strong id="deleteHabitName"></strong>"?</p>
                        <p class="text-danger">This action cannot be undone. All completion history will be lost.</p>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="confirm_delete" required>
                            <label class="form-check-label">Yes, I want to delete this habit</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_habit" class="btn btn-danger">Delete Habit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit Habit Modal
        const editHabitModal = document.getElementById('editHabitModal');
        if (editHabitModal) {
            editHabitModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const habit = JSON.parse(button.dataset.habit);
                
                document.getElementById('editHabitId').value = habit.id;
                document.getElementById('editHabitName').value = habit.name;
                document.getElementById('editHabitCategory').value = habit.category;
                document.getElementById('editHabitFrequency').value = habit.frequency;
                document.getElementById('editHabitReminder').value = habit.reminder_time || '';
            });
        }
        
        // Delete Habit Modal
        const deleteHabitModal = document.getElementById('deleteHabitModal');
        if (deleteHabitModal) {
            deleteHabitModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                document.getElementById('deleteHabitId').value = button.dataset.habitId;
                document.getElementById('deleteHabitName').textContent = button.dataset.habitName;
            });
        }
        
        // Mark habit as complete
        document.querySelectorAll('.mark-habit-complete').forEach(button => {
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
                        button.disabled = true;
                        button.innerHTML = '<i class="fas fa-check me-1"></i>Completed Today';
                        button.classList.remove('btn-success');
                        button.classList.add('btn-secondary');
                        
                        // Show success message
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-success alert-dismissible fade show';
                        alert.innerHTML = `
                            <i class="fas fa-check-circle me-2"></i>Habit marked as completed!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        document.querySelector('.d-flex.justify-content-between').after(alert);
                        
                        // Refresh stats after 2 seconds
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    }
                });
            });
        });
        
        // Form validation
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    </script>

    <!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php include '../../includes/footer.php'; ?>
</body>
</html>