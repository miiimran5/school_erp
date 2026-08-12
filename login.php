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

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{

    font-family:Arial, Helvetica, sans-serif;
    background:#f4f6f9;
}

.login-box{

    width:380px;

    margin:100px auto;

    background:#ffffff;

    padding:30px;

    border-radius:8px;

    box-shadow:0 0 15px rgba(0,0,0,.15);

}

h2{

    text-align:center;

    margin-bottom:20px;

    color:#007bff;

}

input{

    width:100%;

    padding:12px;

    margin-top:12px;

    border:1px solid #ccc;

    border-radius:5px;

    font-size:15px;

}

button{

    width:100%;

    padding:12px;

    margin-top:20px;

    background:#007bff;

    color:#fff;

    border:none;

    border-radius:5px;

    cursor:pointer;

    font-size:16px;

}

button:hover{

    background:#0056b3;

}

.error{

    color:red;

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