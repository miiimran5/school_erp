<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';

access_require_roles(array('Parent'));
$conn->set_charset('utf8mb4');

function parent_portal_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$parent_id = (int)($_SESSION['linked_id'] ?? 0);
$children = array();
$stmt = $conn->prepare('SELECT s.id,s.admission_no,s.student_name,s.class_id,s.section_id,c.class_name,sec.section_name FROM parent_students ps JOIN students s ON s.id=ps.student_id JOIN classes c ON c.id=s.class_id JOIN sections sec ON sec.id=s.section_id WHERE ps.parent_id=? AND s.status=1 ORDER BY s.student_name');
if (!$stmt) {
    error_log('Parent portal child list failed: ' . $conn->error);
    http_response_code(500);
    exit('Unable to load the children linked to this parent account.');
}
$stmt->bind_param('i', $parent_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $children[] = $row;
}
$stmt->close();
if (!$children) {
    http_response_code(403);
    exit('No active students are linked to this parent account. Contact the school administrator.');
}

$selected_id = access_parent_student_id($conn, (int)($_GET['student_id'] ?? 0));
$student = null;
foreach ($children as $child) {
    if ((int)$child['id'] === $selected_id) {
        $student = $child;
        break;
    }
}
if (!$student) {
    http_response_code(403);
    exit('The selected student is not linked to this parent account.');
}

$attendance = array();
$announcements = array();
$homework = array();
if (access_feature_allowed($conn, 'attendance')) {
    $stmt = $conn->prepare('SELECT attendance_date,status,remarks FROM student_attendance WHERE student_id=? ORDER BY attendance_date DESC LIMIT 60');
    $stmt->bind_param('i', $selected_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendance[] = $row;
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
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-users"></i> Family Portal</h1><p class="text-muted">Select a child to view that student's own school information.</p></div></section>
    <section class="content"><div class="container-fluid">
        <div class="card"><div class="card-body">
            <form method="get" class="form-inline"><label for="child-select" class="mr-2">View student</label><select id="child-select" name="student_id" class="form-control mr-2" onchange="this.form.submit()"><?php foreach ($children as $child): ?><option value="<?= (int)$child['id']; ?>" <?= (int)$child['id'] === $selected_id ? 'selected' : ''; ?>><?= parent_portal_e($child['student_name'] . ' (' . $child['admission_no'] . ') - ' . $child['class_name'] . '/' . $child['section_name']); ?></option><?php endforeach; ?></select><button class="btn btn-primary">Switch child</button></form>
        </div></div>
        <div class="alert alert-primary"><h4><?= parent_portal_e($student['student_name']); ?></h4><p class="mb-0"><?= parent_portal_e($student['class_name'] . ' - Section ' . $student['section_name']); ?> | Admission No: <?= parent_portal_e($student['admission_no']); ?></p></div>
        <div class="row">
            <?php if (access_feature_allowed($conn, 'attendance')): ?><div class="col-lg-6" id="attendance"><div class="card"><div class="card-header bg-info text-white">Attendance</div><div class="card-body p-0 table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Status</th><th>Remarks</th></tr></thead><tbody><?php foreach ($attendance as $row): ?><tr><td><?= parent_portal_e($row['attendance_date']); ?></td><td><?= parent_portal_e($row['status']); ?></td><td><?= parent_portal_e($row['remarks']); ?></td></tr><?php endforeach; ?><?php if (!$attendance): ?><tr><td colspan="3" class="text-center text-muted p-3">No attendance records.</td></tr><?php endif; ?></tbody></table></div></div></div><?php endif; ?>
            <?php if (access_feature_allowed($conn, 'announcements')): ?><div class="col-lg-6"><div class="card"><div class="card-header bg-warning">Announcements</div><div class="card-body"><?php foreach ($announcements as $row): ?><article class="border-bottom pb-2 mb-2"><strong><?= parent_portal_e($row['title']); ?></strong><small class="d-block text-muted"><?= parent_portal_e($row['publish_date']); ?></small><p><?= nl2br(parent_portal_e($row['body'])); ?></p></article><?php endforeach; ?><?php if (!$announcements): ?><p class="text-muted mb-0">No announcements.</p><?php endif; ?></div></div></div><?php endif; ?>
        </div>
        <?php if (access_feature_allowed($conn, 'homework')): ?><div class="card"><div class="card-header bg-primary text-white">Homework</div><div class="card-body"><?php foreach ($homework as $row): ?><article class="border-bottom pb-2 mb-2"><strong><?= parent_portal_e($row['title']); ?></strong><?php if ($row['subject_name']): ?><span class="badge badge-info"><?= parent_portal_e($row['subject_name']); ?></span><?php endif; ?><p><?= nl2br(parent_portal_e($row['description'])); ?></p><?php if ($row['due_date']): ?><small class="text-muted">Due <?= parent_portal_e($row['due_date']); ?></small><?php endif; ?></article><?php endforeach; ?><?php if (!$homework): ?><p class="text-muted mb-0">No homework posted.</p><?php endif; ?></div></div><?php endif; ?>
        <div class="row"><?php foreach (array('syllabus' => array('Syllabus', 'Syllabus'), 'date_sheets' => array('Date Sheets', 'Date Sheet')) as $feature => $link): ?><?php if (access_feature_allowed($conn, $feature)): ?><div class="col-md-6"><a class="btn btn-outline-primary btn-block mb-3" href="../resources/index.php?type=<?= rawurlencode($link[1]); ?>&student_id=<?= $selected_id; ?>"><i class="fas fa-folder-open mr-1"></i><?= parent_portal_e($link[0]); ?></a></div><?php endif; ?><?php endforeach; ?></div>
        <?php if (access_feature_allowed($conn, 'announcements') || access_feature_allowed($conn, 'homework')): ?><a href="announcements.php?student_id=<?= $selected_id; ?>" class="btn btn-outline-info"><i class="fas fa-bullhorn mr-1"></i>Open announcements and homework</a><?php endif; ?>
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
