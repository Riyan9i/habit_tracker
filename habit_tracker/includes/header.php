<?php
// Modern Professional Header
?>
<style>
    /* Fixed navbar with shadow */
    .custom-navbar {
        background-color: #4CAF50; /* Primary color */
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        padding: 0.6rem 1rem;
        z-index: 1050; /* above everything */
        transition: all 0.3s;
    }

    .custom-navbar .navbar-brand {
        color: #fff;
        font-size: 1.25rem;
    }

    .custom-navbar .nav-link {
        color: #fff;
        font-weight: 500;
        margin-right: 1rem;
        transition: color 0.2s;
    }

    .custom-navbar .nav-link:hover {
        color: #e0f7fa;
    }

    .custom-navbar .btn-light {
        background-color: #fff !important;
        color: #4CAF50 !important;
        font-weight: 500;
        border-radius: 8px;
        transition: all 0.3s;
    }

    .custom-navbar .btn-light:hover {
        background-color: #f1f1f1 !important;
    }

    .custom-navbar .btn-primary {
        background-color: #fff !important;
        color: #4CAF50 !important;
        font-weight: 500;
        border-radius: 8px;
    }

    body {
        padding-top: 70px; /* to prevent content hidden behind navbar */
    }

</style>

<nav class="navbar navbar-expand-lg navbar-dark custom-navbar fixed-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="../user/dashboard.php">
            <i class="fas fa-chart-line me-2"></i>HabitTracker
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">

                <?php if (isset($_SESSION['user_id'])): ?>

                    <?php if ($_SESSION['is_admin'] == 1): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/dashboard.php">
                                <i class="fas fa-shield-alt me-1"></i>Admin
                            </a>
                        </li>
                    <?php endif; ?>

                    <li class="nav-item">
                        <a class="nav-link" href="../user/dashboard.php">Dashboard</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="../user/habits.php">Habits</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="../user/calorie-tracker.php">Calories</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="../user/profile.php">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo $_SESSION['username'] ?? 'Profile'; ?>
                        </a>
                    </li>

                    <li class="nav-item ms-3">
                        <a class="btn btn-sm btn-light text-dark px-3" href="../auth/logout.php">
                            Logout
                        </a>
                    </li>

                <?php else: ?>

                    <li class="nav-item">
                        <a class="nav-link" href="../auth/login.php">Login</a>
                    </li>

                    <li class="nav-item ms-2">
                        <a class="btn btn-sm btn-primary px-3" href="../auth/register.php">
                            Get Started
                        </a>
                    </li>

                <?php endif; ?>

            </ul>
        </div>
    </div>
</nav>
