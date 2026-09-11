<?php
session_start();
require_once 'config/database.php';

$message = "";

if (isset($_POST['login'])) {

    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = md5(trim($_POST['password']));

    $sql = "SELECT * FROM users
            WHERE username='$username'
            AND password='$password'
            AND status=1
            LIMIT 1";

    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) == 1) {

        $user = mysqli_fetch_assoc($result);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role_id']   = $user['role_id'];

        header("Location: modules/dashboard/index.php");
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

<title>Oasis Public School ERP - Login</title>

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

    <h2>Oasis Public School ERP</h2>

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