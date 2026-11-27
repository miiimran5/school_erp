<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
require_once '../../includes/office.php';

access_require_roles(array('Admin', 'Teacher'));
$conn->set_charset('utf8mb4');
office_ensure_schema($conn);
access_require_feature($conn, 'student_records');

if (empty($_SESSION['student_photos_csrf'])) {
    $_SESSION['student_photos_csrf'] = bin2hex(random_bytes(32));
}
$results = array();
$page_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals((string)$_SESSION['student_photos_csrf'], (string)$_POST['csrf_token'])) {
        $page_error = 'This request could not be verified. Refresh the page and try again.';
    } elseif (!isset($_FILES['photos']) || !is_array($_FILES['photos']['name'])) {
        $page_error = 'Choose one or more student photos to upload.';
    } else {
        $file_count = count($_FILES['photos']['name']);
        if ($file_count > 20) {
            $page_error = 'Upload no more than 20 photos at a time.';
        } else {
            $seen_admissions = array();
            for ($i = 0; $i < $file_count; $i++) {
                if ((int)$_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $original_name = basename((string)$_FILES['photos']['name'][$i]);
                $admission_no = pathinfo($original_name, PATHINFO_FILENAME);
                if ($admission_no === '' || isset($seen_admissions[$admission_no])) {
                    $results[] = array('ok' => false, 'name' => $original_name, 'message' => 'The filename is empty or duplicates another admission number in this upload.');
                    continue;
                }
                $seen_admissions[$admission_no] = true;
                $lookup = $conn->prepare('SELECT id, class_id, section_id, photo FROM students WHERE admission_no=? AND status=1 LIMIT 1');
                if (!$lookup) {
                    error_log('Student photo lookup prepare failed: ' . $conn->error);
                    $results[] = array('ok' => false, 'name' => $original_name, 'message' => 'Unable to look up this admission number.');
                    continue;
                }
                $lookup->bind_param('s', $admission_no);
                if (!$lookup->execute()) {
                    error_log('Student photo lookup failed: ' . $lookup->error);
                    $lookup->close();
                    $results[] = array('ok' => false, 'name' => $original_name, 'message' => 'Unable to look up this admission number.');
                    continue;
                }
                $student = $lookup->get_result()->fetch_assoc();
                $lookup->close();
                if (!$student) {
                    $results[] = array('ok' => false, 'name' => $original_name, 'message' => 'No active student was found with this admission number.');
                    continue;
                }
                if (access_is_teacher() && !access_teacher_can_class($conn, (int)$student['class_id'], (int)$student['section_id'])) {
                    $results[] = array('ok' => false, 'name' => $original_name, 'message' => "You are not assigned to this student's class and section.");
                    continue;
                }
                $upload = array(
                    'error' => (int)$_FILES['photos']['error'][$i],
                    'name' => $original_name,
                    'size' => (int)$_FILES['photos']['size'][$i],
                    'tmp_name' => (string)$_FILES['photos']['tmp_name'][$i]
                );
                try {
                    $new_photo = office_process_student_photo($upload);
                    $update = $conn->prepare('UPDATE students SET photo=? WHERE id=?');
                    if (!$update) {
                        office_delete_student_photo($new_photo);
                        error_log('Student photo update prepare failed: ' . $conn->error);
                        throw new RuntimeException('Unable to prepare the photo update.');
                    }
                    $update->bind_param('si', $new_photo, $student['id']);
                    if (!$update->execute()) {
                        $db_error = $update->error;
                        $update->close();
                        office_delete_student_photo($new_photo);
                        error_log('Student photo update failed: ' . $db_error);
                        throw new RuntimeException('Unable to save the photo for this student.');
                    }
                    $update->close();
                    office_delete_student_photo((string)$student['photo']);
                    $results[] = array('ok' => true, 'name' => $original_name, 'message' => 'Uploaded and resized to 300 KB or less.');
                } catch (Throwable $exception) {
                    $results[] = array('ok' => false, 'name' => $original_name, 'message' => $exception->getMessage());
                }
            }
        }
    }
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1>Student Photo Upload</h1></div></section>
    <section class="content"><div class="container-fluid">
        <?php if ($page_error !== ''): ?><div class="alert alert-danger"><?= office_e($page_error); ?></div><?php endif; ?>
        <?php if ($results): ?>
            <div class="card"><div class="card-header"><h3 class="card-title">Upload results</h3></div><div class="card-body">
                <?php foreach ($results as $result): ?>
                    <div class="alert <?= $result['ok'] ? 'alert-success' : 'alert-danger'; ?> py-2 mb-2"><strong><?= office_e($result['name']); ?></strong>: <?= office_e($result['message']); ?></div>
                <?php endforeach; ?>
            </div></div>
        <?php endif; ?>
        <div class="card"><div class="card-body">
            <p>Select up to 20 student images in one step. Name each image with the student's exact admission number, for example <strong>ADM102.jpg</strong>. The system matches it to an active student and compresses it to 300 KB or less.</p>
            <?php if (access_is_teacher()): ?><p class="text-info">Teachers can only upload photos for students in their assigned classes and sections. Files for other students will be rejected.</p><?php else: ?><p class="text-info">Administrators can upload photos for students in any class.</p><?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= office_e($_SESSION['student_photos_csrf']); ?>">
                <div class="form-group">
                    <label for="photos">Choose student photos (JPG, PNG, or WEBP; each file up to 5 MB)</label>
                    <input type="file" id="photos" name="photos[]" accept=".jpg,.jpeg,.png,.webp" multiple required class="form-control-file">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload Photos</button>
                <a href="index.php" class="btn btn-secondary">Back to Students</a>
            </form>
        </div></div>
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
