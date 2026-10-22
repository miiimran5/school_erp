<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';

access_require_roles(array('Admin'));
$conn->set_charset('utf8mb4');
$catalog = access_feature_catalog();
if (empty($_SESSION['feature_access_csrf'])) {
    $_SESSION['feature_access_csrf'] = bin2hex(random_bytes(32));
}
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'], $_SESSION['feature_access_csrf'])
        || !hash_equals((string)$_SESSION['feature_access_csrf'], (string)$_POST['csrf_token'])) {
        $error = 'This request could not be verified. Refresh the page and try again.';
    } else {
        $permissions = $_POST['permissions'] ?? array();
        if (!is_array($permissions)) {
            $error = 'The submitted feature selections are invalid.';
        } else {
            $conn->begin_transaction();
            $save = $conn->prepare('INSERT INTO role_feature_permissions (account_type,feature_key,enabled,updated_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP');
            if (!$save) {
                $conn->rollback();
                $error = 'Unable to prepare permission updates. Import the latest database migration first.';
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
                            $error = 'Unable to save feature permissions: ' . $save->error;
                            $ok = false;
                            break 2;
                        }
                    }
                }
                $save->close();
                if ($ok && $conn->commit()) {
                    $message = 'Teacher and student feature permissions were saved.';
                } elseif ($ok) {
                    $conn->rollback();
                    $error = 'Unable to commit feature permissions. No changes were saved.';
                } else {
                    $conn->rollback();
                }
            }
        }
    }
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
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-user-lock"></i> Feature Access</h1><p class="text-muted mb-0">Choose which modules each role can open. These controls also protect direct page URLs, not just sidebar links.</p></div></section>
    <section class="content"><div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['feature_access_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="row">
                <?php foreach (array('Teacher', 'Student') as $role): ?>
                <div class="col-lg-6"><div class="card"><div class="card-header bg-primary text-white"><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?> access</div><div class="card-body">
                    <?php foreach ($catalog as $feature => $definition): ?>
                        <?php if (!in_array($role, $definition['roles'], true)) continue; ?>
                        <?php $checked = array_key_exists($feature, $saved[$role]) ? $saved[$role][$feature] : access_feature_default_enabled($role, $feature); ?>
                        <div class="custom-control custom-checkbox mb-2"><input type="checkbox" class="custom-control-input" id="<?= $role . '-' . $feature; ?>" name="permissions[<?= $role; ?>][<?= $feature; ?>]" value="1" <?= $checked ? 'checked' : ''; ?>><label class="custom-control-label" for="<?= $role . '-' . $feature; ?>"><?= htmlspecialchars($definition['label'], ENT_QUOTES, 'UTF-8'); ?></label></div>
                    <?php endforeach; ?>
                    <?php if ($role === 'Student'): ?><small class="form-text text-muted">Recommended student access is limited to announcements, homework, date sheets, syllabus, and their own attendance.</small><?php endif; ?>
                </div></div></div>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-success"><i class="fas fa-save mr-1"></i>Save Permissions</button>
        </form>
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
