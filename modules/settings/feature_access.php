<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';

access_require_login();
$super_admin_portal = access_is_super_admin() && (string)($_SESSION['erp_school_code'] ?? '') === ERP_SUPER_ADMIN_CODE;
if (!access_is_admin() && !$super_admin_portal) {
    http_response_code(403);
    exit('You do not have permission to manage feature access.');
}
$conn->set_charset('utf8mb4');
$schools = array();
$school_id = (int)($_POST['school_id'] ?? $_GET['school_id'] ?? 0);
$school_context_ready = !$super_admin_portal;
if ($super_admin_portal) {
    $school_result = $master_conn->query("SELECT id,school_code,school_name,database_name FROM school_portal_schools WHERE status='Active' AND is_primary=0 ORDER BY school_name");
    if (!$school_result) {
        http_response_code(500);
        exit('Unable to load the school list.');
    }
    while ($school = $school_result->fetch_assoc()) {
        $schools[] = $school;
    }
    foreach ($schools as $school) {
        if ((int)$school['id'] === $school_id && preg_match('/^[A-Za-z0-9_]{1,64}$/', $school['database_name'])) {
            try {
                $conn = erp_open_database($school['database_name']);
                $school_context_ready = true;
            } catch (Throwable $exception) {
                error_log('Feature Access school database connection failed: ' . $exception->getMessage());
                http_response_code(503);
                exit('The selected school database is unavailable.');
            }
            break;
        }
    }
}
$catalog = access_feature_catalog();
$teacher_features = array();
foreach ($catalog as $feature => $definition) {
    if (in_array('Teacher', $definition['roles'], true)) {
        $teacher_features[$feature] = $definition;
    }
}

if (empty($_SESSION['feature_access_csrf'])) {
    $_SESSION['feature_access_csrf'] = bin2hex(random_bytes(32));
}
$message = '';
$error = '';
if ($super_admin_portal && !$school_context_ready) {
    ?>
    <?php include '../../includes/header.php'; ?>
    <?php include '../../includes/navbar.php'; ?>
    <?php include '../../includes/sidebar.php'; ?>
    <div class="content-wrapper">
        <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-user-lock"></i> Feature Access</h1></div></section>
        <section class="content"><div class="container-fluid">
            <div class="alert alert-info">Choose an active school to manage its teacher and student feature permissions.</div>
            <form method="get" class="form-inline">
                <label for="school_id" class="mr-2">School</label>
                <select id="school_id" name="school_id" class="form-control mr-2" required>
                    <option value="">Choose a school</option>
                    <?php foreach ($schools as $school): ?>
                    <option value="<?= (int)$school['id']; ?>"><?= feature_access_e($school['school_name'] . ' (' . $school['school_code'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary">Open feature access</button>
            </form>
        </div></section>
    </div>
    <?php include '../../includes/footer.php'; ?>
    <?php
    exit;
}
$teacher_id = filter_var($_POST['teacher_id'] ?? $_GET['teacher_id'] ?? 0, FILTER_VALIDATE_INT);
$teacher_id = $teacher_id === false || $teacher_id < 0 ? 0 : (int)$teacher_id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'], $_SESSION['feature_access_csrf'])
        || !hash_equals((string)$_SESSION['feature_access_csrf'], (string)$_POST['csrf_token'])) {
        $error = 'This request could not be verified. Refresh the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_teacher' || $action === 'reset_teacher') {
            $teacher_check = $conn->prepare('SELECT full_name FROM teachers WHERE id=? AND status=1 LIMIT 1');
            if (!$teacher_check) {
                $error = 'Unable to verify the selected teacher: ' . $conn->error;
            } else {
                $teacher_check->bind_param('i', $teacher_id);
                if (!$teacher_check->execute()) {
                    $error = 'Unable to verify the selected teacher: ' . $teacher_check->error;
                } else {
                    $selected_teacher = $teacher_check->get_result()->fetch_assoc();
                    if (!$selected_teacher) {
                        $error = 'Select an active teacher before changing individual access.';
                    }
                }
                $teacher_check->close();
            }

            if ($error === '' && $action === 'reset_teacher') {
                $reset = $conn->prepare('DELETE FROM teacher_feature_permissions WHERE teacher_id=?');
                if (!$reset) {
                    $error = 'Unable to prepare the teacher permission reset: ' . $conn->error;
                } else {
                    $reset->bind_param('i', $teacher_id);
                    if ($reset->execute()) {
                        $message = 'This teacher now uses the default teacher permissions.';
                    } else {
                        $error = 'Unable to reset teacher permissions: ' . $reset->error;
                    }
                    $reset->close();
                }
            } elseif ($error === '' && $action === 'save_teacher') {
                $permissions = $_POST['teacher_permissions'] ?? array();
                if (!is_array($permissions)) {
                    $error = 'The submitted feature selections are invalid.';
                } else {
                    $conn->begin_transaction();
                    $save = $conn->prepare('INSERT INTO teacher_feature_permissions (teacher_id,feature_key,enabled,updated_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP');
                    if (!$save) {
                        $conn->rollback();
                        $error = 'Unable to prepare individual teacher permissions. Import database/migrations/022_teacher_feature_permissions.sql first.';
                    } else {
                        $updated_by = (int)($_SESSION['user_id'] ?? 0);
                        $ok = true;
                        foreach ($teacher_features as $feature => $definition) {
                            $enabled = isset($permissions[$feature]) && $permissions[$feature] === '1' ? 1 : 0;
                            $save->bind_param('isii', $teacher_id, $feature, $enabled, $updated_by);
                            if (!$save->execute()) {
                                $error = 'Unable to save individual teacher permissions: ' . $save->error;
                                $ok = false;
                                break;
                            }
                        }
                        $save->close();
                        if ($ok && $conn->commit()) {
                            $message = 'Feature access saved for ' . $selected_teacher['full_name'] . '.';
                        } elseif ($ok) {
                            $conn->rollback();
                            $error = 'Unable to commit teacher permissions. No changes were saved.';
                        } else {
                            $conn->rollback();
                        }
                    }
                }
            }
        } elseif ($action === 'save_defaults') {
            $permissions = $_POST['permissions'] ?? array();
            if (!is_array($permissions)) {
                $error = 'The submitted feature selections are invalid.';
            } else {
                $conn->begin_transaction();
                $save = $conn->prepare('INSERT INTO role_feature_permissions (account_type,feature_key,enabled,updated_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP');
                if (!$save) {
                    $conn->rollback();
                    $error = 'Unable to prepare default permission updates. Verify migration 019 is installed.';
                } else {
                    $updated_by = (int)($_SESSION['user_id'] ?? 0);
                    $ok = true;
                    foreach (array('Teacher', 'Student') as $role) {
                        foreach ($catalog as $feature => $definition) {
                            if (!in_array($role, $definition['roles'], true)) {
                                continue;
                            }
                            $enabled = isset($permissions[$role]) && is_array($permissions[$role])
                                && isset($permissions[$role][$feature]) && $permissions[$role][$feature] === '1' ? 1 : 0;
                            $save->bind_param('ssii', $role, $feature, $enabled, $updated_by);
                            if (!$save->execute()) {
                                $error = 'Unable to save default feature permissions: ' . $save->error;
                                $ok = false;
                                break 2;
                            }
                        }
                    }
                    $save->close();
                    if ($ok && $conn->commit()) {
                        $message = 'Default teacher and student permissions were saved.';
                    } elseif ($ok) {
                        $conn->rollback();
                        $error = 'Unable to commit default permissions. No changes were saved.';
                    } else {
                        $conn->rollback();
                    }
                }
            }
        } else {
            $error = 'Choose a valid Feature Access action.';
        }
    }
}

$teachers = array();
$teacher_result = $conn->query('SELECT id,full_name,employee_code FROM teachers WHERE status=1 ORDER BY full_name,id');
if (!$teacher_result) {
    error_log('Feature Access teacher listing failed: ' . $conn->error);
    http_response_code(500);
    exit('Unable to load the teacher list.');
}
while ($row = $teacher_result->fetch_assoc()) {
    $teachers[] = $row;
}

$selected_teacher = null;
foreach ($teachers as $teacher) {
    if ((int)$teacher['id'] === $teacher_id) {
        $selected_teacher = $teacher;
        break;
    }
}
if ($teacher_id > 0 && !$selected_teacher && $error === '') {
    $error = 'The selected teacher is inactive or no longer exists.';
    $teacher_id = 0;
}

$saved = array('Teacher' => array(), 'Student' => array());
$result = $conn->query('SELECT account_type,feature_key,enabled FROM role_feature_permissions');
if (!$result) {
    error_log('Feature permission listing failed: ' . $conn->error);
    http_response_code(500);
    exit('Unable to load feature permissions. Verify migration 019 is installed.');
}
while ($row = $result->fetch_assoc()) {
    $saved[$row['account_type']][$row['feature_key']] = (int)$row['enabled'] === 1;
}

$teacher_saved = array();
if ($selected_teacher) {
    $teacher_permissions = $conn->prepare('SELECT feature_key,enabled FROM teacher_feature_permissions WHERE teacher_id=?');
    if (!$teacher_permissions) {
        error_log('Individual teacher permission listing failed: ' . $conn->error);
        http_response_code(500);
        exit('Unable to load individual teacher permissions. Import database/migrations/022_teacher_feature_permissions.sql.');
    }
    $teacher_permissions->bind_param('i', $teacher_id);
    if (!$teacher_permissions->execute()) {
        error_log('Individual teacher permission listing failed: ' . $teacher_permissions->error);
        $teacher_permissions->close();
        http_response_code(500);
        exit('Unable to load individual teacher permissions. Check the database error log.');
    }
    $teacher_permission_result = $teacher_permissions->get_result();
    while ($row = $teacher_permission_result->fetch_assoc()) {
        $teacher_saved[$row['feature_key']] = (int)$row['enabled'] === 1;
    }
    $teacher_permissions->close();
}

function feature_access_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-user-lock"></i> Feature Access</h1><p class="text-muted mb-0">Manage default role access and choose specific teachers who should have different access.</p></div></section>
    <section class="content"><div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?= feature_access_e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= feature_access_e($error); ?></div><?php endif; ?>

        <?php if ($super_admin_portal): ?>
        <form method="get" class="form-inline mb-3">
            <label for="school_id" class="mr-2">School</label>
            <select id="school_id" name="school_id" class="form-control mr-2" onchange="this.form.submit()">
                <?php foreach ($schools as $school): ?>
                <option value="<?= (int)$school['id']; ?>" <?= $school_id === (int)$school['id'] ? 'selected' : ''; ?>><?= feature_access_e($school['school_name'] . ' (' . $school['school_code'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-info text-white">Individual Teacher Access</div>
            <div class="card-body">
                <form method="get" class="form-inline mb-3">
                    <?php if ($super_admin_portal): ?><input type="hidden" name="school_id" value="<?= (int)$school_id; ?>"><?php endif; ?>
                    <label for="teacher_id" class="mr-2">Select teacher</label>
                    <select id="teacher_id" name="teacher_id" class="form-control mr-2" onchange="this.form.submit()">
                        <option value="0">Choose a teacher</option>
                        <?php foreach ($teachers as $teacher): ?>
                        <option value="<?= (int)$teacher['id']; ?>" <?= $teacher_id === (int)$teacher['id'] ? 'selected' : ''; ?>><?= feature_access_e($teacher['full_name'] . ' (' . $teacher['employee_code'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php if ($selected_teacher): ?>
                <?php
                $has_override = count($teacher_saved) > 0;
                ?>
                <p class="text-muted">Set exactly which features <?= feature_access_e($selected_teacher['full_name']); ?> can use. Unchecked features are denied for this teacher, even if enabled in the default teacher permissions. <?= $has_override ? 'This teacher has individual permissions saved.' : 'No individual permissions are saved yet; the displayed choices currently inherit the defaults below.'; ?></p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= feature_access_e($_SESSION['feature_access_csrf']); ?>">
                    <input type="hidden" name="action" value="save_teacher">
                    <?php if ($super_admin_portal): ?><input type="hidden" name="school_id" value="<?= (int)$school_id; ?>"><?php endif; ?>
                    <input type="hidden" name="teacher_id" value="<?= (int)$teacher_id; ?>">
                    <div class="row">
                        <?php foreach ($teacher_features as $feature => $definition): ?>
                        <?php $default_enabled = array_key_exists($feature, $saved['Teacher']) ? $saved['Teacher'][$feature] : access_feature_default_enabled('Teacher', $feature); ?>
                        <?php $checked = array_key_exists($feature, $teacher_saved) ? $teacher_saved[$feature] : $default_enabled; ?>
                        <div class="col-md-6 col-lg-4"><div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" class="custom-control-input" id="teacher-<?= (int)$teacher_id; ?>-<?= feature_access_e($feature); ?>" name="teacher_permissions[<?= feature_access_e($feature); ?>]" value="1" <?= $checked ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="teacher-<?= (int)$teacher_id; ?>-<?= feature_access_e($feature); ?>"><?= feature_access_e($definition['label']); ?></label>
                        </div></div>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn btn-success"><i class="fas fa-save mr-1"></i>Save for this teacher</button>
                </form>
                <form method="post" class="mt-2" onsubmit="return confirm('Remove this teacher’s individual settings and return them to default teacher access?');">
                    <input type="hidden" name="csrf_token" value="<?= feature_access_e($_SESSION['feature_access_csrf']); ?>">
                    <input type="hidden" name="action" value="reset_teacher">
                    <?php if ($super_admin_portal): ?><input type="hidden" name="school_id" value="<?= (int)$school_id; ?>"><?php endif; ?>
                    <input type="hidden" name="teacher_id" value="<?= (int)$teacher_id; ?>">
                    <button class="btn btn-outline-secondary"><i class="fas fa-undo mr-1"></i>Use default teacher permissions</button>
                </form>
                <?php else: ?>
                <p class="text-muted mb-0">Choose a teacher to grant or remove features for that individual.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-primary text-white">Default Role Permissions</div>
            <div class="card-body">
                <p class="text-muted">These defaults apply to all teachers without individual settings and to students. Individual teacher settings above override teacher defaults.</p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= feature_access_e($_SESSION['feature_access_csrf']); ?>">
                    <input type="hidden" name="action" value="save_defaults">
                    <?php if ($super_admin_portal): ?><input type="hidden" name="school_id" value="<?= (int)$school_id; ?>"><?php endif; ?>
                    <input type="hidden" name="teacher_id" value="<?= (int)$teacher_id; ?>">
                    <div class="row">
                        <?php foreach (array('Teacher', 'Student') as $role): ?>
                        <div class="col-lg-6"><div class="card"><div class="card-header"><?= feature_access_e($role); ?> defaults</div><div class="card-body">
                            <?php foreach ($catalog as $feature => $definition): ?>
                            <?php if (!in_array($role, $definition['roles'], true)) continue; ?>
                            <?php $checked = array_key_exists($feature, $saved[$role]) ? $saved[$role][$feature] : access_feature_default_enabled($role, $feature); ?>
                            <div class="custom-control custom-checkbox mb-2"><input type="checkbox" class="custom-control-input" id="<?= strtolower($role) . '-' . feature_access_e($feature); ?>" name="permissions[<?= feature_access_e($role); ?>][<?= feature_access_e($feature); ?>]" value="1" <?= $checked ? 'checked' : ''; ?>><label class="custom-control-label" for="<?= strtolower($role) . '-' . feature_access_e($feature); ?>"><?= feature_access_e($definition['label']); ?></label></div>
                            <?php endforeach; ?>
                            <?php if ($role === 'Student'): ?><small class="form-text text-muted">Recommended student access is limited to announcements, homework, date sheets, syllabus, and their own attendance.</small><?php endif; ?>
                        </div></div></div>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save default permissions</button>
                </form>
            </div>
        </div>
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
