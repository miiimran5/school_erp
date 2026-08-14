<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">

    <!-- Brand Logo -->
    <a href="<?= BASE_URL; ?>/modules/dashboard/index.php" class="brand-link">
        <span class="brand-text font-weight-light">
            Oasis Public School ERP
        </span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">

        <!-- User Panel -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">

            <div class="image">
                <i class="fas fa-user-circle fa-2x text-white"></i>
            </div>

            <div class="info">
                <a href="#" class="d-block">
                    <?= $_SESSION['full_name'] ?? 'Administrator'; ?>
                </a>
            </div>

        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">

            <ul class="nav nav-pills nav-sidebar flex-column"
                data-widget="treeview"
                role="menu"
                data-accordion="false">

                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="<?= BASE_URL; ?>/modules/dashboard/index.php" class="nav-link">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <!-- Students -->
                <li class="nav-item">
                    <a href="<?= BASE_URL; ?>/modules/students/index.php" class="nav-link">
                        <i class="nav-icon fas fa-user-graduate"></i>
                        <p>Students</p>
                    </a>
                </li>

                <!-- Teachers -->
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-chalkboard-teacher"></i>
                        <p>Teachers</p>
                    </a>
                </li>

                <!-- Attendance -->
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-calendar-check"></i>
                        <p>Attendance</p>
                    </a>
                </li>

                <!-- Fees -->
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-rupee-sign"></i>
                        <p>Fees</p>
                    </a>
                </li>

                <!-- Examinations -->
                <li class="nav-item">
                    <a href="<?= BASE_URL; ?>/modules/exams/index.php" class="nav-link">
                        <i class="nav-icon fas fa-file-alt"></i>
                        <p>Examinations</p>
                    </a>
                </li>

                <!-- Reports -->
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <p>Reports</p>
                    </a>
                </li>

                <!-- Settings -->
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-cogs"></i>
                        <p>Settings</p>
                    </a>
                </li>

            </ul>

        </nav>

    </div>

</aside>