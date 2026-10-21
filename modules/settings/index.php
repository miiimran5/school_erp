<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
require_once '../../includes/school.php';
access_require_login();

if (!access_is_super_admin()) {
    http_response_code(403);
    exit('Only the Super Admin can access the school portal.');
}
$current_database_result = $master_conn->query('SELECT DATABASE() AS database_name');
$current_database = $current_database_result ? (string)$current_database_result->fetch_assoc()['database_name'] : '';
if ($current_database !== DB_NAME || $conn !== $master_conn) {
    http_response_code(403);
    exit('School management is available only from the primary school account.');
}
$conn->set_charset('utf8mb4');
if (empty($_SESSION['school_portal_csrf'])) {
    $_SESSION['school_portal_csrf'] = bin2hex(random_bytes(32));
}

function school_portal_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function school_portal_table_exists($conn, $table)
{
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Unable to check database setup: ' . $conn->error);
    }
    $stmt->bind_param('s', $table);
    if (!$stmt->execute()) {
        throw new RuntimeException('Unable to check database setup: ' . $stmt->error);
    }
    $exists = $stmt->get_result()->num_rows === 1;
    $stmt->close();
    return $exists;
}

function school_portal_clone_schema($source, $database_name, $copy_school_setup)
{
    $tables_result = $source->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE' AND table_name<>'school_portal_schools' ORDER BY table_name");
    if (!$tables_result) {
        throw new RuntimeException('Unable to read the primary database schema: ' . $source->error);
    }
    $tables = array();
    $dependencies = array();
    while ($row = $tables_result->fetch_assoc()) {
        $tables[] = $row['table_name'];
        $dependencies[$row['table_name']] = array();
    }
    $tables_result->free();
    if (!$tables) {
        throw new RuntimeException('The primary database contains no tables to clone.');
    }
    foreach (array('roles', 'classes', 'sections', 'subjects', 'exam_types', 'school_settings', 'students', 'teachers', 'users', 'student_attendance') as $required_table) {
        if (!in_array($required_table, $tables, true)) {
            throw new RuntimeException('The primary database is missing required table ' . $required_table . '. Update its schema before adding schools.');
        }
    }

    $foreign_keys = $source->query("SELECT table_name, referenced_table_name FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND referenced_table_name IS NOT NULL");
    if (!$foreign_keys) {
        throw new RuntimeException('Unable to read the primary database relationships: ' . $source->error);
    }
    while ($row = $foreign_keys->fetch_assoc()) {
        if (isset($dependencies[$row['table_name']]) && isset($dependencies[$row['referenced_table_name']])) {
            $dependencies[$row['table_name']][$row['referenced_table_name']] = true;
        }
    }
    $foreign_keys->free();

    $ordered = array();
    while ($dependencies) {
        $ready = array();
        foreach ($dependencies as $table => $requires) {
            if (!$requires) {
                $ready[] = $table;
            }
        }
        if (!$ready) {
            throw new RuntimeException('The primary database has circular foreign keys and cannot be safely cloned automatically.');
        }
        foreach ($ready as $table) {
            $ordered[] = $table;
            unset($dependencies[$table]);
        }
        foreach ($dependencies as &$requires) {
            foreach ($ready as $table) {
                unset($requires[$table]);
            }
        }
        unset($requires);
    }

    $database_check = $source->prepare('SELECT 1 FROM information_schema.schemata WHERE schema_name=? LIMIT 1');
    if (!$database_check) {
        throw new RuntimeException('Unable to check the new school database name.');
    }
    $database_check->bind_param('s', $database_name);
    $database_check->execute();
    $already_exists = $database_check->get_result()->num_rows > 0;
    $database_check->close();
    if ($already_exists) {
        throw new RuntimeException('The generated school database already exists. Contact the database administrator.');
    }

    if (!$source->query("CREATE DATABASE `" . $database_name . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
        throw new RuntimeException('The database server could not create a new school database: ' . $source->error);
    }
    $tenant = null;
    try {
        $tenant = erp_open_database($database_name);
        foreach ($ordered as $table) {
            $quoted_table = str_replace('`', '``', $table);
            $show = $source->query('SHOW CREATE TABLE `' . $quoted_table . '`');
            if (!$show) {
                throw new RuntimeException('Unable to read the schema for ' . $table . ': ' . $source->error);
            }
            $definition = $show->fetch_assoc();
            $show->free();
            $create_sql = $definition['Create Table'] ?? '';
            if ($create_sql === '') {
                throw new RuntimeException('The schema definition for ' . $table . ' is empty.');
            }
            if (!$tenant->query($create_sql)) {
                throw new RuntimeException('Unable to create a school table: ' . $tenant->error);
            }
        }

        $setup_tables = array(
            'roles',
            'classes',
            'sections',
            'subjects',
            'exam_types',
            'academic_sessions'
        );
        if ($copy_school_setup) {
            $setup_tables = array_merge($setup_tables, array(
                'houses',
                'categories',
                'concessions',
                'games',
                'religions',
                'transport_routes',
                'exams',
                'class_subjects',
                'report_card_group_subjects',
                'report_card_class_settings',
                'report_card_class_subjects',
                'fee_types',
                'fee_structures',
                'fee_schedules'
            ));
        }
        $setup_tables[] = 'school_settings';
        foreach ($setup_tables as $table) {
            if (!in_array($table, $tables, true)) {
                continue;
            }
            $quoted_table = str_replace('`', '``', $table);
            if (!$tenant->query('INSERT INTO `' . $quoted_table . '` SELECT * FROM `' . DB_NAME . '`.`' . $quoted_table . '`')) {
                throw new RuntimeException('Unable to copy the selected school setup data: ' . $tenant->error);
            }
        }
        return $tenant;
    } catch (Throwable $exception) {
        if ($tenant instanceof mysqli) {
            $tenant->close();
        }
        $quoted_database = str_replace('`', '``', $database_name);
        $source->query('DROP DATABASE `' . $quoted_database . '`');
        throw $exception;
    }
}

$error = '';
$message = '';
$registry_ready = false;
try {
    $registry_ready = school_portal_table_exists($master_conn, 'school_portal_schools');
} catch (Throwable $exception) {
    error_log('School registry check failed: ' . $exception->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals((string)$_SESSION['school_portal_csrf'], $token)) {
        $error = 'This form expired. Refresh the page and try again.';
    } elseif (!$registry_ready) {
        $error = 'Import database/migrations/016_school_database_registry.sql before managing schools.';
    } else {
        $action = (string)($_POST['action'] ?? 'save_settings');
        if ($action === 'toggle_school') {
            $school_id = (int)($_POST['school_id'] ?? 0);
            $new_status = (string)($_POST['new_status'] ?? '');
            if ($school_id <= 0 || !in_array($new_status, array('Active', 'Inactive'), true)) {
                $error = 'Choose a valid school and status.';
            } else {
                $stmt = $master_conn->prepare("UPDATE school_portal_schools SET status=? WHERE id=? AND is_primary=0 AND status IN ('Active','Inactive')");
                if (!$stmt) {
                    $error = 'Unable to prepare the school status change.';
                } else {
                    $stmt->bind_param('si', $new_status, $school_id);
                    if ($stmt->execute() && $stmt->affected_rows === 1) {
                        $message = $new_status === 'Inactive'
                            ? 'School services have been stopped. Its users will be blocked from logging in and from continuing to use the ERP.'
                            : 'School services have been activated. Its users can log in and use the ERP again.';
                    } else {
                        $error = 'The school could not be updated. The primary school cannot be deactivated.';
                    }
                    $stmt->close();
                }
            }
        } elseif ($action === 'create_school') {
            $school_name = trim((string)($_POST['new_school_name'] ?? ''));
            $admin_name = trim((string)($_POST['admin_name'] ?? ''));
            $admin_username = trim((string)($_POST['admin_username'] ?? ''));
            $admin_password = (string)($_POST['admin_password'] ?? '');
            if ($school_name === '' || strlen($school_name) > 150 || $admin_name === '' || strlen($admin_name) > 100 || !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $admin_username) || strlen($admin_password) < 10 || strlen($admin_password) > 200) {
                $error = 'Enter a school name, admin name, login ID (3–50 letters/numbers/._-) and password (10–200 characters).';
            } else {
                try {
                    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $school_name), 0, 5));
                    if ($prefix === '') {
                        $prefix = 'SCHOOL';
                    }
                    $school_code = $prefix . '-' . strtoupper(bin2hex(random_bytes(3)));
                    $database_name = 'school_erp_tenant_' . bin2hex(random_bytes(5));
                    $registry = $master_conn->prepare("INSERT INTO school_portal_schools (school_code, school_name, database_name, status, is_primary) VALUES (?, ?, ?, 'Provisioning', 0)");
                    if (!$registry) {
                        throw new RuntimeException('Unable to prepare the school registry entry: ' . $master_conn->error);
                    }
                    $registry->bind_param('sss', $school_code, $school_name, $database_name);
                    if (!$registry->execute()) {
                        throw new RuntimeException('Unable to create the school registry entry: ' . $registry->error);
                    }
                    $school_id = (int)$registry->insert_id;
                    $registry->close();

                    $tenant = null;
                    try {
                        $copy_school_setup = isset($_POST['copy_school_setup']) && $_POST['copy_school_setup'] === '1';
                        $tenant = school_portal_clone_schema($master_conn, $database_name, $copy_school_setup);
                        if (!$copy_school_setup) {
                            $defaults = array(
                                'school_name' => $school_name,
                                'school_tagline' => 'School ERP',
                                'school_address' => '',
                                'school_phone' => '',
                                'school_email' => '',
                                'school_logo' => '',
                                'school_timezone' => 'Asia/Kolkata',
                                'academic_session' => ''
                            );
                            $identity = $tenant->prepare('INSERT INTO school_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
                            if (!$identity) {
                                throw new RuntimeException('Unable to prepare new school settings.');
                            }
                            foreach ($defaults as $key => $value) {
                                $identity->bind_param('ss', $key, $value);
                                if (!$identity->execute()) {
                                    throw new RuntimeException('Unable to initialize the new school settings: ' . $identity->error);
                                }
                            }
                            $identity->close();
                        }
                        $identity = $tenant->prepare("INSERT INTO school_settings (setting_key, setting_value) VALUES ('school_name', ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
                        if (!$identity) {
                            throw new RuntimeException('Unable to prepare the new school identity.');
                        }
                        $identity->bind_param('s', $school_name);
                        if (!$identity->execute()) {
                            throw new RuntimeException('Unable to save the new school identity: ' . $identity->error);
                        }
                        $identity->close();
                        $clear_identity = $tenant->prepare("INSERT INTO school_settings (setting_key, setting_value) VALUES (?, '') ON DUPLICATE KEY UPDATE setting_value=''");
                        if (!$clear_identity) {
                            throw new RuntimeException('Unable to prepare the new school contact settings.');
                        }
                        foreach (array('school_address', 'school_phone', 'school_email', 'school_logo') as $identity_key) {
                            $clear_identity->bind_param('s', $identity_key);
                            if (!$clear_identity->execute()) {
                                throw new RuntimeException('Unable to initialize new school contact settings.');
                            }
                        }
                        $clear_identity->close();
                        $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                        $admin = $tenant->prepare("INSERT INTO users (role_id, account_type, linked_id, full_name, username, password, status) VALUES (3, 'Admin', NULL, ?, ?, ?, 1)");
                        if (!$admin) {
                            throw new RuntimeException('Unable to prepare the new school administrator account.');
                        }
                        $admin->bind_param('sss', $admin_name, $admin_username, $hashed_password);
                        if (!$admin->execute()) {
                            throw new RuntimeException('Unable to create the new school administrator account: ' . $admin->error);
                        }
                        $admin->close();
                        $tenant->close();

                        $active = $master_conn->prepare("UPDATE school_portal_schools SET status='Active' WHERE id=? AND status='Provisioning'");
                        if (!$active) {
                            throw new RuntimeException('Unable to activate the new school registry entry.');
                        }
                        $active->bind_param('i', $school_id);
                        if (!$active->execute() || $active->affected_rows !== 1) {
                            throw new RuntimeException('The school database was created but registry activation failed.');
                        }
                        $active->close();
                        $message = 'School created. Its login code is ' . $school_code . '. Give this code and the admin login details to the school administrator.';
                    } catch (Throwable $exception) {
                        if (isset($tenant) && $tenant instanceof mysqli) {
                            $tenant->close();
                        }
                        $quoted_database = str_replace('`', '``', $database_name);
                        $master_conn->query('DROP DATABASE IF EXISTS `' . $quoted_database . '`');
                        $failed = $master_conn->prepare("UPDATE school_portal_schools SET status='Failed' WHERE id=?");
                        if ($failed) {
                            $failed->bind_param('i', $school_id);
                            $failed->execute();
                            $failed->close();
                        }
                        throw $exception;
                    }
                } catch (Throwable $exception) {
                    error_log('School provisioning failed: ' . $exception->getMessage());
                    $error = 'The school could not be created. Check that the database account may create databases and review the server error log.';
                }
            }
        } else {
            $keys = array('app_name', 'school_name', 'school_tagline', 'school_address', 'school_phone', 'school_email', 'school_timezone', 'academic_session');
            $posted = array();
            foreach ($keys as $key) {
                $posted[$key] = trim((string)($_POST[$key] ?? ''));
            }
            if ($posted['app_name'] === '' || strlen($posted['app_name']) > 100 || $posted['school_name'] === '') {
                $error = 'ERP name and school name are required. The ERP name must be 100 characters or fewer.';
            } elseif ($posted['school_email'] !== '' && !filter_var($posted['school_email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid school email address.';
            } elseif (!in_array($posted['school_timezone'], timezone_identifiers_list(), true)) {
                $error = 'Please select a valid timezone.';
            } else {
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare('INSERT INTO school_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
                    if (!$stmt) {
                        throw new RuntimeException('Unable to prepare school settings update: ' . $conn->error);
                    }
                    foreach ($posted as $key => $value) {
                        $stmt->bind_param('ss', $key, $value);
                        if (!$stmt->execute()) {
                            throw new RuntimeException('Unable to save school setting ' . $key . ': ' . $stmt->error);
                        }
                    }
                    $stmt->close();
                    if (!$conn->commit()) {
                        throw new RuntimeException('Unable to commit school settings.');
                    }
                    foreach ($posted as $key => $value) {
                        $GLOBALS['school_settings'][$key] = $value;
                    }
                    $message = 'Primary school settings saved.';
                } catch (Throwable $exception) {
                    $conn->rollback();
                    error_log('Primary school settings update failed: ' . $exception->getMessage());
                    $error = 'School settings could not be saved. Check the server error log.';
                }
                if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $allowed = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
                    $mime = is_uploaded_file($_FILES['school_logo']['tmp_name']) && function_exists('mime_content_type') ? mime_content_type($_FILES['school_logo']['tmp_name']) : '';
                    if ($_FILES['school_logo']['error'] !== UPLOAD_ERR_OK || !isset($allowed[$mime]) || $_FILES['school_logo']['size'] > 2097152) {
                        $error = 'Logo must be JPG, PNG, or WEBP and no larger than 2 MB.';
                    } else {
                        $filename = 'school_logo.' . $allowed[$mime];
                        $directory = __DIR__ . '/../../uploads/school';
                        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                            $error = 'Unable to create the school logo folder.';
                        } elseif (move_uploaded_file($_FILES['school_logo']['tmp_name'], $directory . '/' . $filename)) {
                            $stmt = $conn->prepare('INSERT INTO school_settings (setting_key, setting_value) VALUES ("school_logo", ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
                            if ($stmt) {
                                $stmt->bind_param('s', $filename);
                                $error = $stmt->execute() ? '' : 'Unable to save the school logo setting.';
                                $stmt->close();
                                if ($error === '') {
                                    $GLOBALS['school_settings']['school_logo'] = $filename;
                                }
                            } else {
                                $error = 'Unable to prepare school logo settings.';
                            }
                        } else {
                            $error = 'Unable to save the school logo file.';
                        }
                    }
                    if ($error === '') {
                        $message = 'Primary school settings saved.';
                    }
                }
            }
            if ($error === '') {
                foreach (array('app_logo' => 'ERP logo', 'login_background' => 'Home page background') as $field => $label) {
                    try {
                        $saved_asset = school_save_brand_asset($conn, $_FILES[$field] ?? null, $field, $field === 'app_logo' ? 'educare-logo' : 'login-background');
                        if ($saved_asset !== '') {
                            $GLOBALS['school_settings'][$field] = $saved_asset;
                        }
                    } catch (Throwable $exception) {
                        error_log('ERP branding upload failed: ' . $exception->getMessage());
                        $error = $label . ' could not be saved: ' . $exception->getMessage();
                        break;
                    }
                }
            }
            require '../../includes/school.php';
        }
    }
}

$settings = $GLOBALS['school_settings'];
$schools = array();
if ($registry_ready) {
    $school_result = $master_conn->query('SELECT id, school_code, school_name, database_name, status, is_primary, created_at FROM school_portal_schools ORDER BY is_primary DESC, school_name');
    if ($school_result) {
        while ($row = $school_result->fetch_assoc()) {
            $schools[] = $row;
        }
    } else {
        error_log('Unable to load school registry: ' . $master_conn->error);
        $error = $error ?: 'School records could not be loaded.';
    }
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-user-shield mr-2"></i>Super Admin Portal</h1></div></section>
    <section class="content"><div class="container-fluid">
        <?php if ($message !== ''): ?><div class="alert alert-success"><?= school_portal_e($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?= school_portal_e($error); ?></div><?php endif; ?>

        <?php if (!$registry_ready): ?>
            <div class="alert alert-warning">Import <code>database/migrations/016_school_database_registry.sql</code> into the primary database before adding schools.</div>
        <?php else: ?>
            <div class="alert alert-info">Each school has a separate database. Use <strong>Stop Services</strong> to set a school to Inactive: this blocks new logins and prevents users with existing sessions from making further ERP requests. Use <strong>Activate Services</strong> to restore access. The primary school cannot be deactivated here. New schools never receive student, employee, login, attendance, payment, or marks records. You can choose whether to copy the primary school's report-card, subject-to-class, exam, fee, and other academic setup. Basic class, section, subject, role, and exam-type lists are prepared for every new school. The configured MySQL account must have permission to create databases and tables.</div>
            <div class="card">
                <div class="card-header bg-primary text-white"><h3 class="card-title">Schools and access</h3></div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-bordered table-striped mb-0">
                        <thead><tr><th>School</th><th>Login code</th><th>Database</th><th>Status</th><th>School settings</th><th>Services</th></tr></thead>
                        <tbody>
                        <?php foreach ($schools as $school): ?>
                            <tr>
                                <td><?= school_portal_e($school['school_name']); ?><?= (int)$school['is_primary'] === 1 ? ' (Primary)' : ''; ?></td>
                                <td><code><?= school_portal_e($school['school_code']); ?></code></td>
                                <td><?= school_portal_e($school['database_name']); ?></td>
                                <td><span class="badge badge-<?= $school['status'] === 'Active' ? 'success' : ($school['status'] === 'Inactive' ? 'secondary' : 'warning'); ?>"><?= school_portal_e($school['status']); ?></span></td>
                                <td>
                                    <?php if ((int)$school['is_primary'] === 1): ?>
                                        <span class="text-muted">Managed below</span>
                                    <?php elseif (in_array($school['status'], array('Active', 'Inactive'), true)): ?>
                                        <a class="btn btn-sm btn-outline-primary" href="school.php?id=<?= (int)$school['id']; ?>">Manage settings</a>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int)$school['is_primary'] !== 1 && in_array($school['status'], array('Active', 'Inactive'), true)): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= school_portal_e($_SESSION['school_portal_csrf']); ?>">
                                            <input type="hidden" name="action" value="toggle_school">
                                            <input type="hidden" name="school_id" value="<?= (int)$school['id']; ?>">
                                            <input type="hidden" name="new_status" value="<?= $school['status'] === 'Active' ? 'Inactive' : 'Active'; ?>">
                                            <button class="btn btn-sm btn-<?= $school['status'] === 'Active' ? 'outline-danger' : 'outline-success'; ?>" type="submit" onclick="return confirm('<?= $school['status'] === 'Active' ? 'Stop services for this school? All users will be blocked from the ERP.' : 'Activate services for this school and restore user access?'; ?>');"><?= $school['status'] === 'Active' ? 'Stop Services' : 'Activate Services'; ?></button>
                                        </form>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-success text-white"><h3 class="card-title">Add a school</h3></div>
                <form method="post" data-loading>
                    <input type="hidden" name="csrf_token" value="<?= school_portal_e($_SESSION['school_portal_csrf']); ?>">
                    <input type="hidden" name="action" value="create_school">
                    <div class="card-body"><div class="row">
                        <div class="form-group col-md-6"><label for="new_school_name">School name</label><input id="new_school_name" name="new_school_name" class="form-control" maxlength="150" required></div>
                        <div class="form-group col-md-6"><label for="admin_name">School administrator name</label><input id="admin_name" name="admin_name" class="form-control" maxlength="100" required></div>
                        <div class="form-group col-md-6"><label for="admin_username">Administrator login ID</label><input id="admin_username" name="admin_username" class="form-control" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]+" required><small class="form-text text-muted">Use 3–50 letters, numbers, dots, underscores or hyphens.</small></div>
                        <div class="form-group col-md-6"><label for="admin_password">Temporary administrator password</label><input id="admin_password" name="admin_password" type="password" class="form-control" minlength="10" maxlength="200" autocomplete="new-password" required><small class="form-text text-muted">Use at least 10 characters and give it to the administrator securely.</small></div>
                        <div class="form-group col-12"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="copy_school_setup" name="copy_school_setup" value="1" checked><label class="custom-control-label" for="copy_school_setup">Copy academic and fee setup from the primary school</label><small class="form-text text-muted">Copies subjects assigned to classes, exam setup, report-card subjects, fee schedules and lookups; does not copy school contact details.</small></div></div>
                    </div></div>
                    <div class="card-footer"><button type="submit" class="btn btn-success"><i class="fas fa-plus mr-1"></i>Create isolated school database</button></div>
                </form>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-primary text-white"><h3 class="card-title">Primary school identity and settings</h3></div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= school_portal_e($_SESSION['school_portal_csrf']); ?>">
                <input type="hidden" name="action" value="save_settings">
                <div class="card-body"><div class="row">
                    <?php foreach (array('app_name'=>'ERP Name','school_name'=>'School Name','school_tagline'=>'Tagline','school_phone'=>'Phone','school_email'=>'Email','academic_session'=>'Academic Session') as $key=>$label): ?>
                        <div class="form-group col-md-4"><label for="<?= school_portal_e($key); ?>"><?= school_portal_e($label); ?></label><input id="<?= school_portal_e($key); ?>" name="<?= school_portal_e($key); ?>" class="form-control" value="<?= school_portal_e($settings[$key]); ?>" <?= in_array($key, array('app_name', 'school_name'), true) ? 'required' : ''; ?>></div>
                    <?php endforeach; ?>
                    <div class="form-group col-md-4"><label for="school_timezone">Timezone</label><select id="school_timezone" name="school_timezone" class="form-control"><?php foreach (array('Asia/Kolkata','Asia/Dubai','Asia/Dhaka','Asia/Karachi','UTC') as $zone): ?><option value="<?= school_portal_e($zone); ?>" <?= $settings['school_timezone']===$zone?'selected':''; ?>><?= school_portal_e($zone); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group col-md-8"><label for="school_address">Address</label><textarea id="school_address" name="school_address" class="form-control" rows="2"><?= school_portal_e($settings['school_address']); ?></textarea></div>
                    <div class="form-group col-md-4"><label for="school_logo">School logo</label><input id="school_logo" type="file" name="school_logo" class="form-control-file" accept=".jpg,.jpeg,.png,.webp"><small class="text-muted">JPG, PNG or WEBP, maximum 2 MB.</small><?php if ($settings['school_logo']): ?><div class="mt-2"><img src="<?= BASE_URL; ?>/uploads/school/<?= school_portal_e($settings['school_logo']); ?>" alt="School logo" style="max-height:60px"></div><?php endif; ?></div>
                    <div class="form-group col-md-4"><label for="app_logo">ERP Logo</label><input id="app_logo" type="file" name="app_logo" class="form-control-file" accept=".jpg,.jpeg,.png,.webp"><small class="form-text text-muted">Optional; JPG, PNG, or WEBP up to 5 MB. Leave empty to keep the current logo.</small><img class="d-block mt-2" src="<?= BASE_URL; ?>/uploads/branding/<?= rawurlencode(basename($settings['app_logo'])); ?>" alt="Current ERP logo" style="max-width:180px;max-height:90px;object-fit:contain"></div>
                    <div class="form-group col-md-8"><label for="login_background">Home Page Background</label><input id="login_background" type="file" name="login_background" class="form-control-file" accept=".jpg,.jpeg,.png,.webp"><small class="form-text text-muted">Optional; JPG, PNG, or WEBP up to 5 MB. Leave empty to keep the current background.</small><img class="d-block mt-2" src="<?= BASE_URL; ?>/uploads/branding/<?= rawurlencode(basename($settings['login_background'])); ?>" alt="Current home page background" style="max-width:260px;max-height:100px;object-fit:cover"></div>
                </div></div>
                <div class="card-footer"><button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save primary school settings</button></div>
            </form>
        </div>
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
