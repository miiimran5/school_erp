<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
require_once '../../includes/portal_attachments.php';

access_require_roles(array('Admin', 'Teacher', 'Student', 'Parent'));
if (!access_is_admin()
    && !access_feature_allowed($conn, 'announcements')
    && !access_feature_allowed($conn, 'homework')) {
    http_response_code(403);
    exit('Announcements and homework are not enabled for your account.');
}
$conn->set_charset('utf8mb4');

function announcement_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$role = access_role();
$is_manager = access_is_admin() || access_is_teacher();
$teacher_id = (int)($_SESSION['linked_id'] ?? 0);
$student_id = access_is_student() ? access_student_id() : (access_is_parent() ? access_parent_student_id($conn, (int)($_GET['student_id'] ?? 0)) : 0);
$student = null;
if ($student_id > 0) {
    $student_stmt = $conn->prepare('SELECT id,class_id,section_id FROM students WHERE id=? AND status=1 LIMIT 1');
    if (!$student_stmt) {
        error_log('Announcement student lookup failed: ' . $conn->error);
        http_response_code(500);
        exit('Unable to load the selected student.');
    }
    $student_stmt->bind_param('i', $student_id);
    $student_stmt->execute();
    $student = $student_stmt->get_result()->fetch_assoc();
    $student_stmt->close();
}
if (($role === 'Student' || access_is_parent()) && !$student) {
    http_response_code(403);
    exit('The linked student is inactive or unavailable.');
}
if (empty($_SESSION['announcement_csrf'])) {
    $_SESSION['announcement_csrf'] = bin2hex(random_bytes(32));
}
$message = '';
$error = '';
$attachment_directory = realpath(__DIR__ . '/../../uploads/private_resources');
$class_options = array();
$section_options = array();
if (access_is_admin()) {
    $classes_result = $conn->query('SELECT id,class_name FROM classes WHERE status=1 ORDER BY class_order,id');
    while ($classes_result && ($row = $classes_result->fetch_assoc())) {
        $class_options[] = $row;
    }
    $sections_result = $conn->query('SELECT id,section_name FROM sections WHERE status=1 ORDER BY section_name');
    while ($sections_result && ($row = $sections_result->fetch_assoc())) {
        $section_options[] = $row;
    }
} elseif (access_is_teacher()) {
    $scope_stmt = $conn->prepare(
        'SELECT DISTINCT a.class_id,COALESCE(a.section_id,0) AS section_id,c.class_name,COALESCE(sec.section_name,"All sections") AS section_name
         FROM teacher_class_assignments a JOIN classes c ON c.id=a.class_id LEFT JOIN sections sec ON sec.id=a.section_id
         WHERE a.teacher_id=? AND a.status=1
         UNION
         SELECT DISTINCT r.class_id,r.section_id,c.class_name,sec.section_name
         FROM teacher_class_responsibilities r JOIN classes c ON c.id=r.class_id JOIN sections sec ON sec.id=r.section_id
         WHERE r.teacher_id=? AND r.status=1
         ORDER BY class_name,section_name'
    );
    if ($scope_stmt) {
        $scope_stmt->bind_param('ii', $teacher_id, $teacher_id);
        $scope_stmt->execute();
        $scope_result = $scope_stmt->get_result();
        while ($row = $scope_result->fetch_assoc()) {
            $class_options[] = $row;
        }
        $scope_stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'], $_SESSION['announcement_csrf'])
        || !hash_equals((string)$_SESSION['announcement_csrf'], (string)$_POST['csrf_token'])) {
        $error = 'This request could not be verified. Refresh the page and try again.';
    } elseif (!$is_manager) {
        http_response_code(403);
        exit('You do not have permission to post announcements or homework.');
    } else {
        $action = (string)($_POST['action'] ?? '');
        $action_feature = in_array($action, array('homework', 'delete_homework'), true) ? 'homework' : 'announcements';
        if (!access_is_admin() && !access_feature_allowed($conn, $action_feature)) {
            http_response_code(403);
            exit('This feature is not enabled for your account.');
        }
        $class_id = filter_var($_POST['class_id'] ?? 0, FILTER_VALIDATE_INT);
        $section_id = filter_var($_POST['section_id'] ?? 0, FILTER_VALIDATE_INT);
        $can_post_scope = $class_id === 0 && access_is_admin();
        if ($class_id > 0) {
            $can_post_scope = access_is_admin() || access_teacher_can_class($conn, $class_id, max(0, (int)$section_id));
        }
        if ($action === 'delete_announcement' || $action === 'delete_homework') {
            $item_id = filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT);
            $table = $action === 'delete_announcement' ? 'portal_announcements' : 'portal_homework';
            if (!$item_id || $item_id < 1) {
                $error = 'Choose a valid item to delete.';
            } elseif (access_is_admin()) {
                $file_key = $action === 'delete_announcement' ? 'announcement_id' : 'homework_id';
                $file_query = $conn->prepare("SELECT stored_name FROM portal_content_attachments WHERE $file_key=?");
                $stored_files = array();
                if ($file_query) {
                    $file_query->bind_param('i', $item_id);
                    $file_query->execute();
                    $file_result = $file_query->get_result();
                    while ($file_row = $file_result->fetch_assoc()) {
                        $stored_files[] = $file_row['stored_name'];
                    }
                    $file_query->close();
                }
                $delete = $conn->prepare('DELETE FROM ' . $table . ' WHERE id=?');
                if (!$delete) {
                    $error = 'Unable to prepare deletion: ' . $conn->error;
                } else {
                    $delete->bind_param('i', $item_id);
                    $message = $delete->execute() ? 'Item deleted.' : 'Unable to delete the item: ' . $delete->error;
                    $delete->close();
                    if ($message === 'Item deleted.' && $attachment_directory !== false) {
                        foreach ($stored_files as $stored_file) {
                            $file_path = $attachment_directory . DIRECTORY_SEPARATOR . basename($stored_file);
                            if (is_file($file_path) && !unlink($file_path)) {
                                error_log('Unable to remove deleted announcement attachment: ' . $file_path);
                            }
                        }
                    }
                }
            } else {
                $delete = $conn->prepare('DELETE FROM ' . $table . ' WHERE id=? AND created_by=?');
                if (!$delete) {
                    $error = 'Unable to prepare deletion: ' . $conn->error;
                } else {
                    $file_key = $action === 'delete_announcement' ? 'announcement_id' : 'homework_id';
                    $file_query = $conn->prepare("SELECT stored_name FROM portal_content_attachments WHERE $file_key=?");
                    $stored_files = array();
                    if ($file_query) {
                        $file_query->bind_param('i', $item_id);
                        $file_query->execute();
                        $file_result = $file_query->get_result();
                        while ($file_row = $file_result->fetch_assoc()) {
                            $stored_files[] = $file_row['stored_name'];
                        }
                        $file_query->close();
                    }
                    $delete->bind_param('ii', $item_id, $teacher_id);
                    $delete->execute();
                    $deleted = $delete->affected_rows > 0;
                    $message = $deleted ? 'Item deleted.' : 'You may delete only items you posted.';
                    $delete->close();
                    if ($deleted && $attachment_directory !== false) {
                        foreach ($stored_files as $stored_file) {
                            $file_path = $attachment_directory . DIRECTORY_SEPARATOR . basename($stored_file);
                            if (is_file($file_path) && !unlink($file_path)) {
                                error_log('Unable to remove deleted announcement attachment: ' . $file_path);
                            }
                        }
                    }
                }
            }
        } elseif (!$can_post_scope) {
            $error = 'Teachers may post only to their assigned classes and sections.';
        } else {
            $title = trim((string)($_POST['title'] ?? ''));
            $body = trim((string)($_POST['body'] ?? ''));
            $today = date('Y-m-d');
            $created_by = access_is_teacher() ? $teacher_id : (int)($_SESSION['user_id'] ?? 0);
            $class_value = $class_id > 0 ? $class_id : null;
            $section_value = $section_id > 0 ? $section_id : null;
            if (!in_array($action, array('announcement', 'homework'), true) || $title === '' || strlen($title) > 150 || $body === '') {
                $error = 'Enter a title (up to 150 characters) and message.';
            } elseif ($action === 'announcement') {
                $publish_date = trim((string)($_POST['publish_date'] ?? $today));
                $parsed_date = DateTime::createFromFormat('!Y-m-d', $publish_date);
                $audience = access_is_teacher() ? 'Students' : (string)($_POST['audience'] ?? 'All');
                if (!in_array($audience, array('All', 'Students', 'Teachers'), true)
                    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $publish_date)
                    || !$parsed_date || $parsed_date->format('Y-m-d') !== $publish_date) {
                    $error = 'Choose a valid audience and publish date.';
                } else {
                    $insert = $conn->prepare('INSERT INTO portal_announcements (title,body,audience,class_id,section_id,publish_date,created_by,status) VALUES (?,?,?,?,?,?,?,1)');
                    if (!$insert) {
                        $error = 'Unable to prepare the announcement: ' . $conn->error;
                    } else {
                        $insert->bind_param('sssiisi', $title, $body, $audience, $class_value, $section_value, $publish_date, $created_by);
                        $conn->begin_transaction();
                        if ($insert->execute()) {
                            $attachment_result = portal_attachment_uploads($conn, $_FILES['attachments'] ?? array(), 'announcement', (int)$insert->insert_id, $created_by);
                            if ($attachment_result['error'] === '' && $conn->commit()) {
                                $message = 'Announcement published.';
                            } else {
                                $conn->rollback();
                                foreach ($attachment_result['paths'] as $stored_path) {
                                    if (is_file($stored_path)) {
                                        unlink($stored_path);
                                    }
                                }
                                $error = $attachment_result['error'] !== '' ? $attachment_result['error'] : 'Unable to save the announcement. No changes were saved.';
                            }
                        } else {
                            $conn->rollback();
                            $error = 'Unable to publish the announcement: ' . $insert->error;
                        }
                        $insert->close();
                    }
                }
            } else {
                if (!$class_value) {
                    $error = 'Select a class for homework.';
                } else {
                    $due_date = trim((string)($_POST['due_date'] ?? ''));
                    $subject_id = filter_var($_POST['subject_id'] ?? 0, FILTER_VALIDATE_INT);
                    $valid_due_date = $due_date === '' || (preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)
                        && ($parsed_due_date = DateTime::createFromFormat('!Y-m-d', $due_date))
                        && $parsed_due_date->format('Y-m-d') === $due_date);
                    if (!$valid_due_date) {
                        $error = 'Enter a valid homework due date.';
                    } else {
                        if ($subject_id > 0) {
                            $subject_check = $conn->prepare('SELECT 1 FROM class_subjects WHERE class_id=? AND subject_id=? AND status=1 LIMIT 1');
                            $subject_check->bind_param('ii', $class_value, $subject_id);
                            $subject_check->execute();
                            $subject_valid = $subject_check->get_result()->num_rows === 1;
                            $subject_check->close();
                        } else {
                            $subject_valid = true;
                            $subject_id = null;
                        }
                        if (!$subject_valid) {
                            $error = 'Choose a subject assigned to the selected class.';
                        } else {
                            $insert = $conn->prepare('INSERT INTO portal_homework (class_id,section_id,subject_id,title,description,due_date,created_by,status) VALUES (?,?,?,?,?,NULLIF(?,\'\'),?,1)');
                            if (!$insert) {
                                $error = 'Unable to prepare homework: ' . $conn->error;
                            } else {
                                $insert->bind_param('iiisssi', $class_value, $section_value, $subject_id, $title, $body, $due_date, $created_by);
                                $conn->begin_transaction();
                                if ($insert->execute()) {
                                    $attachment_result = portal_attachment_uploads($conn, $_FILES['attachments'] ?? array(), 'homework', (int)$insert->insert_id, $created_by);
                                    if ($attachment_result['error'] === '' && $conn->commit()) {
                                        $message = 'Homework posted.';
                                    } else {
                                        $conn->rollback();
                                        foreach ($attachment_result['paths'] as $stored_path) {
                                            if (is_file($stored_path)) {
                                                unlink($stored_path);
                                            }
                                        }
                                        $error = $attachment_result['error'] !== '' ? $attachment_result['error'] : 'Unable to save homework. No changes were saved.';
                                    }
                                } else {
                                    $conn->rollback();
                                    $error = 'Unable to post homework: ' . $insert->error;
                                }
                                $insert->close();
                            }
                        }
                    }
                }
            }
        }
    }
}

$announcements = array();
$homework = array();
$announcement_attachments = array();
$homework_attachments = array();
if (access_feature_allowed($conn, 'announcements') && access_is_admin()) {
    $announcement_query = $conn->query('SELECT * FROM portal_announcements WHERE status=1 AND publish_date<=CURDATE() ORDER BY publish_date DESC,id DESC');
} elseif (access_feature_allowed($conn, 'announcements') && access_is_teacher()) {
    $announcement_query = $conn->prepare("SELECT * FROM portal_announcements WHERE status=1 AND publish_date<=CURDATE() AND (audience IN ('All','Teachers') OR created_by=?) ORDER BY publish_date DESC,id DESC");
    if ($announcement_query) {
        $announcement_query->bind_param('i', $teacher_id);
        $announcement_query->execute();
        $announcement_query = $announcement_query->get_result();
    }
} elseif (access_feature_allowed($conn, 'announcements')) {
    $announcement_query = $conn->prepare("SELECT * FROM portal_announcements WHERE status=1 AND audience IN ('All','Students') AND publish_date<=CURDATE() AND (class_id IS NULL OR class_id=?) AND (section_id IS NULL OR section_id=?) ORDER BY publish_date DESC,id DESC");
    if ($announcement_query) {
        $announcement_query->bind_param('ii', $student['class_id'], $student['section_id']);
        $announcement_query->execute();
        $announcement_query = $announcement_query->get_result();
    }
}
while ($announcement_query && ($row = $announcement_query->fetch_assoc())) {
    if (access_is_teacher() && $row['class_id'] !== null && !access_teacher_can_class($conn, (int)$row['class_id'], (int)$row['section_id'])) {
        continue;
    }
    $announcements[] = $row;
}
if (access_feature_allowed($conn, 'homework') && access_is_admin()) {
    $homework_query = $conn->query('SELECT h.*,c.class_name,sec.section_name FROM portal_homework h JOIN classes c ON c.id=h.class_id LEFT JOIN sections sec ON sec.id=h.section_id WHERE h.status=1 ORDER BY h.created_at DESC');
} elseif (access_feature_allowed($conn, 'homework') && access_is_teacher()) {
    $homework_query = $conn->prepare('SELECT h.*,c.class_name,sec.section_name FROM portal_homework h JOIN classes c ON c.id=h.class_id LEFT JOIN sections sec ON sec.id=h.section_id WHERE h.status=1 AND (h.created_by=? OR h.class_id IN (SELECT class_id FROM teacher_class_responsibilities WHERE teacher_id=? AND status=1) OR h.class_id IN (SELECT class_id FROM teacher_class_assignments WHERE teacher_id=? AND status=1)) ORDER BY h.created_at DESC');
    if ($homework_query) {
        $homework_query->bind_param('iii', $teacher_id, $teacher_id, $teacher_id);
        $homework_query->execute();
        $homework_query = $homework_query->get_result();
    }
} elseif (access_feature_allowed($conn, 'homework')) {
    $homework_query = $conn->prepare('SELECT h.*,c.class_name,sec.section_name FROM portal_homework h JOIN classes c ON c.id=h.class_id LEFT JOIN sections sec ON sec.id=h.section_id WHERE h.status=1 AND h.class_id=? AND (h.section_id IS NULL OR h.section_id=?) ORDER BY h.due_date DESC,h.created_at DESC');
    if ($homework_query) {
        $homework_query->bind_param('ii', $student['class_id'], $student['section_id']);
        $homework_query->execute();
        $homework_query = $homework_query->get_result();
    }
}
while ($homework_query && ($row = $homework_query->fetch_assoc())) {
    if (access_is_teacher() && $row['section_id'] !== null && !access_teacher_can_class($conn, (int)$row['class_id'], (int)$row['section_id'])) {
        continue;
    }
    $homework[] = $row;
}
$announcement_ids = array_map('intval', array_column($announcements, 'id'));
$homework_ids = array_map('intval', array_column($homework, 'id'));
if ($announcement_ids || $homework_ids) {
    $attachment_conditions = array();
    if ($announcement_ids) {
        $attachment_conditions[] = 'announcement_id IN (' . implode(',', $announcement_ids) . ')';
    }
    if ($homework_ids) {
        $attachment_conditions[] = 'homework_id IN (' . implode(',', $homework_ids) . ')';
    }
    $attachment_query = $conn->query('SELECT id,announcement_id,homework_id,original_name FROM portal_content_attachments WHERE ' . implode(' OR ', $attachment_conditions));
    if ($attachment_query) {
        while ($attachment = $attachment_query->fetch_assoc()) {
            if ($attachment['announcement_id'] !== null) {
                $announcement_attachments[(int)$attachment['announcement_id']][] = $attachment;
            } else {
                $homework_attachments[(int)$attachment['homework_id']][] = $attachment;
            }
        }
    }
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-bullhorn"></i> Announcements & Homework</h1></div></section>
    <section class="content"><div class="container-fluid">
        <?php if ($student_id > 0 && access_is_parent()): ?><p><a href="parent.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i>Back to Family Portal</a></p><?php endif; ?>
        <?php if ($message): ?><div class="alert alert-success"><?= announcement_e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= announcement_e($error); ?></div><?php endif; ?>
        <?php if ($is_manager): ?>
        <div class="card"><div class="card-header bg-primary text-white">Post notice or homework</div><div class="card-body">
            <form method="post" enctype="multipart/form-data" class="form-row">
                <input type="hidden" name="csrf_token" value="<?= announcement_e($_SESSION['announcement_csrf']); ?>">
                <div class="form-group col-md-3"><label>Post type</label><select name="action" id="post-action" class="form-control"><?php if (access_feature_allowed($conn, 'announcements')): ?><option value="announcement">Announcement / Notice</option><?php endif; ?><?php if (access_feature_allowed($conn, 'homework')): ?><option value="homework">Homework</option><?php endif; ?></select></div>
                <div class="form-group col-md-3"><label>Class</label><select name="class_id" id="post-class" class="form-control"><option value="0">All classes</option><?php foreach ($class_options as $class): ?><option value="<?= (int)$class['id']; ?>" data-section="<?= (int)($class['section_id'] ?? 0); ?>"><?= announcement_e($class['class_name'] . (isset($class['section_name']) ? ' - ' . $class['section_name'] : '')); ?></option><?php endforeach; ?></select></div>
                <div class="form-group col-md-2"><label>Section</label><select name="section_id" class="form-control"><option value="0">All sections</option><?php foreach ($section_options as $section): ?><option value="<?= (int)$section['id']; ?>"><?= announcement_e($section['section_name']); ?></option><?php endforeach; ?></select></div>
                <?php if (access_is_admin()): ?><div class="form-group col-md-2" id="audience-wrap"><label>Audience</label><select name="audience" class="form-control"><option>All</option><option>Students</option><option>Teachers</option></select></div><?php endif; ?>
                <div class="form-group col-md-3"><label>Title</label><input type="text" name="title" maxlength="150" class="form-control" required></div>
                <div class="form-group col-md-2" id="subject-wrap"><label>Subject ID (optional)</label><input type="number" min="1" name="subject_id" class="form-control"></div>
                <div class="form-group col-md-2" id="date-wrap"><label>Due / publish date</label><input type="date" name="publish_date" value="<?= date('Y-m-d'); ?>" class="form-control"></div>
                <div class="form-group col-md-2" id="due-wrap" style="display:none"><label>Homework due date</label><input type="date" name="due_date" class="form-control"></div>
                <div class="form-group col-md-9"><label>Message</label><textarea name="body" class="form-control" rows="2" required></textarea></div>
                <div class="form-group col-md-9"><label>Attachments (optional, up to 5 files, 10 MB each)</label><input type="file" name="attachments[]" class="form-control-file" accept=".jpg,.jpeg,.pdf,.docx,image/jpeg,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" multiple><small class="form-text text-muted">Allowed formats: JPEG, PDF, DOCX.</small></div>
                <div class="form-group col-md-3 d-flex align-items-end"><button class="btn btn-success btn-block"><i class="fas fa-paper-plane mr-1"></i>Publish</button></div>
            </form>
            <?php if (access_is_teacher() && !$class_options): ?><p class="text-danger mb-0">No class responsibility is assigned to this teacher yet.</p><?php endif; ?>
        </div></div>
        <?php endif; ?>
        <?php if (access_feature_allowed($conn, 'announcements')): ?><div class="card"><div class="card-header bg-warning">Notices and Announcements</div><div class="card-body">
            <?php foreach ($announcements as $item): ?><article class="border-bottom pb-2 mb-2"><strong><?= announcement_e($item['title']); ?></strong><span class="badge badge-info ml-2"><?= announcement_e($item['audience']); ?></span><small class="d-block text-muted"><?= announcement_e($item['publish_date']); ?></small><p><?= nl2br(announcement_e($item['body'])); ?></p><?php foreach ($announcement_attachments[(int)$item['id']] ?? array() as $attachment): ?><a class="btn btn-sm btn-outline-secondary mb-2" href="download_attachment.php?id=<?= (int)$attachment['id']; ?><?= access_is_parent() ? '&student_id=' . $student_id : ''; ?>"><i class="fas fa-paperclip mr-1"></i><?= announcement_e($attachment['original_name']); ?></a> <?php endforeach; ?><?php if ($is_manager && (access_is_admin() || (int)$item['created_by'] === $teacher_id)): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= announcement_e($_SESSION['announcement_csrf']); ?>"><input type="hidden" name="action" value="delete_announcement"><input type="hidden" name="item_id" value="<?= (int)$item['id']; ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form><?php endif; ?></article><?php endforeach; ?>
            <?php if (!$announcements): ?><p class="text-muted mb-0">No announcements are available.</p><?php endif; ?>
        </div></div><?php endif; ?>
        <?php if (access_feature_allowed($conn, 'homework')): ?><div class="card"><div class="card-header bg-primary text-white">Homework</div><div class="card-body">
            <?php foreach ($homework as $item): ?><article class="border-bottom pb-2 mb-2"><strong><?= announcement_e($item['title']); ?></strong><small class="d-block text-muted"><?= announcement_e(($item['class_name'] ?? '') . ($item['section_name'] ? ' - ' . $item['section_name'] : '') . ($item['due_date'] ? ' | Due: ' . $item['due_date'] : '')); ?></small><p><?= nl2br(announcement_e($item['description'])); ?></p><?php foreach ($homework_attachments[(int)$item['id']] ?? array() as $attachment): ?><a class="btn btn-sm btn-outline-secondary mb-2" href="download_attachment.php?id=<?= (int)$attachment['id']; ?><?= access_is_parent() ? '&student_id=' . $student_id : ''; ?>"><i class="fas fa-paperclip mr-1"></i><?= announcement_e($attachment['original_name']); ?></a> <?php endforeach; ?><?php if ($is_manager && (access_is_admin() || (int)$item['created_by'] === $teacher_id)): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= announcement_e($_SESSION['announcement_csrf']); ?>"><input type="hidden" name="action" value="delete_homework"><input type="hidden" name="item_id" value="<?= (int)$item['id']; ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form><?php endif; ?></article><?php endforeach; ?>
            <?php if (!$homework): ?><p class="text-muted mb-0">No homework is available.</p><?php endif; ?>
        </div></div><?php endif; ?>
    </div></section>
</div>
<script>
(function () {
    var action = document.getElementById('post-action');
    if (!action) return;
    function updatePostFields() {
        var homework = action.value === 'homework';
        document.getElementById('subject-wrap').style.display = homework ? '' : 'none';
        document.getElementById('due-wrap').style.display = homework ? '' : 'none';
        document.getElementById('date-wrap').style.display = homework ? 'none' : '';
        var classSelect = document.getElementById('post-class');
        if (homework) {
            classSelect.options[0].disabled = true;
            if (classSelect.value === '0') classSelect.value = '';
        } else {
            classSelect.options[0].disabled = false;
        }
    }
    action.addEventListener('change', updatePostFields);
    updatePostFields();
}());
</script>
<?php include '../../includes/footer.php'; ?>
