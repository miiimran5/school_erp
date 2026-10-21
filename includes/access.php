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
        return false;
    }
    $stmt->bind_param('iiiiiiiiii', $teacher_id, $class_id, $section_id, $section_id, $class_id, $section_id, $section_id, $class_id, $section_id, $section_id);
    $stmt->execute();
    $allowed = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $allowed;
}

function access_student_id()
{
    return access_is_student() ? (int)($_SESSION['linked_id'] ?? 0) : 0;
}
