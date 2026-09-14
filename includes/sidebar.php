<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">

    <!-- Brand Logo -->
    <a href="<?= BASE_URL; ?>/modules/dashboard/index.php"
       class="brand-link">

        <span class="brand-text font-weight-light">
            <?= school_e(school_setting('school_name', APP_NAME)); ?>
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

                    <?= htmlspecialchars(
                        $_SESSION['full_name'] ?? 'Administrator'
                    ); ?>

                </a>

            </div>

        </div>


        <!-- Sidebar Menu -->
        <nav class="mt-2">

            <ul
                class="nav nav-pills nav-sidebar flex-column"
                data-widget="treeview"
                role="menu"
                data-accordion="false"
            >
                <?php if (function_exists('access_role') && access_role() === 'Student'): ?>
                <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/portal/student.php" class="nav-link"><i class="nav-icon fas fa-home"></i><p>My Student Portal</p></a></li>
                <?php elseif (function_exists('access_role') && access_role() === 'Teacher'): ?>
                <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/portal/teacher.php" class="nav-link"><i class="nav-icon fas fa-home"></i><p>Teacher Control Panel</p></a></li>
                <?php endif; ?>


                <!-- =================================================
                     DASHBOARD
                     ================================================= -->

                <?php if (!function_exists('access_role') || access_role() === 'Admin'): ?><li class="nav-item">

                    <a
                        href="<?= BASE_URL; ?>/modules/dashboard/index.php"
                        class="nav-link"
                    >

                        <i class="nav-icon fas fa-tachometer-alt"></i>

                        <p>
                            Dashboard
                        </p>

                    </a>

                </li><?php endif; ?>


                <!-- =================================================
                     STUDENTS
                     ================================================= -->

                <?php if (!function_exists('access_role') || access_role() !== 'Student'): ?><li class="nav-item">

                    <a
                        href="<?= BASE_URL; ?>/modules/students/index.php"
                        class="nav-link"
                    >

                        <i class="nav-icon fas fa-user-graduate"></i>

                        <p>
                            Students
                        </p>

                    </a>

                </li><?php endif; ?>


                <!-- =================================================
                     TEACHERS
                     ================================================= -->

                <?php if (!function_exists('access_role') || access_role() === 'Admin'): ?><li class="nav-item">

                    <a
                        href="<?= BASE_URL; ?>/modules/teachers/index.php"
                        class="nav-link"
                    >

                        <i class="nav-icon fas fa-chalkboard-teacher"></i>

                        <p>
                            Employees
                        </p>

                    </a>

                </li><?php endif; ?>

                <?php if (!function_exists('access_role') || access_role() !== 'Student'): ?><li class="nav-item">
                    <a href="<?= BASE_URL; ?>/modules/teachers/assignments.php" class="nav-link">
                        <i class="nav-icon fas fa-user-tie"></i>
                        <p>Teacher Subjects & Classes</p>
                    </a>
                </li><?php endif; ?>

                <?php if ((int)($_SESSION['role_id'] ?? 0) <= 3): ?>
                <?php if (!function_exists('access_role') || access_role() !== 'Student'): ?><li class="nav-item">
                    <a href="<?= BASE_URL; ?>/modules/users/index.php" class="nav-link">
                        <i class="nav-icon fas fa-key"></i>
                        <p>Login Accounts</p>
                    </a>
                </li>                <li class="nav-item">
                    <a href="<?= BASE_URL; ?>/modules/settings/index.php" class="nav-link">
                        <i class="nav-icon fas fa-cog"></i><p>School Settings</p>
                    </a>
                </li><?php endif; ?>
                <?php endif; ?>


                <!-- =================================================
                     ATTENDANCE
                     ================================================= -->

                <?php if (!function_exists('access_role') || access_role() !== 'Student'): ?><li class="nav-item">

                    <a
                        href="<?= BASE_URL; ?>/modules/attendance/index.php"
                        class="nav-link"
                    >

                        <i class="nav-icon fas fa-calendar-check"></i>

                        <p>
                            Attendance
                        </p>

                    </a>

                </li><?php endif; ?>


                <!-- =================================================
                     FEES
                     ================================================= -->

                <?php if (!function_exists('access_role') || access_role() === 'Admin'): ?><li class="nav-item has-treeview">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-rupee-sign"></i>
                        <p>Fees <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/fees/index.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Fee Dashboard</p></a></li>
                        <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/fees/index.php#structure" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Fee Structure</p></a></li>
                        <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/fees/index.php#schedule" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Fee Schedule</p></a></li>
                        <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/fees/index.php#concession" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Fee Concession</p></a></li>
                    </ul>
                </li><?php endif; ?>


                <!-- =================================================
                     EXAMINATIONS
                     ================================================= -->

                <?php if (!function_exists('access_role') || access_role() !== 'Student'): ?><li class="nav-item has-treeview">

                    <a
                        href="#"
                        class="nav-link"
                    >

                        <i class="nav-icon fas fa-file-alt"></i>

                        <p>

                            Examinations

                            <i class="right fas fa-angle-left"></i>

                        </p>

                    </a>


                    <ul class="nav nav-treeview">


                        <!-- Marks Entry -->

                        <li class="nav-item">

                            <a
                                href="<?= BASE_URL; ?>/modules/exams/index.php"
                                class="nav-link"
                            >

                                <i class="far fa-circle nav-icon"></i>

                                <p>
                                    Marks Entry
                                </p>

                            </a>

                        </li>

                        <li class="nav-item">
                            <a href="<?= BASE_URL; ?>/modules/exams/types.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Exam Types</p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="<?= BASE_URL; ?>/modules/exams/subjects.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Subjects</p>
                            </a>
                        </li>
                        <?php if (!function_exists('access_role') || access_role() === 'Admin'): ?>
                        <li class="nav-item">
                            <a href="<?= BASE_URL; ?>/modules/results/report_subjects.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i><p>Report Card Subjects</p>
                            </a>
                        </li>
                        <?php endif; ?>


                        <!-- Import Marks -->

                        <li class="nav-item">

                            <a
                                href="<?= BASE_URL; ?>/modules/exams/import_marks.php"
                                class="nav-link"
                            >

                                <i class="far fa-circle nav-icon"></i>

                                <p>
                                    Import Marks
                                </p>

                            </a>

                        </li>


                        <!-- Result Generator -->

                        <li class="nav-item">

                            <a
                                href="<?= BASE_URL; ?>/modules/results/index.php"
                                class="nav-link"
                            >

                                <i class="far fa-circle nav-icon"></i>

                                <p>
                                    Result Generator
                                </p>

                            </a>

                        </li>


                        <!-- HPC Report Card -->

                        <li class="nav-item">

                            <a
                                href="<?= BASE_URL; ?>/modules/results/hpc_report_selector.php"
                                class="nav-link"
                            >

                                <i class="far fa-circle nav-icon"></i>

                                <p>
                                    HPC Report Card
                                </p>

                            </a>

                        </li>


                    </ul>

                </li><?php endif; ?>


                <!-- =================================================
                     REPORTS
                     ================================================= -->

                <li class="nav-item">

                    <a
                        href="<?= BASE_URL; ?>/modules/reports/index.php"
                        class="nav-link"
                    >

                        <i class="nav-icon fas fa-chart-bar"></i>

                        <p>
                            Reports
                        </p>

                    </a>

                </li>


                <!-- =================================================
                     SETTINGS
                     ================================================= -->

                <li class="nav-item">

                    <a
                        href="<?= BASE_URL; ?>/modules/settings/index.php"
                        class="nav-link"
                    >

                        <i class="nav-icon fas fa-cogs"></i>

                        <p>
                            Settings
                        </p>

                    </a>

                </li>


            </ul>

        </nav>

    </div>

</aside>