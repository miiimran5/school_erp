<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
require_once '../../includes/school.php';
access_require_roles(array('Admin'));
$conn->set_charset('utf8mb4');
$error = ''; $message = '';
$keys = array('school_name','school_tagline','school_address','school_phone','school_email','school_timezone','academic_session');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = array();
    foreach ($keys as $key) { $posted[$key] = trim((string)($_POST[$key] ?? '')); }
    if ($posted['school_name'] === '') {
        $error = 'School name is required.';
    } elseif ($posted['school_email'] !== '' && !filter_var($posted['school_email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid school email address.';
    } elseif (!in_array($posted['school_timezone'], timezone_identifiers_list(), true)) {
        $error = 'Please select a valid timezone.';
    } else {
        $stmt = $conn->prepare('INSERT INTO school_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        foreach ($posted as $key => $value) {
            $stmt->bind_param('ss', $key, $value);
            $stmt->execute();
        }
        $stmt->close();
        if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowed = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
            $mime = function_exists('mime_content_type') ? mime_content_type($_FILES['school_logo']['tmp_name']) : '';
            if ($_FILES['school_logo']['error'] !== UPLOAD_ERR_OK || !isset($allowed[$mime]) || $_FILES['school_logo']['size'] > 2097152) {
                $error = 'Logo must be JPG, PNG, or WEBP and no larger than 2 MB.';
            } else {
                $filename = 'school_logo.' . $allowed[$mime];
                $directory = __DIR__ . '/../../uploads/school';
                if (!is_dir($directory)) { mkdir($directory, 0755, true); }
                if (move_uploaded_file($_FILES['school_logo']['tmp_name'], $directory . '/' . $filename)) {
                    $stmt = $conn->prepare('INSERT INTO school_settings (setting_key, setting_value) VALUES ("school_logo", ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
                    $stmt->bind_param('s', $filename); $stmt->execute(); $stmt->close();
                } else { $error = 'Unable to save the school logo.'; }
            }
        }
        if ($error === '') { $message = 'School settings saved successfully.'; }
    }
    require_once '../../includes/school.php';
}
$settings = $GLOBALS['school_settings'];
?>
<?php include '../../includes/header.php'; ?><?php include '../../includes/navbar.php'; ?><?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1><i class="fas fa-school"></i> School Settings</h1></div></section><section class="content"><div class="container-fluid">
<?php if ($message): ?><div class="alert alert-success"><?= school_e($message); ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= school_e($error); ?></div><?php endif; ?>
<div class="alert alert-info">Set these values once for each school installation. The portal branding, browser title, login page, print headers, and mobile app will use the saved values.</div>
<form method="post" enctype="multipart/form-data"><div class="card"><div class="card-header bg-primary text-white">School Identity</div><div class="card-body"><div class="row">
<?php foreach (array('school_name'=>'School Name','school_tagline'=>'Tagline','school_phone'=>'Phone','school_email'=>'Email','academic_session'=>'Academic Session') as $key=>$label): ?><div class="form-group col-md-4"><label><?= school_e($label); ?></label><input name="<?= school_e($key); ?>" class="form-control" value="<?= school_e($settings[$key]); ?>" <?= $key==='school_name'?'required':''; ?>></div><?php endforeach; ?>
<div class="form-group col-md-4"><label>Timezone</label><select name="school_timezone" class="form-control"><?php foreach (array('Asia/Kolkata','Asia/Dubai','Asia/Dhaka','Asia/Karachi','UTC') as $zone): ?><option value="<?= school_e($zone); ?>" <?= $settings['school_timezone']===$zone?'selected':''; ?>><?= school_e($zone); ?></option><?php endforeach; ?></select></div>
<div class="form-group col-md-8"><label>Address</label><textarea name="school_address" class="form-control" rows="2"><?= school_e($settings['school_address']); ?></textarea></div>
<div class="form-group col-md-4"><label>School Logo</label><input type="file" name="school_logo" class="form-control-file" accept=".jpg,.jpeg,.png,.webp"><small class="text-muted">Maximum 2 MB.</small><?php if ($settings['school_logo']): ?><div class="mt-2"><img src="<?= BASE_URL; ?>/uploads/school/<?= school_e($settings['school_logo']); ?>" alt="School logo" style="max-height:60px"></div><?php endif; ?></div>
</div></div><div class="card-footer"><button class="btn btn-success"><i class="fas fa-save"></i> Save Settings</button></div></div></form>
</div></section></div><?php include '../../includes/footer.php'; ?>
