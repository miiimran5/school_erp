<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
access_require_login();

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    http_response_code(403);
    exit('This default portal login does not have a saved user profile yet. Ask the school administrator to create your login account.');
}
$conn->set_charset('utf8mb4');
if (empty($_SESSION['profile_csrf'])) {
    $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
}

function user_profile_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim((string)($_POST['full_name'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $mobile = trim((string)($_POST['mobile'] ?? ''));
    $token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals((string)$_SESSION['profile_csrf'], $token)) {
        $error = 'This form expired. Refresh the page and try again.';
    } elseif ($full_name === '' || strlen($full_name) > 100 || $username === '' || strlen($username) > 50) {
        $error = 'Enter a name and login ID within the allowed length.';
    } elseif ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($mobile) > 15) {
        $error = 'Mobile number must be 15 characters or fewer.';
    } else {
        $check = $conn->prepare('SELECT id FROM users WHERE username=? AND id<>? LIMIT 1');
        if (!$check) {
            $error = 'Unable to check the login ID: ' . $conn->error;
        } else {
            $check->bind_param('si', $username, $user_id);
            if (!$check->execute()) {
                $error = 'Unable to check the login ID: ' . $check->error;
            } elseif ($check->get_result()->num_rows > 0) {
                $error = 'That login ID is already being used.';
            }
            $check->close();
        }
        if ($error === '') {
            $stmt = $conn->prepare('UPDATE users SET full_name=?, username=?, email=NULLIF(?, \'\'), mobile=NULLIF(?, \'\') WHERE id=?');
            if (!$stmt) {
                $error = 'Unable to prepare profile update: ' . $conn->error;
            } else {
                $stmt->bind_param('ssssi', $full_name, $username, $email, $mobile, $user_id);
                if ($stmt->execute()) {
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['username'] = $username;
                    $message = 'Your profile was updated.';
                } else {
                    $error = 'Unable to update the profile: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

$stmt = $conn->prepare('SELECT full_name, username, email, mobile FROM users WHERE id=? LIMIT 1');
if (!$stmt) {
    die('Unable to load profile: ' . user_profile_e($conn->error));
}
$stmt->bind_param('i', $user_id);
if (!$stmt->execute()) {
    die('Unable to load profile: ' . user_profile_e($stmt->error));
}
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$profile) {
    http_response_code(404);
    exit('User profile not found.');
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-user-edit"></i> My Profile</h1></div></section>
    <section class="content"><div class="container-fluid">
        <?php if ($message !== ''): ?><div class="alert alert-success"><?= user_profile_e($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?= user_profile_e($error); ?></div><?php endif; ?>
        <div class="card"><div class="card-header bg-primary text-white">Personal and sign-in details</div><div class="card-body">
            <form method="post" class="row" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= user_profile_e($_SESSION['profile_csrf']); ?>">
                <div class="form-group col-md-6"><label for="full_name">Display name</label><input id="full_name" name="full_name" class="form-control" maxlength="100" required value="<?= user_profile_e($profile['full_name']); ?>"></div>
                <div class="form-group col-md-6"><label for="username">Login ID</label><input id="username" name="username" class="form-control" maxlength="50" required value="<?= user_profile_e($profile['username']); ?>"></div>
                <div class="form-group col-md-6"><label for="email">Email</label><input id="email" name="email" type="email" class="form-control" maxlength="100" value="<?= user_profile_e($profile['email']); ?>"></div>
                <div class="form-group col-md-6"><label for="mobile">Mobile</label><input id="mobile" name="mobile" class="form-control" maxlength="15" value="<?= user_profile_e($profile['mobile']); ?>"></div>
                <div class="col-12"><button class="btn btn-success"><i class="fas fa-save mr-1"></i>Save profile</button></div>
            </form>
        </div></div>
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
