<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || !in_array((int)($_SESSION['role_id'] ?? 0), array(1, 2, 3), true)) { header('Location: ../../login.php'); exit; }
$conn->set_charset('utf8mb4');
function user_admin_e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$message = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = (string)($_POST['account_type'] ?? ''); $linked = (int)($_POST['linked_id'] ?? 0); $password = trim((string)($_POST['password'] ?? ''));
    if (!in_array($type, array('Student','Teacher'), true) || $linked <= 0) { $error = 'Select a valid student or employee.'; }
    else {
        if ($type === 'Student') { $stmt = $conn->prepare('SELECT admission_no AS username, student_name AS full_name, NULL AS email, NULL AS mobile FROM students WHERE id=? AND status=1'); $role = 7; }
        else { $stmt = $conn->prepare('SELECT mobile AS username, full_name, email, mobile FROM teachers WHERE id=? AND status=1 AND mobile IS NOT NULL AND mobile <> ""'); $role = 5; }
        $stmt->bind_param('i', $linked); $stmt->execute(); $person = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$person) { $error = 'The selected record is not active or has no login mobile number.'; }
        else {
            if ($password === '') {
                $date_sql = $type === 'Student' ? 'SELECT DATE_FORMAT(dob, "%d%m%Y") AS default_password FROM students WHERE id=?' : 'SELECT DATE_FORMAT(date_of_birth, "%d%m%Y") AS default_password FROM teachers WHERE id=?';
                $stmt = $conn->prepare($date_sql); $stmt->bind_param('i', $linked); $stmt->execute(); $date_row = $stmt->get_result()->fetch_assoc(); $password = (string)($date_row['default_password'] ?? ''); $stmt->close();
            }
            if ($password === '') { $error = 'Enter a password or complete the date of birth first.'; }
            else {
                $stmt = $conn->prepare('INSERT INTO users (role_id, account_type, linked_id, full_name, username, password, email, mobile, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), username=VALUES(username), password=VALUES(password), email=VALUES(email), mobile=VALUES(mobile), status=1');
                $hash = md5($password); $email = (string)($person['email'] ?? ''); $mobile = (string)($person['mobile'] ?? '');
                $stmt->bind_param('isisssss', $role, $type, $linked, $person['full_name'], $person['username'], $hash, $email, $mobile);
                if ($stmt->execute()) { $message = 'Login account saved. Username: ' . $person['username'] . ' (the password is not displayed for security).'; } else { $error = $stmt->error; } $stmt->close();
            }
        }
    }
}
$students = array(); $teachers = array(); $accounts = array();
$r = $conn->query('SELECT id, admission_no, student_name FROM students WHERE status=1 ORDER BY student_name'); while ($r && ($row=$r->fetch_assoc())) $students[]=$row;
$r = $conn->query('SELECT id, full_name, mobile FROM teachers WHERE status=1 AND mobile IS NOT NULL AND mobile <> "" ORDER BY full_name'); while ($r && ($row=$r->fetch_assoc())) $teachers[]=$row;
$r = $conn->query("SELECT account_type, linked_id, full_name, username, status FROM users WHERE account_type IN ('Student','Teacher') ORDER BY account_type, full_name"); while ($r && ($row=$r->fetch_assoc())) $accounts[]=$row;
?>
<?php include '../../includes/header.php'; ?><?php include '../../includes/navbar.php'; ?><?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1><i class="fas fa-key"></i> Login Accounts</h1></div></section><section class="content"><div class="container-fluid">
<?php if ($message): ?><div class="alert alert-success"><?= user_admin_e($message); ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= user_admin_e($error); ?></div><?php endif; ?>
<div class="alert alert-info">Students use Admission Number and date of birth as <strong>DDMMYYYY</strong>. Teachers use Mobile Number and date of birth as <strong>DDMMYYYY</strong>. Admin can override/reset a password below.</div>
<div class="card"><div class="card-header bg-primary text-white">Create or Reset Account</div><div class="card-body"><form method="post" class="row"><div class="form-group col-md-3"><label>Account Type</label><select name="account_type" id="account_type" class="form-control" required><option value="Student">Student</option><option value="Teacher">Employee / Teacher</option></select></div><div class="form-group col-md-5"><label>Person</label><select name="linked_id" id="linked_id" class="form-control" required></select></div><div class="form-group col-md-4"><label>New Password <small>(blank = DOB DDMMYYYY)</small></label><input type="text" name="password" class="form-control"></div><div class="col-12"><button class="btn btn-success"><i class="fas fa-save"></i> Save / Reset Login</button></div></form></div></div>
<div class="card"><div class="card-header bg-info text-white">Existing Student and Teacher Accounts</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-bordered mb-0"><thead><tr><th>Type</th><th>Name</th><th>Login ID</th><th>Status</th></tr></thead><tbody><?php foreach($accounts as $account): ?><tr><td><?= user_admin_e($account['account_type']); ?></td><td><?= user_admin_e($account['full_name']); ?></td><td><?= user_admin_e($account['username']); ?></td><td><?= (int)$account['status'] ? 'Active' : 'Disabled'; ?></td></tr><?php endforeach; ?><?php if (!$accounts): ?><tr><td colspan="4" class="text-center text-muted">No manually provisioned accounts. Default credentials remain available.</td></tr><?php endif; ?></tbody></table></div></div></div>
<script>
var people={Student:<?= json_encode($students); ?>,Teacher:<?= json_encode($teachers); ?>};var type=document.getElementById('account_type'),person=document.getElementById('linked_id');function loadPeople(){person.innerHTML='<option value="">Select person</option>';people[type.value].forEach(function(item){var o=document.createElement('option');o.value=item.id;o.textContent=type.value==='Student'?item.student_name+' - '+item.admission_no:item.full_name+' - '+item.mobile;person.appendChild(o);});}type.addEventListener('change',loadPeople);loadPeople();
</script></div></section></div><?php include '../../includes/footer.php'; ?>
