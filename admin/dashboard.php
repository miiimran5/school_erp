<?php
session_start();

if(!isset($_SESSION['user_id']))
{
header("Location:../login.php");
exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Dashboard</title>
</head>

<body>

<h1>Welcome to Oasis Public School ERP</h1>

<h3><?php echo $_SESSION['full_name']; ?></h3>

<p>Dashboard is working successfully.</p>

<a href="../logout.php">Logout</a>

</body>
</html>