<!-- Navbar -->
<?php require_once __DIR__ . '/access.php'; ?>
<nav class="main-header navbar navbar-expand navbar-white navbar-light">

    <!-- Left navbar links -->
    <ul class="navbar-nav">

        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                <i class="fas fa-bars"></i>
            </a>
        </li>

        <li class="nav-item d-none d-sm-inline-block">
            <a href="<?= BASE_URL; ?>/modules/dashboard/index.php" class="nav-link">Dashboard</a>
        </li>

    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">

        <?php
        $home_path = access_is_student()
            ? '/modules/portal/student.php'
            : (access_is_parent()
                ? '/modules/portal/parent.php'
                : (access_is_teacher() ? '/modules/portal/teacher.php' : '/modules/dashboard/index.php'));
        ?>
        <li class="nav-item">
            <a href="<?= BASE_URL . $home_path; ?>" class="nav-link" title="Home" aria-label="Home">
                <i class="fas fa-home"></i><span class="d-none d-sm-inline ml-1">Home</span>
            </a>
        </li>
        <li class="nav-item d-flex align-items-center mr-1">
            <label class="sr-only" for="global-menu-search">Search ERP pages</label>
            <div class="input-group input-group-sm global-search-group">
                <input type="search" class="form-control" id="global-menu-search" placeholder="Search pages..." autocomplete="off" data-global-search>
                <div class="input-group-append"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
            </div>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link btn btn-link" data-mobile-preview title="Toggle mobile layout preview">
                <i class="fas fa-mobile-alt"></i><span class="d-none d-lg-inline ml-1">Mobile view</span>
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link btn btn-link" data-theme-toggle title="Switch light/dark mode">
                <i class="fas fa-moon"></i><span class="d-none d-lg-inline ml-1">Theme</span>
            </button>
        </li>

        <?php
        $mobile_app_url = BASE_URL . '/login.php';
        $android_app_url = $mobile_app_url;
        $ios_app_url = $mobile_app_url;
        $android_qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=8&data=' . rawurlencode($android_app_url);
        $ios_qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=8&data=' . rawurlencode($ios_app_url);
        ?>
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#" title="Install mobile app">
                <i class="fas fa-mobile-alt"></i> <span class="d-none d-md-inline">Mobile App</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right p-3" style="min-width:320px;">
                <h6 class="dropdown-header px-0"><i class="fas fa-download mr-1"></i>Open School ERP on mobile</h6>
                <button type="button" class="btn btn-primary btn-sm btn-block mb-3" data-install-app>
                    <i class="fas fa-plus-square mr-1"></i>Install / Create shortcut
                </button>
                <p class="small text-muted">If the install button is not available: Android Chrome menu &gt; Add to Home screen. iPhone Safari Share &gt; Add to Home Screen.</p>
                <div class="d-flex border-bottom pb-3 mb-3">
                    <img src="<?= htmlspecialchars($android_qr_url, ENT_QUOTES, 'UTF-8'); ?>" width="100" height="100" alt="Android QR code">
                    <div class="ml-2 small">
                        <strong><i class="fab fa-android text-success mr-1"></i>Android web app</strong>
                        <p class="mb-1">Scan, then choose <b>Install app</b> or <b>Add to Home Screen</b>.</p>
                        <a href="<?= htmlspecialchars($android_app_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Open Android link</a>
                    </div>
                </div>
                <div class="d-flex">
                    <img src="<?= htmlspecialchars($ios_qr_url, ENT_QUOTES, 'UTF-8'); ?>" width="100" height="100" alt="iOS QR code">
                    <div class="ml-2 small">
                        <strong><i class="fab fa-apple mr-1"></i>iPhone / iPad web app</strong>
                        <p class="mb-1">Scan in Safari, tap <b>Share</b>, then <b>Add to Home Screen</b>.</p>
                        <a href="<?= htmlspecialchars($ios_app_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Open iOS link</a>
                    </div>
                </div>
                <small class="text-muted d-block mt-3">These are browser shortcuts to the live ERP, not downloadable APK or native iOS app files.</small>
            </div>
        </li>

        <?php
        $academic_sessions = array();
        $session_query = isset($conn) ? $conn->query("SELECT id, session_name FROM academic_sessions WHERE status=1 ORDER BY id DESC") : false;
        if ($session_query) {
            while ($session_row = $session_query->fetch_assoc()) {
                $academic_sessions[] = $session_row;
            }
        }
        if (!empty($academic_sessions)) {
            $active_academic_session = access_current_academic_session($conn);
            if (!isset($_SESSION['erp_session_change_token'])) {
                $_SESSION['erp_session_change_token'] = bin2hex(random_bytes(32));
            }
            $current_path = (string)($_SERVER['REQUEST_URI'] ?? '/');
        ?>
        <li class="nav-item d-flex align-items-center mr-1">
            <form method="post" action="<?= BASE_URL; ?>/modules/academic/change_session.php" class="form-inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['erp_session_change_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="return_to" value="<?= htmlspecialchars($current_path, ENT_QUOTES, 'UTF-8'); ?>">
                <label class="sr-only" for="academic-session-switcher">Change academic session</label>
                <select id="academic-session-switcher" name="session_id" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="Change academic session">
                    <?php foreach ($academic_sessions as $academic_session): ?>
                        <option value="<?= (int)$academic_session['id']; ?>" <?= (int)$academic_session['id'] === $active_academic_session ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($academic_session['session_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </li>
        <?php } ?>

        <li class="nav-item">
            <a href="<?= BASE_URL; ?>/logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> <span class="d-none d-sm-inline">Logout</span>
            </a>
        </li>

    </ul>

</nav>
<!-- /.navbar -->