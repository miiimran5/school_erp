<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/employee_options.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }
if (!($conn instanceof mysqli)) { die('Database connection failed.'); }
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) { header('Location: index.php?error=invalid'); exit; }
$stmt = $conn->prepare('SELECT employee_code, full_name, role, gender, date_of_birth, mobile, alternate_mobile, email, designation, department, qualification, joining_date, address, photo, class_incharge_id, status FROM teachers WHERE id=?');
$stmt->bind_param('i',$id); $stmt->execute(); $form = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$form) { header('Location: index.php?error=notfound'); exit; }
function teacher_edit_e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_designation = trim((string)($_POST['designation'] ?? ''));
    $custom_designation = trim((string)($_POST['custom_designation'] ?? ''));
    foreach ($form as $key => $value) { if (isset($_POST[$key])) { $form[$key] = trim((string)$_POST[$key]); } }
    if ($posted_designation === 'Other') { $form['designation'] = $custom_designation; }
    if ($form['employee_code'] === '' || $form['full_name'] === '') { $error = 'Employee code and full name are required.'; }
    elseif ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) { $error = 'Please enter a valid email address.'; }
    elseif ($form['department'] !== '' && !in_array($form['department'], $employee_departments, true)) { $error = 'Please select a valid department.'; }
    elseif (!employee_option_is_valid($form['department'], $form['designation']) && !employee_custom_designation_is_valid($form['department'], $form['designation'])) { $error = 'Please select a valid designation for the selected department.'; }
    if ($error === '') {
        $photo = $form['photo'];
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK || $_FILES['photo']['size'] > 2097152 || @getimagesize($_FILES['photo']['tmp_name']) === false) { $error = 'Photo must be a valid image up to 2 MB.'; }
            else { $extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION)); if (!in_array($extension, array('jpg','jpeg','png','webp'), true)) { $error = 'Photo must be JPG, PNG, or WEBP.'; } else { $photo = 'employee_' . $id . '_' . time() . '.' . $extension; $target = __DIR__ . '/../../uploads/employees/' . $photo; if (!is_dir(dirname($target))) { mkdir(dirname($target), 0755, true); } if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target)) { $error = 'Unable to save the employee photo.'; } } }
        }
        if ($error !== '') { /* keep form visible */ } else {
        $stmt = $conn->prepare('UPDATE teachers SET employee_code=?, full_name=?, role=?, gender=NULLIF(?, \'\'), date_of_birth=NULLIF(?, \'\'), mobile=?, alternate_mobile=?, email=?, designation=?, department=?, qualification=?, joining_date=NULLIF(?, \'\'), address=?, photo=?, class_incharge_id=NULLIF(?, 0), status=? WHERE id=?');
        $status=(int)$form['status']; $class_incharge_id=(int)$form['class_incharge_id']; $stmt->bind_param('ssssssssssssssiii',$form['employee_code'],$form['full_name'],$form['role'],$form['gender'],$form['date_of_birth'],$form['mobile'],$form['alternate_mobile'],$form['email'],$form['designation'],$form['department'],$form['qualification'],$form['joining_date'],$form['address'],$photo,$class_incharge_id,$status,$id);
        if ($stmt->execute()) { header('Location: index.php?saved=1'); exit; }
        $error = $stmt->errno === 1062 ? 'Employee code already exists.' : $stmt->error; $stmt->close();
        }
    }
}
?>
<?php include '../../includes/header.php'; ?><?php include '../../includes/navbar.php'; ?><?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1>Edit Employee</h1></div></section><section class="content"><div class="container-fluid">
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= teacher_edit_e($error); ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="id" value="<?= $id; ?>"><div class="card"><div class="card-header bg-primary text-white">Employee Particulars</div><div class="card-body"><div class="row">
<?php foreach (array('full_name'=>'Full Name *','mobile'=>'Mobile Number','alternate_mobile'=>'Alternate Mobile Number','email'=>'Email','department'=>'Department','designation'=>'Designation','qualification'=>'Qualification','date_of_birth'=>'Date of Birth','joining_date'=>'Date of Joining') as $key => $label): ?><div class="form-group col-md-4"><label><?= $label; ?></label><?php if ($key === 'designation'): ?><select name="designation" id="designation" class="form-control" required><option value="">Select designation</option><?php foreach ($employee_designations as $department => $designations): ?><optgroup label="<?= teacher_edit_e($department); ?>"><?php foreach ($designations as $designation): ?><option value="<?= teacher_edit_e($designation); ?>" data-department="<?= teacher_edit_e($department); ?>" <?= $form['designation'] === $designation ? 'selected' : ''; ?>><?= teacher_edit_e($designation); ?></option><?php endforeach; ?><option value="Other" data-department="<?= teacher_edit_e($department); ?>" <?= $form['department'] === $department && $form['designation'] !== '' && !in_array($form['designation'], $designations, true) ? 'selected' : ''; ?>>Other</option></optgroup><?php endforeach; ?></select><input name="custom_designation" id="custom_designation" class="form-control mt-2" placeholder="Type designation" value="<?= teacher_edit_e($form['designation']); ?>" hidden><?php elseif ($key === 'department'): ?><select name="department" id="department" class="form-control" required><option value="">Select department</option><?php foreach ($employee_departments as $department): ?><option value="<?= teacher_edit_e($department); ?>" <?= $form['department'] === $department ? 'selected' : ''; ?>><?= teacher_edit_e($department); ?></option><?php endforeach; ?></select><?php else: ?><input name="<?= $key; ?>" type="<?= in_array($key,array('date_of_birth','joining_date'),true) ? 'date' : ($key === 'email' ? 'email' : 'text'); ?>" class="form-control" value="<?= teacher_edit_e($form[$key]); ?>" <?= $key === 'full_name' ? 'required' : ''; ?>><?php endif; ?></div><?php endforeach; ?>
<div class="form-group col-md-4"><label>Gender</label><select name="gender" class="form-control"><option value="">Select</option><?php foreach(array('Male','Female','Other') as $g): ?><option value="<?= $g; ?>" <?= $form['gender']===$g?'selected':''; ?>><?= $g; ?></option><?php endforeach; ?></select></div><div class="form-group col-md-4"><label>Class Incharge</label><select name="class_incharge_id" class="form-control"><option value="">No class assigned</option><?php $class_options = $conn->query('SELECT id, class_name FROM classes WHERE status = 1 ORDER BY class_order'); while ($class_option = $class_options ? $class_options->fetch_assoc() : null): ?><option value="<?= (int)$class_option['id']; ?>" <?= (int)$form['class_incharge_id'] === (int)$class_option['id'] ? 'selected' : ''; ?>><?= teacher_edit_e($class_option['class_name']); ?></option><?php endwhile; ?></select></div><div class="form-group col-md-4"><label>Photo</label><input type="file" name="photo" class="form-control-file" accept=".jpg,.jpeg,.png,.webp"><?php if ($form['photo'] !== ''): ?><small class="text-muted">Current photo: <?= teacher_edit_e($form['photo']); ?></small><?php endif; ?></div><div class="form-group col-md-12"><label>Address</label><textarea name="address" class="form-control" rows="3"><?= teacher_edit_e($form['address']); ?></textarea></div>
</div></div><div class="card-footer"><button class="btn btn-success">Update Employee</button> <a href="index.php" class="btn btn-secondary">Cancel</a></div></div></form>
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
