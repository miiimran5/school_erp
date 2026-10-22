<!-- Main Sidebar Container -->
<?php require_once __DIR__ . '/access.php'; ?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">

    <!-- Brand Logo -->
    <a href="<?= BASE_URL; ?>/modules/dashboard/index.php"
       class="brand-link">

        <img src="<?= BASE_URL; ?>/uploads/branding/<?= rawurlencode(basename(school_setting('app_logo', 'educare-erp-logo.png'))); ?>" alt="<?= school_e(school_setting('app_name', APP_NAME)); ?>" class="brand-image img-circle elevation-3" style="opacity:.9; object-fit:contain; background:#fff;">
        <span class="brand-text font-weight-light"><?= school_e(school_setting('app_name', APP_NAME)); ?></span>

    </a>


    <!-- Sidebar -->
    <div class="sidebar">


        <!-- User Panel -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">

            <div class="image">
                <i class="fas fa-user-circle fa-2x text-white"></i>
            </div>

            <div class="info">

                <?php if ((int)($_SESSION['user_id'] ?? 0) > 0): ?>
                    <a href="<?= BASE_URL; ?>/modules/users/profile.php" class="d-block">
                        <?= htmlspecialchars($_SESSION['full_name'] ?? 'Administrator', ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    <a href="<?= BASE_URL; ?>/modules/users/profile.php" class="small text-info">Edit profile</a>
                <?php else: ?>
                    <span class="d-block text-white"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>

            </div>

        </div>

        <div class="form-group px-2 mb-2">
            <label class="sr-only" for="sidebar-search">Search menu</label>
            <div class="input-group input-group-sm">
                <input type="search" id="sidebar-search" class="form-control" placeholder="Search menu..." autocomplete="off" data-sidebar-search>
                <div class="input-group-append"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
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
                <?php if (access_is_parent()): ?>
                <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/portal/parent.php" class="nav-link"><i class="nav-icon fas fa-users"></i><p>Family Portal</p></a></li>
                <?php elseif (access_is_student()): ?>
                <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/portal/student.php" class="nav-link"><i class="nav-icon fas fa-home"></i><p>My Student Portal</p></a></li>
                <?php elseif (access_is_teacher()): ?>
                <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/portal/teacher.php" class="nav-link"><i class="nav-icon fas fa-home"></i><p>Teacher Control Panel</p></a></li>
                <?php endif; ?>

                <?php if (access_feature_allowed($conn, 'announcements') || access_feature_allowed($conn, 'homework')): ?>
                <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/portal/announcements.php" class="nav-link"><i class="nav-icon fas fa-bullhorn"></i><p>Announcements</p></a></li>
                <?php endif; ?>
                <?php if (access_feature_allowed($conn, 'study_materials')): ?>
                <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/resources/index.php?type=Study%20Material" class="nav-link"><i class="nav-icon fas fa-book-open"></i><p>Study Materials</p></a></li>
                <?php endif; ?>
                <?php if (access_feature_allowed($conn, 'syllabus')): ?>
                <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/resources/index.php?type=Syllabus" class="nav-link"><i class="nav-icon fas fa-book"></i><p>Syllabus</p></a></li>
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

                <?php if (access_is_admin() || (access_is_teacher() && access_feature_allowed($conn, 'student_records'))): ?><li class="nav-item">

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

                <?php if (access_is_admin()): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL; ?>/modules/exams/promote_students.php" class="nav-link">
                        <i class="nav-icon fas fa-user-graduate"></i><p>Promote Students</p>
                    </a>
                </li>
                <?php endif; ?>


                <!-- =================================================
                     TEACHERS
                     ================================================= -->

                <?php if (access_is_admin()): ?><li class="nav-item">

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

                <?php if (!function_exists('access_role') || access_role() === 'Admin'): ?><li class="nav-item">
                    <a href="<?= BASE_URL; ?>/modules/teachers/assignments.php" class="nav-link">
                        <i class="nav-icon fas fa-user-tie"></i>
                        <p>Teacher Subjects & Classes</p>
                    </a>
                </li><?php endif; ?>

                <?php if (access_is_admin()): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL; ?>/modules/users/index.php" class="nav-link">
                        <i class="nav-icon fas fa-key"></i>
                        <p>Login Accounts</p>
                    </a>
                </li><?php endif; ?>
                <?php if ((int)($_SESSION['role_id'] ?? 0) === 1): ?><li class="nav-item">
                    <a href="<?= BASE_URL; ?>/modules/settings/index.php" class="nav-link">
                        <i class="nav-icon fas fa-user-shield"></i><p>Super Admin Portal</p>
                    </a>
                </li><?php endif; ?>
                <?php if (access_is_admin()): ?>
                <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/settings/feature_access.php" class="nav-link"><i class="nav-icon fas fa-user-lock"></i><p>Feature Access</p></a></li>
                <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/users/parent_accounts.php" class="nav-link"><i class="nav-icon fas fa-users-cog"></i><p>Parent Portal Accounts</p></a></li>
                <?php endif; ?>


                <!-- =================================================
                     ATTENDANCE
                     ================================================= -->

                <?php if (access_is_admin() || (access_is_teacher() && access_feature_allowed($conn, 'attendance'))): ?><li class="nav-item">

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
                <?php if (access_is_student() && access_feature_allowed($conn, 'attendance')): ?>
                <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/portal/student.php#attendance" class="nav-link"><i class="nav-icon fas fa-calendar-check"></i><p>My Attendance</p></a></li>
                <?php endif; ?>
                <?php if (access_feature_allowed($conn, 'timetable')): ?>
                <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/resources/index.php?type=Time%20Table" class="nav-link"><i class="nav-icon fas fa-clock"></i><p>Time Table</p></a></li>
                <?php endif; ?>
                <?php if ((access_is_student() || access_is_parent()) && access_feature_allowed($conn, 'date_sheets')): ?>
                <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/resources/index.php?type=Date%20Sheet" class="nav-link"><i class="nav-icon fas fa-calendar-alt"></i><p>Date Sheets</p></a></li>
                <?php endif; ?>


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

                <?php if (access_is_admin() || (access_is_teacher() && (access_feature_allowed($conn, 'exams') || access_feature_allowed($conn, 'reports') || access_feature_allowed($conn, 'hpc_reports')))): ?><li class="nav-item has-treeview">

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

                        <?php if (access_is_admin() || (access_is_teacher() && access_feature_allowed($conn, 'exams'))): ?><li class="nav-item">

                            <a
                                href="<?= BASE_URL; ?>/modules/exams/index.php"
                                class="nav-link"
                            >

                                <i class="far fa-circle nav-icon"></i>

                                <p>
                                    Marks Entry
                                </p>

                            </a>

                        </li><?php endif; ?>

                        <?php if (access_is_admin()): ?><li class="nav-item">
                            <a href="<?= BASE_URL; ?>/modules/exams/types.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Exam Types</p>
                            </a>
                        </li><?php endif; ?>

                        <?php if (access_is_admin()): ?><li class="nav-item">
                            <a href="<?= BASE_URL; ?>/modules/exams/subjects.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Subjects</p>
                            </a>
                        </li><?php endif; ?>
                        <?php if (!function_exists('access_role') || access_role() === 'Admin'): ?>
                        <?php if (access_is_admin()): ?><li class="nav-item">
                            <a href="<?= BASE_URL; ?>/modules/results/report_subjects.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i><p>Report Card Subjects</p>
                            </a>
                        </li><?php endif; ?>
                        <?php endif; ?>
                        <?php if (access_feature_allowed($conn, 'exams')): ?>
                        <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/exams/mark_status.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Marks Entry Status</p></a></li>
                        <?php endif; ?>
                        <?php if (access_is_admin()): ?>
                        <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/exams/grading.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Grading System</p></a></li>
                        <?php endif; ?>
                        <?php if (access_feature_allowed($conn, 'date_sheets')): ?>
                        <li class="nav-item"><a href="<?= BASE_URL; ?>/modules/resources/index.php?type=Date%20Sheet" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Date Sheets</p></a></li>
                        <?php endif; ?>


                        <!-- Import Marks -->

                        <?php if (access_feature_allowed($conn, 'exams')): ?><li class="nav-item">

                            <a
                                href="<?= BASE_URL; ?>/modules/exams/import_marks.php"
                                class="nav-link"
                            >

                                <i class="far fa-circle nav-icon"></i>

                                <p>
                                    Import Marks
                                </p>

                            </a>

                        </li><?php endif; ?>


                        <!-- Result Generator -->

                        <?php if (access_is_admin() || access_feature_allowed($conn, 'reports')): ?><li class="nav-item">

                            <a
                                href="<?= BASE_URL; ?>/modules/results/index.php"
                                class="nav-link"
                            >

                                <i class="far fa-circle nav-icon"></i>

                                <p>
                                    Result Generator
                                </p>

                            </a>

                        </li><?php endif; ?>


                        <!-- HPC Report Card -->

                        <?php if (access_is_admin() || access_feature_allowed($conn, 'hpc_reports')): ?><li class="nav-item">

                            <a
                                href="<?= BASE_URL; ?>/modules/results/hpc_report_selector.php"
                                class="nav-link"
                            >

                                <i class="far fa-circle nav-icon"></i>

                                <p>
                                    HPC Report Card
                                </p>

                            </a>

                        </li><?php endif; ?>


                    </ul>

                </li><?php endif; ?>


                <!-- =================================================
                     REPORTS
                     ================================================= -->

                <?php if (!function_exists('access_role') || access_role() === 'Admin'): ?><li class="nav-item">

                    <a
                        href="<?= BASE_URL; ?>/modules/reports/index.php"
                        class="nav-link"
                    >

                        <i class="nav-icon fas fa-chart-bar"></i>

                        <p>
                            Reports
                        </p>

                    </a>

                </li><?php endif; ?>


            </ul>

        </nav>

    </div>

</aside>
<script>
(function () {
    var menu = document.querySelector('.nav-sidebar');
    if (!menu) return;
    function sortItems(list, pinDashboard) {
        Array.prototype.slice.call(list.children).forEach(function (item) {
            var nested = item.querySelector(':scope > ul.nav-treeview');
            if (nested) sortItems(nested, false);
        });
        var items = Array.prototype.slice.call(list.children);
        items.sort(function (first, second) {
            var firstLabel = (first.querySelector(':scope > a p') || first).textContent.trim();
            var secondLabel = (second.querySelector(':scope > a p') || second).textContent.trim();
            if (pinDashboard && firstLabel === 'Dashboard') return -1;
            if (pinDashboard && secondLabel === 'Dashboard') return 1;
            return firstLabel.localeCompare(secondLabel, undefined, {sensitivity: 'base'});
        });
        items.forEach(function (item) { list.appendChild(item); });
    }
    sortItems(menu, true);
}());
</script>