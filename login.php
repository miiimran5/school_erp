<?php
session_start();
include 'config/database.php';

$message = "";

if(isset($_POST['login']))
{
    $username = mysqli_real_escape_string($conn,$_POST['username']);
    $password = md5($_POST['password']);

    $query = mysqli_query($conn,"SELECT * FROM users WHERE username='$username' AND password='$password' AND status=1");

    if(mysqli_num_rows($query)>0)
    {
        $user=mysqli_fetch_assoc($query);

        $_SESSION['user_id']=$user['id'];
        $_SESSION['full_name']=$user['full_name'];
        $_SESSION['role_id']=$user['role_id'];

        header("Location: modules/dashboard/index.php");
        exit;
    }
    else
    {
        $message="Invalid Username or Password";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Oasis Public School ERP</title>

    <style>

body{
font-family:Arial;
background:#f1f1f1;
}

.login-box{
width:350px;
margin:100px auto;
background:#fff;
padding:25px;
box-shadow:0px 0px 10px #ccc;
border-radius:5px;
}

input{
width:100%;
padding:10px;
margin-top:10px;
}

button{
width:100%;
padding:10px;
margin-top:15px;
background:#007bff;
color:white;
border:none;
cursor:pointer;
}

.error{
color:red;
margin-top:10px;
}

h2{
text-align:center;
}

    </style>

</head>

<body>

<div class="login-box">

<h2>Oasis Public School ERP</h2>

<form method="post">

<input
type="text"
name="username"
placeholder="Username"
required>

<input
type="password"
name="password"
placeholder="Password"
required>

<button name="login">
Login
</button>

<div class="error">
<?php echo $message;?>
</div>

</form>

</div>

</body>
</html>