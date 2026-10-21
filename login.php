<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/school.php';

$message = "";
$school_options = array();
$school_registry_available = false;
$school_lookup = $master_conn->query("SHOW TABLES LIKE 'school_portal_schools'");
if ($school_lookup && $school_lookup->num_rows > 0) {
    $school_registry_available = true;
    $school_result = $master_conn->query("SELECT school_code, school_name FROM school_portal_schools WHERE status='Active' ORDER BY is_primary DESC, school_name");
    if ($school_result) {
        while ($school_row = $school_result->fetch_assoc()) {
            $school_options[] = $school_row;
        }
    }
}

if (isset($_POST['login'])) {
    $username = trim((string)($_POST['username'] ?? ''));
    $password_input = (string)($_POST['password'] ?? '');
    $school_code = strtoupper(trim((string)($_POST['school_code'] ?? '')));
    $login_conn = $master_conn;
    $school_valid = true;

    if ($school_registry_available) {
        $school_stmt = $master_conn->prepare("SELECT database_name FROM school_portal_schools WHERE school_code=? AND status='Active' LIMIT 1");
        if (!$school_stmt) {
            $school_valid = false;
            $message = 'School login is unavailable. Ask the Super Admin to check the database setup.';
        } else {
            $school_stmt->bind_param('s', $school_code);
            $school_stmt->execute();
            $school_record = $school_stmt->get_result()->fetch_assoc();
            $school_stmt->close();
            if (!$school_record || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $school_record['database_name'])) {
                $school_valid = false;
                $message = 'Select an active school.';
            } elseif ($school_record['database_name'] !== DB_NAME) {
                try {
                    $login_conn = erp_open_database($school_record['database_name']);
                } catch (Throwable $exception) {
                    error_log('School login database connection failed: ' . $exception->getMessage());
                    $school_valid = false;
                    $message = 'This school database is unavailable. Contact the Super Admin.';
                }
            }
        }
    }

    $user = null;
    if ($school_valid) {
        $conn = $login_conn;
        $stmt = $conn->prepare('SELECT id, role_id, full_name, username, password, account_type, linked_id FROM users WHERE username=? AND status=1 LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $username);
            if ($stmt->execute()) {
                $candidate = $stmt->get_result()->fetch_assoc();
                if ($candidate) {
                    $password_info = password_get_info($candidate['password']);
                    if ($password_info['algo'] !== null) {
                        if (password_verify($password_input, $candidate['password'])) {
                            $user = $candidate;
                        }
                    } elseif (hash_equals((string)$candidate['password'], md5($password_input))) {
                        $user = $candidate;
                        $upgraded_password = password_hash($password_input, PASSWORD_DEFAULT);
                        $upgrade = $conn->prepare('UPDATE users SET password=? WHERE id=?');
                        if ($upgrade) {
                            $upgrade->bind_param('si', $upgraded_password, $candidate['id']);
                            $upgrade->execute();
                            $upgrade->close();
                        }
                    }
                }
            }
            $stmt->close();
        }
        if (!$user) {
            $stmt = $conn->prepare("SELECT s.id, s.admission_no AS username, s.student_name AS full_name, s.dob FROM students s LEFT JOIN users u ON u.account_type='Student' AND u.linked_id=s.id WHERE s.admission_no=? AND s.status=1 AND u.id IS NULL LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $student = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($student && hash_equals(md5(date('dmY', strtotime($student['dob']))), md5($password_input))) {
                    $user = array('id' => 0, 'role_id' => 7, 'full_name' => $student['full_name'], 'username' => $student['username'], 'account_type' => 'Student', 'linked_id' => $student['id']);
                }
            }
        }
        if (!$user) {
            $stmt = $conn->prepare("SELECT t.id, t.mobile AS username, t.full_name, t.date_of_birth FROM teachers t LEFT JOIN users u ON u.account_type='Teacher' AND u.linked_id=t.id WHERE t.mobile=? AND t.status=1 AND t.mobile IS NOT NULL AND t.mobile <> '' AND u.id IS NULL LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $teacher = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($teacher && $teacher['date_of_birth'] && hash_equals(md5(date('dmY', strtotime($teacher['date_of_birth']))), md5($password_input))) {
                    $user = array('id' => 0, 'role_id' => 5, 'full_name' => $teacher['full_name'], 'username' => $teacher['username'], 'account_type' => 'Teacher', 'linked_id' => $teacher['id']);
                }
            }
        }
    }

    if ($user) {

        $account_type = (string)($user['account_type'] ?? '');
        if ((int)$user['role_id'] === 7) {
            $account_type = 'Student';
        } elseif ((int)$user['role_id'] === 5) {
            $account_type = 'Teacher';
        } elseif ($account_type === '') {
            $account_type = 'Admin';
        }
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role_id']   = $user['role_id'];
        $_SESSION['account_type'] = $account_type;
        $_SESSION['linked_id'] = $user['linked_id'] ?? null;
        $_SESSION['erp_school_code'] = $school_registry_available ? $school_code : '';

        header("Location: " . ($account_type === 'Student' ? 'modules/portal/student.php' : ($account_type === 'Teacher' ? 'modules/portal/teacher.php' : 'modules/dashboard/index.php')));
        exit;

    } elseif ($message === '') {

        $message = "Invalid Username or Password.";

    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= school_e(school_setting('app_name', APP_NAME)); ?> - Login</title>

<link rel="stylesheet" href="assets/css/app.css">

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:flex-end;
    padding:24px;
    background:
        linear-gradient(rgba(238,243,251,.28), rgba(238,243,251,.28)),
        url("<?= school_e(BASE_URL . '/uploads/branding/' . rawurlencode(basename(school_setting('login_background', 'educare-erp-login-background.png')))); ?>") center / cover fixed,
        #eef3fb;
}

.login-box{

    width:min(420px, 100%);

    margin:0 3vw 0 0;

    background:#ffffff;

    padding:42px 36px 32px;

    border-radius:18px;

    box-shadow:0 18px 55px rgba(15,23,42,.14);
    border:1px solid rgba(255,255,255,.85);

}

h2{

    text-align:center;

    margin-bottom:20px;

    color:#172033;
    font-size:25px;

}

.login-box h2::before {
    content:"";
    display:block;
    width:48px;
    height:6px;
    margin:0 auto 15px;
    border-radius:99px;
    background:linear-gradient(90deg,#2563eb,#14b8a6);

}

input{

    width:100%;

    padding:12px;

    margin-top:12px;

    border:1px solid #d8e0ec;
    border-radius:9px;

    font-size:15px;

}

button{

    width:100%;

    padding:13px 14px;

    margin-top:20px;

    background:linear-gradient(90deg,#2563eb,#1d4ed8);

    color:#fff;

    border:none;

    border-radius:9px;

    cursor:pointer;

    font-size:16px;
    font-weight:600;

}

button:hover{

    background:#1d4ed8;

}

.error{

    color:#b91c1c;
    background:#fef2f2;
    border-radius:8px;
    padding:10px;

    text-align:center;

    margin-top:15px;

}

.footer{

    text-align:center;

    margin-top:20px;

    color:#666;

    font-size:13px;

}

.version{

    text-align:center;

    margin-top:8px;

    color:#999;

    font-size:12px;

}

@media (max-width: 767.98px) {
    body {
        justify-content: center;
        padding: 16px;
        background-position: 30% center;
    }

    .login-box {
        margin: 0;
        padding: 32px 24px 26px;
    }
}

</style>

</head>

<body>

<div class="login-box">

    <img src="<?= school_e(BASE_URL . '/uploads/branding/' . rawurlencode(basename(school_setting('app_logo', 'educare-erp-logo.png')))); ?>" alt="<?= school_e(school_setting('app_name', APP_NAME)); ?>" style="display:block;max-width:190px;max-height:130px;object-fit:contain;margin:0 auto 16px;">
    <h2><?= school_e(school_setting('app_name', APP_NAME)); ?></h2>

    <?php if (!$school_registry_available && isset($_POST['login'])): ?>
        <div class="error">Multi-school login is not initialized. The Super Admin must import migration 016 into the primary database.</div>
    <?php endif; ?>

    <form method="post" autocomplete="off">

        <?php if ($school_registry_available): ?>
        <label for="school_code" class="sr-only">School</label>
        <select name="school_code" id="school_code" required style="width:100%; padding:12px; margin-top:12px; border:1px solid #d8e0ec; border-radius:9px; font-size:15px;">
            <option value="">Select school</option>
            <?php foreach ($school_options as $school_option): ?>
                <option value="<?= school_e($school_option['school_code']); ?>" <?= strtoupper((string)($_POST['school_code'] ?? '')) === $school_option['school_code'] ? 'selected' : ''; ?>>
                    <?= school_e($school_option['school_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <input
            type="text"
            name="username"
            placeholder="Username"
            required
            autofocus>

        <input
            type="password"
            name="password"
            placeholder="Password"
            required>

        <button
            type="submit"
            name="login">

            Login

        </button>

        <?php if($message!=""){ ?>

            <div class="error">

                <?php echo $message; ?>

            </div>

        <?php } ?>

    </form>

    <p class="small text-muted mt-3 mb-0">
        If no login account was created for you: students use their admission number and date of birth as DDMMYYYY; employees use their mobile number and date of birth as DDMMYYYY. Otherwise, use the login ID and password provided by the school administrator.
    </p>

    <div class="footer">

        <?= school_e(school_setting('app_name', APP_NAME)); ?>

    </div>

    <div class="version">

        Version 0.1.0

    </div>

</div>

</body>
</html>
