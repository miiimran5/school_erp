<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';

access_require_roles(array('Admin'));
$conn->set_charset('utf8mb4');

function parent_account_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['parent_account_csrf'])) {
    $_SESSION['parent_account_csrf'] = bin2hex(random_bytes(32));
}
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'], $_SESSION['parent_account_csrf'])
        || !hash_equals((string)$_SESSION['parent_account_csrf'], (string)$_POST['csrf_token'])) {
        $error = 'This request could not be verified. Refresh the page and try again.';
    } else {
        $parent_id = filter_var($_POST['parent_id'] ?? null, FILTER_VALIDATE_INT);
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if (!$parent_id || !preg_match('/^[A-Za-z0-9._@+-]{4,50}$/', $username) || strlen($password) < 10) {
            $error = 'Use a valid parent account, a username of 4-50 allowed characters, and a password of at least 10 characters.';
        } else {
            $role_stmt = $conn->prepare("SELECT id FROM roles WHERE id=8 AND role_name='Parent' LIMIT 1");
            if (!$role_stmt) {
                $error = 'The Parent role is not available. Import the latest database migration first.';
            } else {
                $role_stmt->execute();
                $parent_role = $role_stmt->get_result()->fetch_assoc();
                $role_stmt->close();
                if (!$parent_role) {
                    $error = 'The Parent role is not available. Import the latest database migration first.';
                } else {
                    $check = $conn->prepare('SELECT p.parent_name,p.mobile,p.user_id,COUNT(ps.student_id) AS child_count FROM parent_accounts p LEFT JOIN parent_students ps ON ps.parent_id=p.id WHERE p.id=? GROUP BY p.id LIMIT 1');
                    if (!$check) {
                        $error = 'Unable to check the parent account: ' . $conn->error;
                    } else {
                        $check->bind_param('i', $parent_id);
                        $check->execute();
                        $parent = $check->get_result()->fetch_assoc();
                        $check->close();
                        if (!$parent || (int)$parent['child_count'] < 1 || (int)$parent['user_id'] > 0) {
                            $error = 'This parent account has no linked children or already has a login.';
                        } else {
                            $duplicate = $conn->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
                            $duplicate->bind_param('s', $username);
                            $duplicate->execute();
                            $username_exists = $duplicate->get_result()->num_rows > 0;
                            $duplicate->close();
                            if ($username_exists) {
                                $error = 'That username is already in use. Choose another username.';
                            } else {
                                $conn->begin_transaction();
                                $hash = password_hash($password, PASSWORD_DEFAULT);
                                $mobile = (string)($parent['mobile'] ?? '');
                                $create = $conn->prepare("INSERT INTO users (role_id,account_type,linked_id,full_name,username,password,mobile,status) VALUES (8,'Parent',?,?,?,?,?,1)");
                                if (!$create) {
                                    $error = 'Unable to prepare the parent login: ' . $conn->error;
                                } else {
                                    $create->bind_param('issss', $parent_id, $parent['parent_name'], $username, $hash, $mobile);
                                    if ($create->execute()) {
                                        $user_id = (int)$create->insert_id;
                                        $create->close();
                                        $update = $conn->prepare('UPDATE parent_accounts SET user_id=? WHERE id=? AND user_id IS NULL');
                                        if ($update) {
                                            $update->bind_param('ii', $user_id, $parent_id);
                                            $update->execute();
                                            $updated = $update->affected_rows === 1;
                                            $update->close();
                                        } else {
                                            $updated = false;
                                        }
                                        if ($updated && $conn->commit()) {
                                            $message = 'Parent login created. Give the parent the username and password you entered; they can sign in at the normal ERP login page.';
                                        } else {
                                            $conn->rollback();
                                            $error = 'Unable to link the new login to the parent account. No login was saved.';
                                        }
                                    } else {
                                        $error = 'Unable to create the parent login: ' . $create->error;
                                        $create->close();
                                        $conn->rollback();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

$parents = array();
$parent_result = $conn->query('SELECT p.id,p.parent_name,p.mobile,p.user_id,u.username FROM parent_accounts p LEFT JOIN users u ON u.id=p.user_id ORDER BY p.parent_name,p.id');
while ($parent_result && ($row = $parent_result->fetch_assoc())) {
    $parents[(int)$row['id']] = $row;
    $parents[(int)$row['id']]['children'] = array();
}
$child_stmt = $conn->prepare('SELECT ps.parent_id,s.admission_no,s.student_name,c.class_name,sec.section_name FROM parent_students ps JOIN students s ON s.id=ps.student_id JOIN classes c ON c.id=s.class_id JOIN sections sec ON sec.id=s.section_id ORDER BY s.student_name');
if ($child_stmt) {
    $child_stmt->execute();
    $child_result = $child_stmt->get_result();
    while ($row = $child_result->fetch_assoc()) {
        if (isset($parents[(int)$row['parent_id']])) {
            $parents[(int)$row['parent_id']]['children'][] = $row;
        }
    }
    $child_stmt->close();
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-users-cog"></i> Parent Portal Accounts</h1><p class="text-muted mb-0">Student admission numbers and school records remain separate; linked parents can switch between their children.</p></div></section>
    <section class="content"><div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?= parent_account_e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= parent_account_e($error); ?></div><?php endif; ?>
        <div class="card"><div class="card-body table-responsive p-0"><table class="table table-bordered mb-0"><thead><tr><th>Parent</th><th>Mobile</th><th>Linked Students</th><th>Login</th><th>Create Parent Login</th></tr></thead><tbody>
            <?php foreach ($parents as $parent): ?><tr><td><?= parent_account_e($parent['parent_name']); ?></td><td><?= parent_account_e($parent['mobile']); ?></td><td><?php foreach ($parent['children'] as $child): ?><div><?= parent_account_e($child['student_name'] . ' (' . $child['admission_no'] . ') - ' . $child['class_name'] . '/' . $child['section_name']); ?></div><?php endforeach; ?></td><td><?= $parent['username'] ? parent_account_e($parent['username']) : '<span class="text-muted">Not created</span>'; ?></td><td>
                <?php if (!$parent['user_id'] && $parent['children']): ?><form method="post" class="form-row"><input type="hidden" name="csrf_token" value="<?= parent_account_e($_SESSION['parent_account_csrf']); ?>"><input type="hidden" name="parent_id" value="<?= (int)$parent['id']; ?>"><div class="col-md-4 mb-2"><input type="text" name="username" class="form-control" minlength="4" maxlength="50" placeholder="Username" required></div><div class="col-md-5 mb-2"><input type="password" name="password" class="form-control" minlength="10" placeholder="Set password (10+ characters)" required></div><div class="col-md-3"><button class="btn btn-primary btn-block">Create Login</button></div></form><?php else: ?><span class="text-muted">Login already linked</span><?php endif; ?>
            </td></tr><?php endforeach; ?>
            <?php if (!$parents): ?><tr><td colspan="5" class="text-center text-muted p-4">No sibling parent groups exist yet. Link a sibling while admitting a student or importing the CSV template.</td></tr><?php endif; ?>
        </tbody></table></div></div>
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
