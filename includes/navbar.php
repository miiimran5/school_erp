<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">

    <!-- Left navbar links -->
    <ul class="navbar-nav">

        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                <i class="fas fa-bars"></i>
            </a>
        </li>

        <li class="nav-item d-none d-sm-inline-block">
            <a href="#" class="nav-link">Dashboard</a>
        </li>

    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">

        <?php
        $mobile_app_url = BASE_URL . '/login.php';
        $android_app_url = $mobile_app_url . '?mobile=android';
        $ios_app_url = $mobile_app_url . '?mobile=ios';
        $android_qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=8&data=' . rawurlencode($android_app_url);
        $ios_qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=8&data=' . rawurlencode($ios_app_url);
        ?>
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#" title="Install mobile app">
                <i class="fas fa-mobile-alt"></i> <span class="d-none d-md-inline">Mobile App</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right p-3" style="min-width:320px;">
                <h6 class="dropdown-header px-0"><i class="fas fa-download mr-1"></i>Install School ERP</h6>
                <div class="d-flex border-bottom pb-3 mb-3">
                    <img src="<?= htmlspecialchars($android_qr_url, ENT_QUOTES, 'UTF-8'); ?>" width="100" height="100" alt="Android QR code">
                    <div class="ml-2 small">
                        <strong><i class="fab fa-android text-success mr-1"></i>Android</strong>
                        <p class="mb-1">Scan, then choose <b>Install app</b> or <b>Add to Home Screen</b>.</p>
                        <a href="<?= htmlspecialchars($android_app_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Open Android link</a>
                    </div>
                </div>
                <div class="d-flex">
                    <img src="<?= htmlspecialchars($ios_qr_url, ENT_QUOTES, 'UTF-8'); ?>" width="100" height="100" alt="iOS QR code">
                    <div class="ml-2 small">
                        <strong><i class="fab fa-apple mr-1"></i>iPhone / iPad (iOS)</strong>
                        <p class="mb-1">Scan in Safari, tap <b>Share</b>, then <b>Add to Home Screen</b>.</p>
                        <a href="<?= htmlspecialchars($ios_app_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Open iOS link</a>
                    </div>
                </div>
                <small class="text-muted d-block mt-3">Both links open the same live ERP and stay synchronized.</small>
            </div>
        </li>

        <li class="nav-item">
            <a href="<?= BASE_URL; ?>/logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>

    </ul>

</nav>
<!-- /.navbar -->