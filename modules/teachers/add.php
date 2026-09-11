<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/employee_options.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }
if (!($conn instanceof mysqli)) { die('Database connection failed.'); }
$conn->set_charset('utf8mb4');
function teacher_form_e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$form = array('employee_code'=>'','full_name'=>'','role'=>'','gender'=>'','date_of_birth'=>'','mobile'=>'','email'=>'','designation'=>'','custom_designation'=>'','department'=>'','qualification'=>'','joining_date'=>'','address'=>'','status'=>'1');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $value) { $form[$key] = trim((string)($_POST[$key] ?? $value)); }
    if ($form['designation'] === 'Other') { $form['designation'] = $form['custom_designation']; }
    $allowed = array('Male','Female','Other');
    if ($form['employee_code'] === '' || $form['full_name'] === '') { $error = 'Employee code and full name are required.'; }
    elseif ($form['gender'] !== '' && !in_array($form['gender'], $allowed, true)) { $error = 'Please select a valid gender.'; }
    elseif ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) { $error = 'Please enter a valid email address.'; }
    elseif ($form['department'] !== '' && !in_array($form['department'], $employee_departments, true)) { $error = 'Please select a valid department.'; }
    elseif (!employee_option_is_valid($form['department'], $form['designation']) && !employee_custom_designation_is_valid($form['department'], $form['designation'])) { $error = 'Please select a valid designation for the selected department.'; }
    elseif ($form['date_of_birth'] !== '' && !DateTime::createFromFormat('!Y-m-d', $form['date_of_birth'])) { $error = 'Date of birth must use YYYY-MM-DD.'; }
    elseif ($form['joining_date'] !== '' && !DateTime::createFromFormat('!Y-m-d', $form['joining_date'])) { $error = 'Joining date must use YYYY-MM-DD.'; }
    if ($error === '') {
        $stmt = $conn->prepare('INSERT INTO teachers (employee_code, full_name, role, gender, date_of_birth, mobile, email, designation, department, qualification, joining_date, address, status) VALUES (?, ?, ?, NULLIF(?, \'\'), NULLIF(?, \'\'), ?, ?, ?, ?, ?, NULLIF(?, \'\'), ?, ?)');
        if (!$stmt) { $error = $conn->error; } else {
            $status = (int)$form['status'];
            $stmt->bind_param('ssssssssssssi', $form['employee_code'],$form['full_name'],$form['role'],$form['gender'],$form['date_of_birth'],$form['mobile'],$form['email'],$form['designation'],$form['department'],$form['qualification'],$form['joining_date'],$form['address'],$status);
            if ($stmt->execute()) { header('Location: index.php?saved=1'); exit; }
            $error = $stmt->errno === 1062 ? 'Employee code already exists.' : $stmt->error; $stmt->close();
        }
    }
}
?>
<?php include '../../includes/header.php'; ?><?php include '../../includes/navbar.php'; ?><?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1>Add Employee</h1></div></section><section class="content"><div class="container-fluid">
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= teacher_form_e($error); ?></div><?php endif; ?>
<form method="post"><div class="card"><div class="card-header bg-primary text-white">Employee Particulars</div><div class="card-body"><div class="row">
<?php foreach (array('employee_code'=>'Employee Code *','full_name'=>'Full Name *','role'=>'Role','mobile'=>'Mobile','email'=>'Email','designation'=>'Designation','department'=>'Department','qualification'=>'Qualification','date_of_birth'=>'Date of Birth','joining_date'=>'Joining Date') as $key => $label): ?><div class="form-group col-md-4"><label><?= $label; ?></label><?php if ($key === 'designation'): ?><select name="designation" id="designation" class="form-control" required><option value="">Select designation</option><?php foreach ($employee_designations as $department => $designations): ?><optgroup label="<?= teacher_form_e($department); ?>"><?php foreach ($designations as $designation): ?><option value="<?= teacher_form_e($designation); ?>" data-department="<?= teacher_form_e($department); ?>" <?= $form['designation'] === $designation ? 'selected' : ''; ?>><?= teacher_form_e($designation); ?></option><?php endforeach; ?><option value="Other" data-department="<?= teacher_form_e($department); ?>" <?= $form['department'] === $department && $form['designation'] !== '' && !in_array($form['designation'], $designations, true) ? 'selected' : ''; ?>>Other</option></optgroup><?php endforeach; ?></select><input name="custom_designation" id="custom_designation" class="form-control mt-2" placeholder="Type designation" value="<?= teacher_form_e($form['designation']); ?>" hidden><?php elseif ($key === 'department'): ?><select name="department" id="department" class="form-control" required><option value="">Select department</option><?php foreach ($employee_departments as $department): ?><option value="<?= teacher_form_e($department); ?>" <?= $form['department'] === $department ? 'selected' : ''; ?>><?= teacher_form_e($department); ?></option><?php endforeach; ?></select><?php else: ?><input name="<?= $key; ?>" type="<?= in_array($key,array('date_of_birth','joining_date'),true) ? 'date' : ($key === 'email' ? 'email' : 'text'); ?>" class="form-control" value="<?= teacher_form_e($form[$key]); ?>" <?= in_array($key,array('employee_code','full_name'),true) ? 'required' : ''; ?>><?php endif; ?></div><?php endforeach; ?>
<div class="form-group col-md-4"><label>Gender</label><select name="gender" class="form-control"><option value="">Select</option><option <?= $form['gender']==='Male'?'selected':''; ?>>Male</option><option <?= $form['gender']==='Female'?'selected':''; ?>>Female</option><option <?= $form['gender']==='Other'?'selected':''; ?>>Other</option></select></div>
<div class="form-group col-md-12"><label>Address</label><textarea name="address" class="form-control" rows="3"><?= teacher_form_e($form['address']); ?></textarea></div>
</div></div><div class="card-footer"><button class="btn btn-success">Save Employee</button> <a href="index.php" class="btn btn-secondary">Cancel</a></div></div></form>
<script>
document.getElementById('department').addEventListener('change', function () {
    var department = this.value;
    var designation = document.getElementById('designation');
    Array.prototype.forEach.call(designation.options, function (option) {
        option.hidden = option.value !== '' && option.getAttribute('data-department') !== department;
    });
    if (designation.selectedOptions.length && designation.selectedOptions[0].getAttribute('data-department') !== department) {
        designation.value = '';
    }
    designation.dispatchEvent(new Event('change'));
});
document.getElementById('designation').addEventListener('change', function () {
    var custom = document.getElementById('custom_designation');
    custom.hidden = this.value !== 'Other';
    custom.required = this.value === 'Other';
});
document.getElementById('department').dispatchEvent(new Event('change'));
document.getElementById('designation').dispatchEvent(new Event('change'));
</script>
</div></section></div><?php include '../../includes/footer.php'; ?>
