<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
require_once '../../includes/school.php';
access_require_login();

if (!access_is_super_admin() || $conn !== $master_conn || (string)($_SESSION['erp_school_code'] ?? '') !== ERP_SUPER_ADMIN_CODE) {
    http_response_code(403);
    exit('Only the Super Admin portal can edit school settings.');
}

function school_settings_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function school_settings_save_logo($upload, $school_code)
{
    if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ((int)$upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name']) || (int)$upload['size'] > 2097152) {
        throw new RuntimeException('School logo must be a valid JPG, PNG, or WEBP image no larger than 2 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $image = getimagesize($upload['tmp_name']);
    $extensions = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
    if ($image === false || !isset($extensions[$mime])) {
        throw new RuntimeException('Choose a JPG, PNG, or WEBP school logo.');
    }
    $directory = __DIR__ . '/../../uploads/school';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the school logo folder.');
    }
    $prefix = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$school_code);
    $filename = $prefix . '-school-logo-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($upload['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Unable to save the school logo.');
    }
    return $filename;
}

$school_id = (int)($_POST['school_id'] ?? $_GET['id'] ?? 0);
$school_stmt = $master_conn->prepare("SELECT id, school_code, school_name, database_name, status, is_primary FROM school_portal_schools WHERE id=? AND status IN ('Active','Inactive') LIMIT 1");
if (!$school_stmt) {
    http_response_code(500);
    exit('The school registry is unavailable.');
}
$school_stmt->bind_param('i', $school_id);
if (!$school_stmt->execute()) {
    http_response_code(500);
    exit('Unable to load the selected school.');
}
$school = $school_stmt->get_result()->fetch_assoc();
$school_stmt->close();
if (!$school || (int)$school['is_primary'] === 1 || $school['database_name'] === DB_CONTROL_NAME || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $school['database_name'])) {
    http_response_code(404);
    exit('The selected school was not found.');
}

try {
    $tenant = erp_open_database($school['database_name']);
} catch (Throwable $exception) {
    error_log('Unable to load tenant settings: ' . $exception->getMessage());
    http_response_code(503);
    exit('The selected school database is unavailable.');
}

if (empty($_SESSION['school_identity_csrf'])) {
    $_SESSION['school_identity_csrf'] = bin2hex(random_bytes(32));
}
$message = '';
$error = '';
$keys = array('school_name', 'school_address', 'school_phone', 'school_email', 'academic_session');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    $action = (string)($_POST['action'] ?? '');
    $values = array();
    foreach ($keys as $key) {
        $values[$key] = trim((string)($_POST[$key] ?? ''));
    }
    if (!hash_equals((string)$_SESSION['school_identity_csrf'], $token)) {
        $error = 'This form expired. Refresh the page and try again.';
    } elseif ($action === 'save_school_admin') {
        $admin_id = filter_var($_POST['admin_id'] ?? 0, FILTER_VALIDATE_INT);
        $admin_name = trim((string)($_POST['admin_name'] ?? ''));
        $admin_username = trim((string)($_POST['admin_username'] ?? ''));
        $admin_password = (string)($_POST['admin_password'] ?? '');
        if ($admin_id === false || $admin_id <= 0 || $admin_name === '' || strlen($admin_name) > 100 || !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $admin_username) || ($admin_password !== '' && (strlen($admin_password) < 10 || strlen($admin_password) > 200))) {
            $error = 'Enter a name, a login ID (3–50 letters/numbers/._-) and, if resetting it, a password of 10–200 characters.';
        } else {
            try {
                $admin_check = $tenant->prepare("SELECT id FROM users WHERE id=? AND role_id IN (2,3) AND account_type='Admin' LIMIT 1");
                if (!$admin_check) {
                    throw new RuntimeException('Unable to verify the selected school administrator.');
                }
                $admin_check->bind_param('i', $admin_id);
                if (!$admin_check->execute() || $admin_check->get_result()->num_rows !== 1) {
                    $admin_check->close();
                    throw new RuntimeException('The selected school administrator no longer exists.');
                }
                $admin_check->close();

                $username_check = $tenant->prepare('SELECT id FROM users WHERE username=? AND id<>? LIMIT 1');
                if (!$username_check) {
                    throw new RuntimeException('Unable to check whether the login ID is already in use.');
                }
                $username_check->bind_param('si', $admin_username, $admin_id);
                if (!$username_check->execute()) {
                    $username_check->close();
                    throw new RuntimeException('Unable to check whether the login ID is already in use.');
                }
                $username_taken = $username_check->get_result()->num_rows > 0;
                $username_check->close();
                if ($username_taken) {
                    throw new RuntimeException('That login ID is already in use in this school. Choose a different one.');
                }

                if ($admin_password === '') {
                    $update_admin = $tenant->prepare("UPDATE users SET full_name=?,username=? WHERE id=? AND role_id IN (2,3) AND account_type='Admin'");
                    if ($update_admin) {
                        $update_admin->bind_param('ssi', $admin_name, $admin_username, $admin_id);
                    }
                } else {
                    $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
                    $update_admin = $tenant->prepare("UPDATE users SET full_name=?,username=?,password=? WHERE id=? AND role_id IN (2,3) AND account_type='Admin'");
                    if ($update_admin) {
                        $update_admin->bind_param('sssi', $admin_name, $admin_username, $password_hash, $admin_id);
                    }
                }
                if (!$update_admin) {
                    throw new RuntimeException('Unable to prepare the school administrator update.');
                }
                if (!$update_admin->execute()) {
                    $update_admin->close();
                    throw new RuntimeException('Unable to save the school administrator login.');
                }
                $update_admin->close();
                $message = 'School administrator details saved. The new login ID is ' . $admin_username . '.';
            } catch (Throwable $exception) {
                error_log('School administrator update failed: ' . $exception->getMessage());
                $error = $exception->getMessage();
            }
        }
    } elseif ($action !== 'save_school_settings') {
        $error = 'Choose a valid school settings action.';
    } elseif ($values['school_name'] === '' || strlen($values['school_name']) > 150 || strlen($values['school_phone']) > 50 || strlen($values['academic_session']) > 100) {
        $error = 'Enter a school name and ensure the phone number and academic session are within the allowed length.';
    } elseif ($values['school_email'] !== '' && (!filter_var($values['school_email'], FILTER_VALIDATE_EMAIL) || strlen($values['school_email']) > 150)) {
        $error = 'Please enter a valid school email address.';
    } elseif (strlen($values['school_address']) > 4000) {
        $error = 'The school address is too long.';
    } elseif (!in_array($values['school_timezone'], timezone_identifiers_list(), true)) {
        $error = 'Select a valid timezone.';
    } else {
        try {
            if (!$tenant->begin_transaction()) {
                throw new RuntimeException('Unable to begin school settings transaction.');
            }
            $save = $tenant->prepare('INSERT INTO school_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
            if (!$save) {
                throw new RuntimeException('Unable to prepare school settings update: ' . $tenant->error);
            }
            foreach ($values as $key => $value) {
                $save->bind_param('ss', $key, $value);
                if (!$save->execute()) {
                    throw new RuntimeException('Unable to save school setting: ' . $save->error);
                }
            }
            $save->close();
            if (!$tenant->commit()) {
                throw new RuntimeException('Unable to save school settings transaction.');
            }
            try {
                $school_logo = school_settings_save_logo($_FILES['school_logo'] ?? null, $school['school_code']);
                if ($school_logo !== '') {
                    $logo_save = $tenant->prepare('INSERT INTO school_settings (setting_key,setting_value) VALUES ("school_logo",?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
                    if (!$logo_save) {
                        throw new RuntimeException('Unable to prepare the school logo setting.');
                    }
                    $logo_save->bind_param('s', $school_logo);
                    if (!$logo_save->execute()) {
                        $logo_save->close();
                        throw new RuntimeException('Unable to save the school logo setting.');
                    }
                    $logo_save->close();
                }
            } catch (Throwable $upload_exception) {
                error_log('School logo upload failed: ' . $upload_exception->getMessage());
                $error = $upload_exception->getMessage();
                $message = '';
            }
            $school['school_name'] = $values['school_name'];
            $registry_update = $master_conn->prepare('UPDATE school_portal_schools SET school_name=? WHERE id=? AND is_primary=0');
            if (!$registry_update) {
                $error = 'School settings were saved, but the school-list name could not be synchronized.';
            } else {
                $registry_update->bind_param('si', $values['school_name'], $school_id);
                if (!$registry_update->execute()) {
                    $error = 'School settings were saved, but the school-list name could not be synchronized.';
                } else {
                    $message = $error === '' ? 'School settings saved.' : '';
                }
                $registry_update->close();
            }
        } catch (Throwable $exception) {
            $tenant->rollback();
            error_log('School settings update failed: ' . $exception->getMessage());
            $error = 'School settings could not be saved. Check the server error log.';
        }
    }
}

$settings = array(
    'school_name' => $school['school_name'],
    'school_address' => '',
    'school_phone' => '',
    'school_email' => '',
    'academic_session' => '',
    'school_logo' => ''
);
$result = $tenant->query('SELECT setting_key, setting_value FROM school_settings');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if (array_key_exists($row['setting_key'], $settings)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    $result->free();
}
$admin_accounts = array();
$admin_result = $tenant->query("SELECT id,full_name,username,status FROM users WHERE role_id IN (2,3) AND account_type='Admin' ORDER BY full_name,id");
if ($admin_result) {
    while ($row = $admin_result->fetch_assoc()) {
        $admin_accounts[] = $row;
    }
    $admin_result->free();
} else {
    error_log('School administrator listing failed: ' . $tenant->error);
    $error = $error ?: 'School administrator accounts could not be loaded.';
}
$tenant->close();
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-school mr-2"></i>Edit school particulars and logins</h1></div></section>
    <section class="content"><div class="container-fluid">
        <a class="btn btn-secondary mb-3" href="index.php"><i class="fas fa-arrow-left mr-1"></i>Back to Super Admin Portal</a>
        <div class="alert alert-info">Editing <strong><?= school_settings_e($school['school_name']); ?></strong> (<code><?= school_settings_e($school['school_code']); ?></code>). The school has a separate database; changes here apply only to this school.</div>
        <?php if ($message !== ''): ?><div class="alert alert-success"><?= school_settings_e($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?= school_settings_e($error); ?></div><?php endif; ?>
        <div class="card">
            <div class="card-header bg-primary text-white"><h3 class="card-title">School particulars and academic settings</h3></div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="school_id" value="<?= (int)$school['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?= school_settings_e($_SESSION['school_identity_csrf']); ?>">
                <input type="hidden" name="action" value="save_school_settings">
                <div class="card-body"><div class="row">
                    <div class="form-group col-md-4"><label for="school_logo">School logo</label><input id="school_logo" type="file" name="school_logo" class="form-control-file" accept=".jpg,.jpeg,.png,.webp"><small class="form-text text-muted">Optional; JPG, PNG, or WEBP up to 2 MB.</small><?php if ($settings['school_logo'] !== ''): ?><img class="d-block mt-2" src="<?= BASE_URL; ?>/uploads/school/<?= rawurlencode(basename($settings['school_logo'])); ?>" alt="Current school logo" style="max-width:180px;max-height:90px;object-fit:contain"><?php endif; ?></div>
                    <?php foreach (array('school_name'=>'School name','school_phone'=>'Phone','school_email'=>'Email','academic_session'=>'Academic session') as $key=>$label): ?>
                        <div class="form-group col-md-4"><label for="<?= school_settings_e($key); ?>"><?= school_settings_e($label); ?></label><input id="<?= school_settings_e($key); ?>" name="<?= school_settings_e($key); ?>" class="form-control" value="<?= school_settings_e($settings[$key]); ?>" <?= $key === 'school_name' ? 'required' : ''; ?>></div>
                    <?php endforeach; ?>
                    <div class="form-group col-md-8"><label for="school_address">Address</label><textarea id="school_address" name="school_address" class="form-control" rows="3" maxlength="4000"><?= school_settings_e($settings['school_address']); ?></textarea></div>
                </div></div>
                <div class="card-footer"><button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i>Save school settings</button></div>
            </form>
        </div>
        <div class="card">
            <div class="card-header bg-info text-white"><h3 class="card-title">School administrator login accounts</h3></div>
            <div class="card-body">
                <p class="text-muted">Change an administrator's name or login ID, or set a new password. Leave the password blank to keep it unchanged. Passwords are never displayed.</p>
                <?php if (!$admin_accounts): ?><div class="alert alert-warning mb-0">No school administrator account was found for this school.</div><?php endif; ?>
                <?php foreach ($admin_accounts as $admin): ?>
                    <form method="post" class="border rounded p-3 mb-3">
                        <input type="hidden" name="school_id" value="<?= (int)$school['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?= school_settings_e($_SESSION['school_identity_csrf']); ?>">
                        <input type="hidden" name="action" value="save_school_admin">
                        <input type="hidden" name="admin_id" value="<?= (int)$admin['id']; ?>">
                        <div class="form-row">
                            <div class="form-group col-md-4"><label for="admin-name-<?= (int)$admin['id']; ?>">Administrator name</label><input id="admin-name-<?= (int)$admin['id']; ?>" name="admin_name" class="form-control" maxlength="100" value="<?= school_settings_e($admin['full_name']); ?>" required></div>
                            <div class="form-group col-md-4"><label for="admin-username-<?= (int)$admin['id']; ?>">Login ID</label><input id="admin-username-<?= (int)$admin['id']; ?>" name="admin_username" class="form-control" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]+" value="<?= school_settings_e($admin['username']); ?>" required></div>
                            <div class="form-group col-md-4"><label for="admin-password-<?= (int)$admin['id']; ?>">New password</label><input id="admin-password-<?= (int)$admin['id']; ?>" name="admin_password" type="password" class="form-control" minlength="10" maxlength="200" autocomplete="new-password" placeholder="Leave blank to keep current password"><small class="form-text text-muted">At least 10 characters when changing.</small></div>
                        </div>
                        <button class="btn btn-success" type="submit"><i class="fas fa-save mr-1"></i>Save / reset login</button>
                        <span class="badge badge-<?= (int)$admin['status'] === 1 ? 'success' : 'secondary'; ?> ml-2"><?= (int)$admin['status'] === 1 ? 'Active' : 'Disabled'; ?></span>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
