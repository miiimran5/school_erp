<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
require_once '../../includes/school.php';
access_require_login();

if (!access_is_super_admin() || $conn !== $master_conn) {
    http_response_code(403);
    exit('Only the Super Admin on the primary school portal can edit school settings.');
}

function school_settings_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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
if (!$school || (int)$school['is_primary'] === 1 || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $school['database_name'])) {
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
$keys = array('app_name', 'school_name', 'school_tagline', 'school_address', 'school_phone', 'school_email', 'school_timezone', 'academic_session');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    $values = array();
    foreach ($keys as $key) {
        $values[$key] = trim((string)($_POST[$key] ?? ''));
    }
    if (!hash_equals((string)$_SESSION['school_identity_csrf'], $token)) {
        $error = 'This form expired. Refresh the page and try again.';
    } elseif ($values['app_name'] === '' || strlen($values['app_name']) > 100 || $values['school_name'] === '' || strlen($values['school_name']) > 150 || strlen($values['school_tagline']) > 150 || strlen($values['school_phone']) > 50 || strlen($values['academic_session']) > 100) {
        $error = 'Enter a valid ERP name, school name, tagline, phone number, and academic session.';
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
            foreach ($values as $key => $value) {
                $GLOBALS['school_settings'][$key] = $value;
            }
            foreach (array('app_logo' => 'ERP logo', 'login_background' => 'Home page background') as $field => $label) {
                try {
                    school_save_brand_asset($tenant, $_FILES[$field] ?? null, $field, $field === 'app_logo' ? 'educare-logo' : 'login-background');
                } catch (Throwable $upload_exception) {
                    error_log('Tenant branding upload failed: ' . $upload_exception->getMessage());
                    $error = $label . ' could not be saved: ' . $upload_exception->getMessage();
                    break;
                }
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
    'app_name' => APP_NAME,
    'school_name' => $school['school_name'],
    'school_tagline' => 'School ERP',
    'school_address' => '',
    'school_phone' => '',
    'school_email' => '',
    'school_timezone' => 'Asia/Kolkata',
    'academic_session' => '',
    'app_logo' => 'educare-erp-logo.png',
    'login_background' => 'educare-erp-login-background.png'
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
$tenant->close();
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-school mr-2"></i>School Settings</h1></div></section>
    <section class="content"><div class="container-fluid">
        <a class="btn btn-secondary mb-3" href="index.php"><i class="fas fa-arrow-left mr-1"></i>Back to Super Admin Portal</a>
        <div class="alert alert-info">Editing <strong><?= school_settings_e($school['school_name']); ?></strong> (<code><?= school_settings_e($school['school_code']); ?></code>). The school has a separate database; changes here apply only to this school.</div>
        <?php if ($message !== ''): ?><div class="alert alert-success"><?= school_settings_e($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?= school_settings_e($error); ?></div><?php endif; ?>
        <div class="card">
            <div class="card-header bg-primary text-white"><h3 class="card-title">School identity and academic settings</h3></div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="school_id" value="<?= (int)$school['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?= school_settings_e($_SESSION['school_identity_csrf']); ?>">
                <div class="card-body"><div class="row">
                    <?php foreach (array('app_name'=>'ERP name','school_name'=>'School name','school_tagline'=>'Tagline','school_phone'=>'Phone','school_email'=>'Email','academic_session'=>'Academic session') as $key=>$label): ?>
                        <div class="form-group col-md-4"><label for="<?= school_settings_e($key); ?>"><?= school_settings_e($label); ?></label><input id="<?= school_settings_e($key); ?>" name="<?= school_settings_e($key); ?>" class="form-control" value="<?= school_settings_e($settings[$key]); ?>" <?= in_array($key, array('app_name', 'school_name'), true) ? 'required' : ''; ?>></div>
                    <?php endforeach; ?>
                    <div class="form-group col-md-4"><label for="school_timezone">Timezone</label><select id="school_timezone" name="school_timezone" class="form-control"><?php foreach (array('Asia/Kolkata','Asia/Dubai','Asia/Dhaka','Asia/Karachi','UTC') as $zone): ?><option value="<?= school_settings_e($zone); ?>" <?= $settings['school_timezone']===$zone?'selected':''; ?>><?= school_settings_e($zone); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group col-md-8"><label for="school_address">Address</label><textarea id="school_address" name="school_address" class="form-control" rows="3" maxlength="4000"><?= school_settings_e($settings['school_address']); ?></textarea></div>
                    <div class="form-group col-md-4"><label for="app_logo">ERP Logo</label><input id="app_logo" type="file" name="app_logo" class="form-control-file" accept=".jpg,.jpeg,.png,.webp"><small class="form-text text-muted">Optional; JPG, PNG, or WEBP up to 5 MB.</small><img class="d-block mt-2" src="<?= BASE_URL; ?>/uploads/branding/<?= rawurlencode(basename($settings['app_logo'])); ?>" alt="Current ERP logo" style="max-width:180px;max-height:90px;object-fit:contain"></div>
                    <div class="form-group col-md-8"><label for="login_background">Home page background</label><input id="login_background" type="file" name="login_background" class="form-control-file" accept=".jpg,.jpeg,.png,.webp"><small class="form-text text-muted">Optional; JPG, PNG, or WEBP up to 5 MB.</small><img class="d-block mt-2" src="<?= BASE_URL; ?>/uploads/branding/<?= rawurlencode(basename($settings['login_background'])); ?>" alt="Current home page background" style="max-width:260px;max-height:100px;object-fit:cover"></div>
                </div></div>
                <div class="card-footer"><button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i>Save school settings</button></div>
            </form>
        </div>
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
