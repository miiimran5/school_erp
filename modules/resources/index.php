<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';

access_require_roles(array('Admin', 'Teacher', 'Student', 'Parent'));
$conn->set_charset('utf8mb4');

function resource_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$resource_types = array(
    'Study Material' => 'study_materials',
    'Syllabus' => 'syllabus',
    'Time Table' => 'timetable',
    'Date Sheet' => 'date_sheets'
);
$type = trim((string)($_GET['type'] ?? $_POST['resource_type'] ?? 'Study Material'));
if (!isset($resource_types[$type])) {
    http_response_code(400);
    exit('Choose a valid study resource category.');
}
access_require_feature($conn, $resource_types[$type]);
$is_manager = access_is_admin() || access_is_teacher();
$student_id = access_is_student() ? access_student_id() : (access_is_parent() ? access_parent_student_id($conn, (int)($_GET['student_id'] ?? 0)) : 0);
$student = null;
if (access_is_student() || access_is_parent()) {
    if ($student_id <= 0) {
        http_response_code(403);
        exit('No student is linked to this account. Contact the school administrator.');
    }
    $stmt = $conn->prepare('SELECT id,class_id,section_id FROM students WHERE id=? AND status=1 LIMIT 1');
    if (!$stmt) {
        error_log('Resource student lookup failed: ' . $conn->error);
        http_response_code(500);
        exit('Unable to load the selected student.');
    }
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$student) {
        http_response_code(403);
        exit('The linked student is inactive or unavailable.');
    }
}

if (empty($_SESSION['resource_csrf'])) {
    $_SESSION['resource_csrf'] = bin2hex(random_bytes(32));
}
$message = '';
$error = '';
$private_directory = realpath(__DIR__ . '/../../uploads/private_resources');
if ($private_directory === false) {
    $private_directory = __DIR__ . '/../../uploads/private_resources';
}
$allowed_extensions = array(
    'Study Material' => array('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'xls', 'xlsx'),
    'Syllabus' => array('pdf', 'jpg', 'jpeg'),
    'Time Table' => array('pdf', 'jpg', 'jpeg'),
    'Date Sheet' => array('doc', 'docx', 'pdf', 'jpg', 'jpeg')
);
$mime_by_extension = array(
    'pdf' => array('application/pdf'),
    'doc' => array('application/msword', 'application/x-ole-storage'),
    'docx' => array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'),
    'jpg' => array('image/jpeg'),
    'jpeg' => array('image/jpeg'),
    'png' => array('image/png'),
    'xls' => array('application/vnd.ms-excel', 'application/x-ole-storage'),
    'xlsx' => array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip')
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'], $_SESSION['resource_csrf'])
        || !hash_equals((string)$_SESSION['resource_csrf'], (string)$_POST['csrf_token'])) {
        $error = 'This request could not be verified. Refresh the page and try again.';
    } elseif (!$is_manager) {
        http_response_code(403);
        exit('You do not have permission to manage study resources.');
    } elseif (isset($_POST['delete_id'])) {
        $resource_id = filter_var($_POST['delete_id'], FILTER_VALIDATE_INT);
        if (!$resource_id || $resource_id < 1) {
            $error = 'Choose a valid resource to delete.';
        } else {
            $stmt = $conn->prepare('SELECT id,stored_name,class_id,section_id,created_by,created_by_type FROM study_resources WHERE id=? AND resource_type=? LIMIT 1');
            if (!$stmt) {
                $error = 'Unable to prepare the resource lookup: ' . $conn->error;
            } else {
                $stmt->bind_param('is', $resource_id, $type);
                $stmt->execute();
                $item = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $allowed = $item && (
                    access_is_admin()
                    || (access_is_teacher()
                        && (int)$item['created_by'] === (int)($_SESSION['linked_id'] ?? 0)
                        && $item['created_by_type'] === 'Teacher'
                        && access_teacher_can_class($conn, (int)$item['class_id'], (int)$item['section_id']))
                );
                if (!$allowed) {
                    $error = 'You may delete only your own resources for an assigned class.';
                } else {
                    $delete = $conn->prepare('DELETE FROM study_resources WHERE id=?');
                    if (!$delete) {
                        $error = 'Unable to prepare the resource deletion: ' . $conn->error;
                    } else {
                        $delete->bind_param('i', $resource_id);
                        if ($delete->execute()) {
                            $stored_path = $private_directory . DIRECTORY_SEPARATOR . basename($item['stored_name']);
                            if (is_file($stored_path) && !unlink($stored_path)) {
                                error_log('Unable to remove deleted resource file: ' . $stored_path);
                            }
                            $message = 'Resource deleted.';
                        } else {
                            $error = 'Unable to delete the resource: ' . $delete->error;
                        }
                        $delete->close();
                    }
                }
            }
        }
    } else {
        $titles = isset($_POST['title']) && is_array($_POST['title']) ? $_POST['title'] : array();
        $class_ids = isset($_POST['class_id']) && is_array($_POST['class_id']) ? $_POST['class_id'] : array();
        $section_ids = isset($_POST['section_id']) && is_array($_POST['section_id']) ? $_POST['section_id'] : array();
        $files = $_FILES['resource_file'] ?? null;
        if (!$files || !isset($files['name']) || !is_array($files['name'])) {
            $error = 'Select at least one file to upload.';
        } else {
            $conn->begin_transaction();
            $uploaded_paths = array();
            $uploaded_count = 0;
            $insert = $conn->prepare('INSERT INTO study_resources (resource_type,class_id,section_id,session_id,title,stored_name,original_name,mime_type,created_by,created_by_type) VALUES (?,?,NULLIF(?,0),NULLIF(?,0),?,?,?,?,?,?)');
            if (!$insert) {
                $error = 'Unable to prepare the resource upload: ' . $conn->error;
            } else {
                $session_id = (int)($_SESSION['erp_academic_session_id'] ?? 0);
                $created_by_type = access_is_teacher() ? 'Teacher' : 'Admin';
                $created_by = access_is_teacher() ? (int)($_SESSION['linked_id'] ?? 0) : (int)($_SESSION['user_id'] ?? 0);
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                for ($i = 0; $i < count($files['name']); $i++) {
                    $original_name = trim((string)$files['name'][$i]);
                    if ($original_name === '' || (int)$files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    if ((int)$files['error'][$i] !== UPLOAD_ERR_OK || (int)$files['size'][$i] <= 0 || (int)$files['size'][$i] > 10 * 1024 * 1024) {
                        $error = 'Each file must upload successfully and be no larger than 10 MB.';
                        break;
                    }
                    $title = trim((string)($titles[$i] ?? ''));
                    $class_id = filter_var($class_ids[$i] ?? null, FILTER_VALIDATE_INT);
                    $section_id = filter_var($section_ids[$i] ?? null, FILTER_VALIDATE_INT);
                    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    $mime_type = $finfo->file((string)$files['tmp_name'][$i]);
                    $class_valid = false;
                    if ($class_id && $class_id > 0) {
                        $check = $conn->prepare('SELECT 1 FROM classes WHERE id=? AND status=1 LIMIT 1');
                        $check->bind_param('i', $class_id);
                        $check->execute();
                        $class_valid = $check->get_result()->num_rows === 1;
                        $check->close();
                    }
                    $section_valid = true;
                    if ($section_id && $section_id > 0) {
                        $check = $conn->prepare('SELECT 1 FROM sections WHERE id=? AND status=1 LIMIT 1');
                        $check->bind_param('i', $section_id);
                        $check->execute();
                        $section_valid = $check->get_result()->num_rows === 1;
                        $check->close();
                    }
                    if ($title === '' || strlen($title) > 200 || !$class_valid || !$section_valid
                        || !in_array($extension, $allowed_extensions[$type], true)
                        || !in_array($mime_type, $mime_by_extension[$extension] ?? array(), true)) {
                        $error = 'Check each title, active class/section, and allowed file type for this category.';
                        break;
                    }
                    if (access_is_teacher() && !access_teacher_can_class($conn, (int)$class_id, (int)$section_id)) {
                        $error = 'Teachers may upload only for their assigned classes and sections.';
                        break;
                    }
                    if (!is_dir($private_directory) && !mkdir($private_directory, 0750, true) && !is_dir($private_directory)) {
                        $error = 'The private resource upload folder could not be created.';
                        break;
                    }
                    $stored_name = bin2hex(random_bytes(20)) . '.' . $extension;
                    $destination = $private_directory . DIRECTORY_SEPARATOR . $stored_name;
                    if (!move_uploaded_file((string)$files['tmp_name'][$i], $destination)) {
                        $error = 'A selected file could not be stored safely.';
                        break;
                    }
                    $uploaded_paths[] = $destination;
                    $clean_name = basename(str_replace('\\', '/', $original_name));
                    $insert->bind_param(
                        'siiissssis',
                        $type,
                        $class_id,
                        $section_id,
                        $session_id,
                        $title,
                        $stored_name,
                        $clean_name,
                        $mime_type,
                        $created_by,
                        $created_by_type
                    );
                    if (!$insert->execute()) {
                        $error = 'Unable to save a resource record: ' . $insert->error;
                        break;
                    }
                    $uploaded_count++;
                }
                $insert->close();
            }
            if ($error === '' && $uploaded_count > 0 && $conn->commit()) {
                $message = $uploaded_count . ' resource(s) uploaded successfully.';
            } else {
                $conn->rollback();
                foreach ($uploaded_paths as $uploaded_path) {
                    if (is_file($uploaded_path)) {
                        unlink($uploaded_path);
                    }
                }
                if ($error === '') {
                    $error = 'Choose at least one file before submitting.';
                }
            }
        }
    }
}

$classes = array();
$class_result = $conn->query('SELECT id,class_name FROM classes WHERE status=1 ORDER BY class_order,id');
while ($class_result && ($row = $class_result->fetch_assoc())) {
    $classes[] = $row;
}
$sections = array();
$section_result = $conn->query('SELECT id,section_name FROM sections WHERE status=1 ORDER BY section_name');
while ($section_result && ($row = $section_result->fetch_assoc())) {
    $sections[] = $row;
}
$resources = array();
$stmt = $conn->prepare('SELECT r.*,c.class_name,sec.section_name FROM study_resources r LEFT JOIN classes c ON c.id=r.class_id LEFT JOIN sections sec ON sec.id=r.section_id WHERE r.resource_type=? AND (r.session_id IS NULL OR r.session_id=? ) ORDER BY r.created_at DESC');
if (!$stmt) {
    error_log('Study resource listing failed: ' . $conn->error);
    http_response_code(500);
    exit('Unable to load study resources.');
}
$session_id = (int)($_SESSION['erp_academic_session_id'] ?? 0);
$stmt->bind_param('si', $type, $session_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $visible = true;
    if ($student) {
        $visible = ($row['class_id'] === null || (int)$row['class_id'] === (int)$student['class_id'])
            && ($row['section_id'] === null || (int)$row['section_id'] === (int)$student['section_id']);
    } elseif (access_is_teacher() && $row['class_id'] !== null) {
        $visible = access_teacher_can_class($conn, (int)$row['class_id'], (int)$row['section_id']);
    }
    if ($visible) {
        $resources[] = $row;
    }
}
$stmt->close();
$page_title = $type === 'Date Sheet' ? 'Date Sheets' : $type;
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-folder-open"></i> <?= resource_e($page_title); ?></h1></div></section>
    <section class="content"><div class="container-fluid">
        <?php if ($message !== ''): ?><div class="alert alert-success"><?= resource_e($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?= resource_e($error); ?></div><?php endif; ?>
        <?php if ($is_manager): ?>
        <div class="card"><div class="card-header bg-primary text-white"><i class="fas fa-upload mr-1"></i>Add <?= resource_e($page_title); ?></div><div class="card-body">
            <form method="post" enctype="multipart/form-data" id="resource-form">
                <input type="hidden" name="csrf_token" value="<?= resource_e($_SESSION['resource_csrf']); ?>">
                <input type="hidden" name="resource_type" value="<?= resource_e($type); ?>">
                <div id="resource-rows">
                    <div class="form-row resource-row border-bottom mb-3 pb-3">
                        <div class="form-group col-md-3"><label>Class</label><select name="class_id[]" class="form-control" required><option value="">Select class</option><?php foreach ($classes as $class): ?><option value="<?= (int)$class['id']; ?>"><?= resource_e($class['class_name']); ?></option><?php endforeach; ?></select></div>
                        <div class="form-group col-md-2"><label>Section</label><select name="section_id[]" class="form-control"><option value="0">All sections</option><?php foreach ($sections as $section): ?><option value="<?= (int)$section['id']; ?>"><?= resource_e($section['section_name']); ?></option><?php endforeach; ?></select></div>
                        <div class="form-group col-md-3"><label>Title</label><input type="text" name="title[]" class="form-control" maxlength="200" required></div>
                        <div class="form-group col-md-3"><label>File (max 10 MB)</label><input type="file" name="resource_file[]" class="form-control-file" accept="<?= resource_e(implode(',', array_map(function ($extension) { return '.' . $extension; }, $allowed_extensions[$type]))); ?>" required></div>
                        <div class="form-group col-md-1 d-flex align-items-end"><button type="button" class="btn btn-outline-danger remove-resource" title="Remove row"><i class="fas fa-trash"></i></button></div>
                    </div>
                </div>
                <button type="button" id="add-resource-row" class="btn btn-outline-primary"><i class="fas fa-plus mr-1"></i>Add New Row</button>
                <button type="submit" class="btn btn-success ml-2"><i class="fas fa-paper-plane mr-1"></i>Submit</button>
                <small class="form-text text-muted mt-2">Allowed formats: <?= resource_e(strtoupper(implode(', ', $allowed_extensions[$type]))); ?>. Files are private and can only be downloaded after sign-in.</small>
            </form>
        </div></div>
        <?php endif; ?>
        <div class="card"><div class="card-header bg-secondary text-white"><?= resource_e($page_title); ?> Library</div><div class="card-body table-responsive p-0">
            <table class="table table-bordered table-striped mb-0"><thead><tr><th>Title</th><th>Class / Section</th><th>File</th><th>Uploaded</th><?php if ($is_manager): ?><th>Action</th><?php endif; ?></tr></thead><tbody>
            <?php foreach ($resources as $resource): ?><tr><td><?= resource_e($resource['title']); ?></td><td><?= resource_e(($resource['class_name'] ?? 'All classes') . ($resource['section_name'] ? ' / ' . $resource['section_name'] : '')); ?></td><td><a href="<?= BASE_URL; ?>/modules/resources/download.php?id=<?= (int)$resource['id']; ?><?= access_is_parent() ? '&student_id=' . $student_id : ''; ?>"><i class="fas fa-download mr-1"></i><?= resource_e($resource['original_name']); ?></a></td><td><?= resource_e($resource['created_at']); ?></td><?php if ($is_manager): ?><td><?php if (access_is_admin() || ((int)$resource['created_by'] === (int)($_SESSION['linked_id'] ?? 0) && $resource['created_by_type'] === 'Teacher')): ?><form method="post" onsubmit="return confirm('Delete this resource?');"><input type="hidden" name="csrf_token" value="<?= resource_e($_SESSION['resource_csrf']); ?>"><input type="hidden" name="resource_type" value="<?= resource_e($type); ?>"><input type="hidden" name="delete_id" value="<?= (int)$resource['id']; ?>"><button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</button></form><?php endif; ?></td><?php endif; ?></tr><?php endforeach; ?>
            <?php if (!$resources): ?><tr><td colspan="<?= $is_manager ? 5 : 4; ?>" class="text-center text-muted p-4">No resources have been uploaded for this class yet.</td></tr><?php endif; ?>
            </tbody></table>
        </div></div>
    </div></section>
</div>
<?php if ($is_manager): ?><script>
(function () {
    var rows = document.getElementById('resource-rows');
    document.getElementById('add-resource-row').addEventListener('click', function () {
        var clone = rows.querySelector('.resource-row').cloneNode(true);
        clone.querySelectorAll('input').forEach(function (input) { input.value = ''; });
        clone.querySelectorAll('select').forEach(function (select) { select.selectedIndex = 0; });
        rows.appendChild(clone);
    });
    rows.addEventListener('click', function (event) {
        var button = event.target.closest('.remove-resource');
        if (button && rows.querySelectorAll('.resource-row').length > 1) {
            button.closest('.resource-row').remove();
        }
    });
}());
</script><?php endif; ?>
<?php include '../../includes/footer.php'; ?>
