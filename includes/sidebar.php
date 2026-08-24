<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">

    <!-- Brand Logo -->
    <a href="<?= BASE_URL; ?>/modules/dashboard/index.php"
       class="brand-link">

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


                <!-- =================================================
                     DASHBOARD
                     ================================================= -->

                <li class="nav-item">

                    <a
                        href="<?= BASE_URL; ?>/modules/dashboard/index.php"
                        class="nav-link"
                    >

                        <i class="nav-icon fas fa-tachometer-alt"></i>

                        <p>
                            Dashboard
                        </p>

                    </a>

                </li>


                <!-- =================================================
                     STUDENTS
                     ================================================= -->

                <li class="nav-item">

                    <a
                        href="<?= BASE_URL; ?>/modules/students/index.php"
                        class="nav-link"
                    >

                        <i class="nav-icon fas fa-user-graduate"></i>

                        <p>
                            Students
                        </p>

                    </a>

                </li>


                <!-- =================================================
                     TEACHERS
                     ================================================= -->

                <li class="nav-item">

                    <a
                        href="<?= BASE_URL; ?>/modules/teachers/index.php"
                        class="nav-link"
                    >

                        <i class="nav-icon fas fa-chalkboard-teacher"></i>

                        <p>
                            Teachers
                        </p>

                    </a>

                </li>


                <!-- =================================================
                     ATTENDANCE
                     ================================================= -->

                <li class="nav-item">

                    <a
                        href="<?= BASE_URL; ?>/modules/attendance/index.php"
                        class="nav-link"
                    >

                        <i class="nav-icon fas fa-calendar-check"></i>

                        <p>
                            Attendance
                        </p>

                    </a>

                </li>


                <!-- =================================================
                     FEES
                     ================================================= -->

                <li class="nav-item">

                    <a
                        href="<?= BASE_URL; ?>/modules/fees/index.php"
                        class="nav-link"
                    >

                        <i class="nav-icon fas fa-rupee-sign"></i>

                        <p>
                            Fees
                        </p>

                    </a>

                </li>


                <!-- =================================================
                     EXAMINATIONS
                     ================================================= -->

                <li class="nav-item has-treeview">

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
                                href="<?= BASE_URL; ?>/modules/results/generate.php"
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
                                href="<?= BASE_URL; ?>/modules/results/hpc_report.php"
                                class="nav-link"
                            >

                                <i class="far fa-circle nav-icon"></i>

                                <p>
                                    HPC Report Card
                                </p>

                            </a>

                        </li>


                    </ul>

                </li>


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