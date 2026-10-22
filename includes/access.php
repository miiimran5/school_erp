<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function access_role()
{
    return (string)($_SESSION['account_type'] ?? ((int)($_SESSION['role_id'] ?? 0) === 7 ? 'Student' : ((int)($_SESSION['role_id'] ?? 0) === 5 ? 'Teacher' : 'Admin')));
}

function access_is_admin()
{
    return in_array((int)($_SESSION['role_id'] ?? 0), array(1, 2, 3), true) && access_role() === 'Admin';
}

function access_is_super_admin()
{
    return (int)($_SESSION['role_id'] ?? 0) === 1;
}

function access_is_teacher()
{
    return access_role() === 'Teacher';
}

function access_is_student()
{
    return access_role() === 'Student';
}

function access_is_parent()
{
    return access_role() === 'Parent';
}

function access_feature_catalog()
{
    return array(
        'announcements' => array('label' => 'Announcements', 'roles' => array('Teacher', 'Student')),
        'homework' => array('label' => 'Homework', 'roles' => array('Teacher', 'Student')),
        'study_materials' => array('label' => 'Study Materials', 'roles' => array('Teacher')),
        'syllabus' => array('label' => 'Syllabus', 'roles' => array('Teacher', 'Student')),
        'timetable' => array('label' => 'Time Table', 'roles' => array('Teacher')),
        'date_sheets' => array('label' => 'Date Sheets', 'roles' => array('Teacher', 'Student')),
        'attendance' => array('label' => 'Attendance', 'roles' => array('Teacher', 'Student')),
        'student_records' => array('label' => 'Assigned Student Records', 'roles' => array('Teacher')),
        'exams' => array('label' => 'Examinations and Marks Entry', 'roles' => array('Teacher')),
        'reports' => array('label' => 'Result Reports and Report Cards', 'roles' => array('Teacher')),
        'hpc_reports' => array('label' => 'HPC Report Cards', 'roles' => array('Teacher'))
    );
}

function access_feature_default_enabled($role, $feature)
{
    $defaults = array(
        'Teacher' => array('announcements', 'homework', 'study_materials', 'syllabus', 'timetable', 'date_sheets', 'attendance', 'student_records', 'exams', 'reports', 'hpc_reports'),
        'Student' => array('announcements', 'homework', 'date_sheets', 'syllabus', 'attendance')
    );
    return isset($defaults[$role]) && in_array($feature, $defaults[$role], true);
}

function access_feature_allowed($conn, $feature)
{
    $role = access_role();
    if ($role === 'Admin') {
        return true;
    }
    if ($role === 'Parent') {
        $role = 'Student';
    }
    if (!in_array($role, array('Teacher', 'Student'), true)) {
        return false;
    }

    $catalog = access_feature_catalog();
    if (!isset($catalog[$feature]) || !in_array($role, $catalog[$feature]['roles'], true)) {
        return false;
    }

    $stmt = $conn->prepare('SELECT enabled FROM role_feature_permissions WHERE account_type=? AND feature_key=? LIMIT 1');
    if (!$stmt) {
        error_log('Feature access lookup failed: ' . $conn->error);
        return false;
    }
    $stmt->bind_param('ss', $role, $feature);
    if (!$stmt->execute()) {
        error_log('Feature access lookup failed: ' . $stmt->error);
        $stmt->close();
        return false;
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['enabled'] === 1 : access_feature_default_enabled($role, $feature);
}

function access_require_feature($conn, $feature)
{
    if (!access_feature_allowed($conn, $feature)) {
        http_response_code(403);
        exit('This feature is not enabled for your account. Contact your school administrator.');
    }
}

function access_current_academic_session($conn)
{
    $selected = (int)($_SESSION['erp_academic_session_id'] ?? 0);
    if ($selected > 0) {
        $check = $conn->prepare('SELECT id FROM academic_sessions WHERE id=? AND status=1 LIMIT 1');
        if ($check) {
            $check->bind_param('i', $selected);
            if ($check->execute() && $check->get_result()->num_rows === 1) {
                $check->close();
                return $selected;
            }
            $check->close();
        } else {
            error_log('Academic session lookup failed: ' . $conn->error);
            return 0;
        }
    }
    $stmt = $conn->prepare('SELECT id FROM academic_sessions WHERE status=1 ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        error_log('Academic session lookup failed: ' . $conn->error);
        return 0;
    }
    if (!$stmt->execute()) {
        error_log('Academic session lookup failed: ' . $stmt->error);
        $stmt->close();
        return 0;
    }
    $session = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $_SESSION['erp_academic_session_id'] = (int)($session['id'] ?? 0);
    return (int)($_SESSION['erp_academic_session_id'] ?? 0);
}

function access_require_login($relative_login = '../../login.php')
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . $relative_login);
        exit;
    }
}

function access_require_roles($roles, $relative_login = '../../login.php')
{
    access_require_login($relative_login);
    if (!in_array(access_role(), (array)$roles, true)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

function access_teacher_can_class($conn, $class_id, $section_id = 0)
{
    $teacher_id = (int)($_SESSION['linked_id'] ?? 0);
    if ($teacher_id <= 0) {
        return false;
    }
    $stmt = $conn->prepare('SELECT 1 FROM teachers t WHERE t.id=? AND ((t.class_incharge_id=? AND (?=0 OR t.class_incharge_section_id IS NULL OR t.class_incharge_section_id=?)) OR EXISTS (SELECT 1 FROM teacher_class_assignments a WHERE a.teacher_id=t.id AND a.class_id=? AND a.status=1 AND (?=0 OR a.section_id IS NULL OR a.section_id=?)) OR EXISTS (SELECT 1 FROM teacher_class_responsibilities r WHERE r.teacher_id=t.id AND r.class_id=? AND r.status=1 AND (?=0 OR r.section_id=?))) LIMIT 1');
    if (!$stmt) {
        error_log('Teacher class access lookup failed: ' . $conn->error);
        return false;
    }
    $stmt->bind_param('iiiiiiiiii', $teacher_id, $class_id, $section_id, $section_id, $class_id, $section_id, $section_id, $class_id, $section_id, $section_id);
    if (!$stmt->execute()) {
        error_log('Teacher class access lookup failed: ' . $stmt->error);
        $stmt->close();
        return false;
    }
    $allowed = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $allowed;
}

function access_teacher_can_subject($conn, $class_id, $section_id, $subject_id)
{
    if (!access_is_teacher()) {
        return access_is_admin();
    }
    $teacher_id = (int)($_SESSION['linked_id'] ?? 0);
    $stmt = $conn->prepare('SELECT 1 FROM teacher_class_assignments WHERE teacher_id=? AND class_id=? AND subject_id=? AND status=1 AND (section_id IS NULL OR ?=0 OR section_id=?) LIMIT 1');
    if (!$stmt) {
        error_log('Teacher subject access lookup failed: ' . $conn->error);
        return false;
    }
    $stmt->bind_param('iiiii', $teacher_id, $class_id, $subject_id, $section_id, $section_id);
    if (!$stmt->execute()) {
        error_log('Teacher subject access lookup failed: ' . $stmt->error);
        $stmt->close();
        return false;
    }
    $allowed = $stmt->get_result()->num_rows === 1;
    $stmt->close();
    return $allowed;
}

function access_student_id()
{
    return access_is_student() ? (int)($_SESSION['linked_id'] ?? 0) : 0;
}

function access_parent_student_id($conn, $requested_student_id = 0)
{
    if (!access_is_parent()) {
        return 0;
    }
    $parent_id = (int)($_SESSION['linked_id'] ?? 0);
    if ($parent_id <= 0) {
        return 0;
    }
    if ((int)$requested_student_id > 0) {
        $stmt = $conn->prepare('SELECT student_id FROM parent_students WHERE parent_id=? AND student_id=? LIMIT 1');
        if (!$stmt) {
            error_log('Parent child access lookup failed: ' . $conn->error);
            return 0;
        }
        $stmt->bind_param('ii', $parent_id, $requested_student_id);
    } else {
        $stmt = $conn->prepare('SELECT student_id FROM parent_students WHERE parent_id=? ORDER BY student_id LIMIT 1');
        if (!$stmt) {
            error_log('Parent child access lookup failed: ' . $conn->error);
            return 0;
        }
        $stmt->bind_param('i', $parent_id);
    }
    if (!$stmt->execute()) {
        error_log('Parent child access lookup failed: ' . $stmt->error);
        $stmt->close();
        return 0;
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['student_id'] ?? 0);
}
