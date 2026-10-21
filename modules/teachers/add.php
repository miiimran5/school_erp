<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/employee_options.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }
if (!($conn instanceof mysqli)) { die('Database connection failed.'); }
$conn->set_charset('utf8mb4');
function teacher_form_e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$form = array('employee_code'=>'','full_name'=>'','role'=>'','gender'=>'','date_of_birth'=>'','mobile'=>'','alternate_mobile'=>'','email'=>'','designation'=>'','custom_designation'=>'','department'=>'','qualification'=>'','joining_date'=>'','address'=>'','class_incharge_id'=>'','class_incharge_section_id'=>'','status'=>'1');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $value) { $form[$key] = trim((string)($_POST[$key] ?? $value)); }
    if ($form['designation'] === 'Other') { $form['designation'] = $form['custom_designation']; }
    $allowed = array('Male','Female','Other');
    if ($form['full_name'] === '' || $form['mobile'] === '' || $form['date_of_birth'] === '') { $error = 'Full name, mobile number, and date of birth are required.'; }
    elseif (strlen($form['employee_code']) > 50 || strlen($form['full_name']) > 120 || strlen($form['mobile']) > 20) { $error = 'Employee code, full name, or mobile number is too long.'; }
    elseif ($form['gender'] !== '' && !in_array($form['gender'], $allowed, true)) { $error = 'Please select a valid gender.'; }
    elseif ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) { $error = 'Please enter a valid email address.'; }
    elseif ($form['department'] !== '' && !in_array($form['department'], $employee_departments, true)) { $error = 'Please select a valid department.'; }
    elseif ($form['designation'] !== '' && !employee_option_is_valid($form['department'], $form['designation']) && !employee_custom_designation_is_valid($form['department'], $form['designation'])) { $error = 'Please select a valid designation for the selected department.'; }
    elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['date_of_birth']) || !($date_of_birth = DateTime::createFromFormat('!Y-m-d', $form['date_of_birth'])) || $date_of_birth->format('Y-m-d') !== $form['date_of_birth']) { $error = 'Enter a valid date of birth.'; }
    elseif ($form['joining_date'] !== '' && !DateTime::createFromFormat('!Y-m-d', $form['joining_date'])) { $error = 'Joining date must use YYYY-MM-DD.'; }
    elseif ((int)$form['class_incharge_section_id'] > 0 && (int)$form['class_incharge_id'] <= 0) { $error = 'Select a class before selecting its section.'; }
    if ($error === '') {
            if ($form['employee_code'] === '') { $form['employee_code'] = 'EMP-' . date('ymdHis') . '-' . mt_rand(100, 999); }
            if ((int)$form['class_incharge_id'] > 0) {
                $class_id = (int)$form['class_incharge_id'];
                $check = $conn->prepare('SELECT 1 FROM classes WHERE id=? AND status=1');
                $check->bind_param('i', $class_id);
                $check->execute();
                $valid_class = $check->get_result()->num_rows > 0;
                $check->close();
                if (!$valid_class) { $error = 'Select a valid active class for the class in-charge assignment.'; }
            }
            if ($error === '' && (int)$form['class_incharge_section_id'] > 0) {
                $section_id = (int)$form['class_incharge_section_id'];
                $check = $conn->prepare('SELECT 1 FROM sections WHERE id=? AND status=1');
                $check->bind_param('i', $section_id);
                $check->execute();
                $valid_section = $check->get_result()->num_rows > 0;
                $check->close();
                if (!$valid_section) { $error = 'Select a valid active section.'; }
            }
            $photo = '';
            if ($error === '' && isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK || $_FILES['photo']['size'] > 2097152) { $error = 'Photo must be a JPG, PNG, or WEBP image up to 2 MB.'; }
                elseif (@getimagesize($_FILES['photo']['tmp_name']) === false) { $error = 'Please upload a valid image file.'; }
                else { $extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION)); if (!in_array($extension, array('jpg','jpeg','png','webp'), true)) { $error = 'Photo must be JPG, PNG, or WEBP.'; } else { $photo = 'employee_' . date('ymdHis') . '_' . mt_rand(100, 999) . '.' . $extension; $target = __DIR__ . '/../../uploads/employees/' . $photo; if (!is_dir(dirname($target))) { mkdir(dirname($target), 0755, true); } if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target)) { $error = 'Unable to save the employee photo.'; } } }
            }
            if ($error === '') {
            $stmt = $conn->prepare('INSERT INTO teachers (employee_code, full_name, role, gender, date_of_birth, mobile, alternate_mobile, email, designation, department, qualification, joining_date, address, photo, class_incharge_id, class_incharge_section_id, status) VALUES (?, ?, ?, NULLIF(?, \'\'), ?, ?, ?, ?, ?, ?, ?, NULLIF(?, \'\'), ?, ?, NULLIF(?, 0), NULLIF(?, 0), ?)');
        if (!$stmt) { $error = $conn->error; } else {
            $status = (int)$form['status'];
            $class_incharge_id = (int)$form['class_incharge_id'];
            $class_incharge_section_id = (int)$form['class_incharge_section_id'];
            $stmt->bind_param('ssssssssssssssiii', $form['employee_code'],$form['full_name'],$form['role'],$form['gender'],$form['date_of_birth'],$form['mobile'],$form['alternate_mobile'],$form['email'],$form['designation'],$form['department'],$form['qualification'],$form['joining_date'],$form['address'],$photo,$class_incharge_id,$class_incharge_section_id,$status);
            if ($stmt->execute()) { header('Location: index.php?saved=1'); exit; }
            $error = $stmt->errno === 1062 ? 'Employee code already exists. Please submit again.' : $stmt->error; $stmt->close();
        }
        }
    }
}
?>
<?php include '../../includes/header.php'; ?><?php include '../../includes/navbar.php'; ?><?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1>Add Employee</h1></div></section><section class="content"><div class="container-fluid">
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= teacher_form_e($error); ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data"><div class="card"><div class="card-header bg-primary text-white">Employee Particulars</div><div class="card-body"><div class="row">
<div class="form-group col-md-4"><label for="employee_code">Employee Code (CBSE OASIS ID)</label><input name="employee_code" id="employee_code" maxlength="50" class="form-control" value="<?= teacher_form_e($form['employee_code']); ?>"><small class="form-text text-muted">Optional. A code will be generated if left blank.</small></div>
<?php foreach (array('full_name'=>'Full Name *','mobile'=>'Mobile Number *','alternate_mobile'=>'Alternate Mobile Number','email'=>'Email','department'=>'Department','designation'=>'Designation','qualification'=>'Qualification','date_of_birth'=>'Date of Birth *','joining_date'=>'Date of Joining') as $key => $label): ?><div class="form-group col-md-4"><label><?= $label; ?></label><?php if ($key === 'designation'): ?><select name="designation" id="designation" class="form-control"><option value="">Select designation</option><?php foreach ($employee_designations as $department => $designations): ?><optgroup label="<?= teacher_form_e($department); ?>"><?php foreach ($designations as $designation): ?><option value="<?= teacher_form_e($designation); ?>" data-department="<?= teacher_form_e($department); ?>" <?= $form['designation'] === $designation ? 'selected' : ''; ?>><?= teacher_form_e($designation); ?></option><?php endforeach; ?><option value="Other" data-department="<?= teacher_form_e($department); ?>">Other</option></optgroup><?php endforeach; ?></select><input name="custom_designation" id="custom_designation" class="form-control mt-2" placeholder="Type designation" value="" hidden><?php elseif ($key === 'department'): ?><select name="department" id="department" class="form-control"><option value="">Select department</option><?php foreach ($employee_departments as $department): ?><option value="<?= teacher_form_e($department); ?>" <?= $form['department'] === $department ? 'selected' : ''; ?>><?= teacher_form_e($department); ?></option><?php endforeach; ?></select><?php else: ?><input name="<?= $key; ?>" type="<?= in_array($key,array('date_of_birth','joining_date'),true) ? 'date' : ($key === 'email' ? 'email' : 'text'); ?>" class="form-control" value="<?= teacher_form_e($form[$key]); ?>" <?= in_array($key,array('full_name','mobile','date_of_birth'),true) ? 'required' : ''; ?>><?php endif; ?></div><?php endforeach; ?>
<div class="form-group col-md-4"><label>Gender</label><select name="gender" class="form-control"><option value="">Select</option><option <?= $form['gender']==='Male'?'selected':''; ?>>Male</option><option <?= $form['gender']==='Female'?'selected':''; ?>>Female</option><option <?= $form['gender']==='Other'?'selected':''; ?>>Other</option></select></div>
<div class="form-group col-md-4"><label>Class Incharge - Class</label><select name="class_incharge_id" class="form-control"><option value="">No class assigned</option><?php $class_options = $conn->query('SELECT id, class_name FROM classes WHERE status = 1 ORDER BY class_order'); while ($class_option = $class_options ? $class_options->fetch_assoc() : null): ?><option value="<?= (int)$class_option['id']; ?>"><?= teacher_form_e($class_option['class_name']); ?></option><?php endwhile; ?></select></div>
<div class="form-group col-md-4"><label>Class Incharge - Section</label><select name="class_incharge_section_id" class="form-control"><option value="">All sections / no section assigned</option><?php $section_options = $conn->query('SELECT id, section_name FROM sections WHERE status = 1 ORDER BY section_name, id'); while ($section_option = $section_options ? $section_options->fetch_assoc() : null): ?><option value="<?= (int)$section_option['id']; ?>"><?= teacher_form_e($section_option['section_name']); ?></option><?php endwhile; ?></select></div>
<div class="form-group col-md-4"><label>Photo</label><input type="file" name="photo" class="form-control-file" accept=".jpg,.jpeg,.png,.webp"><small class="text-muted">Maximum 2 MB.</small></div>
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
    custom.required = false;
});
document.getElementById('department').dispatchEvent(new Event('change'));
document.getElementById('designation').dispatchEvent(new Event('change'));
</script>
</div></section></div><?php include '../../includes/footer.php'; ?>
