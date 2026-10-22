<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
access_require_roles(array('Student'));
$conn->set_charset('utf8mb4');

function portal_student_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$student_id = access_student_id();
$stmt = $conn->prepare('SELECT s.*,c.class_name,sec.section_name FROM students s JOIN classes c ON c.id=s.class_id JOIN sections sec ON sec.id=s.section_id WHERE s.id=? AND s.status=1 LIMIT 1');
if (!$stmt) {
    error_log('Student portal lookup failed: ' . $conn->error);
    http_response_code(500);
    exit('Unable to load your student profile.');
}
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$student) {
    http_response_code(403);
    exit('Your student record is inactive or unavailable. Contact the school administrator.');
}

$attendance = array();
$homework = array();
$announcements = array();
if (access_feature_allowed($conn, 'attendance')) {
    $stmt = $conn->prepare('SELECT attendance_date,status,remarks FROM student_attendance WHERE student_id=? ORDER BY attendance_date DESC LIMIT 60');
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendance[] = $row;
    }
    $stmt->close();
}
if (access_feature_allowed($conn, 'homework')) {
    $stmt = $conn->prepare('SELECT h.title,h.description,h.due_date,s.subject_name FROM portal_homework h LEFT JOIN subjects s ON s.id=h.subject_id WHERE h.class_id=? AND (h.section_id IS NULL OR h.section_id=?) AND h.status=1 ORDER BY h.due_date DESC,h.created_at DESC LIMIT 30');
    $stmt->bind_param('ii', $student['class_id'], $student['section_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $homework[] = $row;
    }
    $stmt->close();
}
if (access_feature_allowed($conn, 'announcements')) {
    $stmt = $conn->prepare("SELECT title,body,publish_date FROM portal_announcements WHERE status=1 AND audience IN ('All','Students') AND publish_date<=CURDATE() AND (class_id IS NULL OR class_id=?) AND (section_id IS NULL OR section_id=?) ORDER BY publish_date DESC LIMIT 20");
    $stmt->bind_param('ii', $student['class_id'], $student['section_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
    $stmt->close();
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-user-graduate"></i> Student Portal</h1></div></section>
    <section class="content"><div class="container-fluid">
        <div class="alert alert-primary">
            <h4><?= portal_student_e($student['student_name']); ?></h4>
            <p class="mb-0"><?= portal_student_e($student['class_name'] . ' - Section ' . $student['section_name']); ?> | Admission No: <?= portal_student_e($student['admission_no']); ?></p>
        </div>
        <div class="row">
            <?php if (access_feature_allowed($conn, 'attendance')): ?>
            <div class="col-lg-6" id="attendance"><div class="card"><div class="card-header bg-info text-white">My Attendance</div><div class="card-body p-0 table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Status</th><th>Remarks</th></tr></thead><tbody><?php foreach ($attendance as $row): ?><tr><td><?= portal_student_e($row['attendance_date']); ?></td><td><?= portal_student_e($row['status']); ?></td><td><?= portal_student_e($row['remarks']); ?></td></tr><?php endforeach; ?><?php if (!$attendance): ?><tr><td colspan="3" class="text-muted text-center p-3">No attendance records.</td></tr><?php endif; ?></tbody></table></div></div></div>
            <?php endif; ?>
            <?php if (access_feature_allowed($conn, 'announcements')): ?>
            <div class="col-lg-6"><div class="card"><div class="card-header bg-warning">Announcements & Notices</div><div class="card-body"><?php foreach ($announcements as $row): ?><article class="border-bottom pb-2 mb-2"><strong><?= portal_student_e($row['title']); ?></strong><small class="d-block text-muted"><?= portal_student_e($row['publish_date']); ?></small><p><?= nl2br(portal_student_e($row['body'])); ?></p></article><?php endforeach; ?><?php if (!$announcements): ?><p class="text-muted mb-0">No announcements.</p><?php endif; ?></div></div></div>
            <?php endif; ?>
        </div>
        <?php if (access_feature_allowed($conn, 'homework')): ?>
        <div class="card"><div class="card-header bg-primary text-white">Homework</div><div class="card-body"><?php foreach ($homework as $row): ?><article class="border-bottom pb-2 mb-2"><strong><?= portal_student_e($row['title']); ?></strong><?php if ($row['subject_name']): ?><span class="badge badge-info"><?= portal_student_e($row['subject_name']); ?></span><?php endif; ?><?php if ($row['due_date']): ?><span class="badge badge-warning"><?= portal_student_e($row['due_date']); ?></span><?php endif; ?><p class="mb-0"><?= nl2br(portal_student_e($row['description'])); ?></p></article><?php endforeach; ?><?php if (!$homework): ?><p class="text-muted mb-0">No homework posted.</p><?php endif; ?></div></div>
        <?php endif; ?>
        <div class="row">
            <?php foreach (array('syllabus' => array('Syllabus', 'Syllabus'), 'date_sheets' => array('Date Sheets', 'Date Sheet')) as $feature => $link): ?>
                <?php if (access_feature_allowed($conn, $feature)): ?><div class="col-md-6"><a class="btn btn-outline-primary btn-block mb-3" href="../resources/index.php?type=<?= rawurlencode($link[1]); ?>"><i class="fas fa-folder-open mr-1"></i>Open <?= portal_student_e($link[0]); ?></a></div><?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
