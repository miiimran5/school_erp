<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/school.php';

$message = "";

if (isset($_POST['login'])) {
    $username = trim((string)($_POST['username'] ?? ''));
    $password_input = trim((string)($_POST['password'] ?? ''));
    $password = md5($password_input);
    $stmt = $conn->prepare('SELECT id, role_id, full_name, username, password, account_type, linked_id FROM users WHERE username=? AND password=? AND status=1 LIMIT 1');
    $stmt->bind_param('ss', $username, $password);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    /* Default credentials work for records that have not yet been provisioned:
       students: admission number / DDMMYYYY; teachers: mobile / DDMMYYYY. */
    if (!$user) {
        $stmt = $conn->prepare("SELECT s.id, s.admission_no AS username, s.student_name AS full_name, s.dob FROM students s LEFT JOIN users u ON u.account_type='Student' AND u.linked_id=s.id WHERE s.admission_no=? AND s.status=1 AND u.id IS NULL LIMIT 1");
        $stmt->bind_param('s', $username); $stmt->execute(); $student = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($student && md5($password_input) === md5(date('dmY', strtotime($student['dob'])))) {
            $user = array('id' => 0, 'role_id' => 7, 'full_name' => $student['full_name'], 'username' => $student['username'], 'account_type' => 'Student', 'linked_id' => $student['id']);
        }
    }
    if (!$user) {
        $stmt = $conn->prepare("SELECT t.id, t.mobile AS username, t.full_name, t.date_of_birth FROM teachers t LEFT JOIN users u ON u.account_type='Teacher' AND u.linked_id=t.id WHERE t.mobile=? AND t.status=1 AND t.mobile IS NOT NULL AND t.mobile <> '' AND u.id IS NULL LIMIT 1");
        $stmt->bind_param('s', $username); $stmt->execute(); $teacher = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($teacher && $teacher['date_of_birth'] && md5($password_input) === md5(date('dmY', strtotime($teacher['date_of_birth'])))) {
            $user = array('id' => 0, 'role_id' => 5, 'full_name' => $teacher['full_name'], 'username' => $teacher['username'], 'account_type' => 'Teacher', 'linked_id' => $teacher['id']);
        }
    }

    if ($user) {

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role_id']   = $user['role_id'];
        $_SESSION['account_type'] = $user['account_type'] ?? 'Admin';
        $_SESSION['linked_id'] = $user['linked_id'] ?? null;

        header("Location: " . (($user['account_type'] ?? 'Admin') === 'Student' ? 'modules/portal/student.php' : (($user['account_type'] ?? 'Admin') === 'Teacher' ? 'modules/portal/teacher.php' : 'modules/dashboard/index.php')));
        exit;

    } else {

        $message = "Invalid Username or Password.";

    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= school_e(school_setting('school_name', APP_NAME)); ?> - Login</title>

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
    justify-content:center;
    padding:24px;
    background:
        radial-gradient(circle at 15% 15%, rgba(37,99,235,.22), transparent 32%),
        radial-gradient(circle at 85% 85%, rgba(20,184,166,.18), transparent 32%),
        #eef3fb;
}

.login-box{

    width:380px;

    margin:0 auto;

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

</style>

</head>

<body>

<div class="login-box">

    <h2><?= school_e(school_setting('school_name', APP_NAME)); ?></h2>

    <form method="post" autocomplete="off">

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

    <div class="footer">

        Oasis Public School, Malerkotla

    </div>

    <div class="version">

        Version 0.1.0

    </div>

</div>

</body>
</html>
