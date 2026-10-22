<?php
function parent_link_sibling_students($conn, $existing_student_id, $new_student_id)
{
    $existing_student_id = (int)$existing_student_id;
    $new_student_id = (int)$new_student_id;
    if ($existing_student_id <= 0 || $new_student_id <= 0 || $existing_student_id === $new_student_id) {
        return 'Choose a valid existing sibling.';
    }

    $lock = $conn->prepare('SELECT id FROM students WHERE id=? AND status=1 FOR UPDATE');
    if (!$lock) {
        return 'Unable to verify the selected sibling: ' . $conn->error;
    }
    $lock->bind_param('i', $existing_student_id);
    if (!$lock->execute() || !$lock->get_result()->fetch_assoc()) {
        $lock->close();
        return 'The selected sibling is no longer active.';
    }
    $lock->close();

    $parents = $conn->prepare('SELECT parent_id FROM parent_students WHERE student_id=? ORDER BY parent_id');
    if (!$parents) {
        return 'Unable to find the sibling parent account: ' . $conn->error;
    }
    $parents->bind_param('i', $existing_student_id);
    if (!$parents->execute()) {
        $message = $parents->error;
        $parents->close();
        return 'Unable to find the sibling parent account: ' . $message;
    }
    $parent_rows = $parents->get_result();
    $parent_ids = array();
    while ($row = $parent_rows->fetch_assoc()) {
        $parent_ids[] = (int)$row['parent_id'];
    }
    $parents->close();
    if (count($parent_ids) > 1) {
        return 'The selected sibling is linked to more than one parent account. Ask an administrator to resolve the parent links first.';
    }

    if ($parent_ids) {
        $parent_id = $parent_ids[0];
    } else {
        $guardian = $conn->prepare('SELECT father_name,father_mobile,guardian_name,guardian_mobile FROM students WHERE id=? LIMIT 1');
        if (!$guardian) {
            return 'Unable to read the selected sibling parent details: ' . $conn->error;
        }
        $guardian->bind_param('i', $existing_student_id);
        if (!$guardian->execute()) {
            $message = $guardian->error;
            $guardian->close();
            return 'Unable to read the selected sibling parent details: ' . $message;
        }
        $details = $guardian->get_result()->fetch_assoc();
        $guardian->close();
        $parent_name = trim((string)($details['father_name'] ?? ''));
        $mobile = trim((string)($details['father_mobile'] ?? ''));
        if ($parent_name === '') {
            $parent_name = trim((string)($details['guardian_name'] ?? ''));
        }
        if ($mobile === '') {
            $mobile = trim((string)($details['guardian_mobile'] ?? ''));
        }
        if ($parent_name === '') {
            $parent_name = 'Parent account for student ' . $existing_student_id;
        }
        $create = $conn->prepare('INSERT INTO parent_accounts (parent_name,mobile) VALUES (?,NULLIF(?,\'\'))');
        if (!$create) {
            return 'Unable to create a shared parent account: ' . $conn->error;
        }
        $create->bind_param('ss', $parent_name, $mobile);
        if (!$create->execute()) {
            $message = $create->error;
            $create->close();
            return 'Unable to create a shared parent account: ' . $message;
        }
        $parent_id = (int)$create->insert_id;
        $create->close();
        $link_existing = $conn->prepare('INSERT INTO parent_students (parent_id,student_id) VALUES (?,?)');
        if (!$link_existing) {
            return 'Unable to link the sibling to its parent account: ' . $conn->error;
        }
        $link_existing->bind_param('ii', $parent_id, $existing_student_id);
        if (!$link_existing->execute()) {
            $message = $link_existing->error;
            $link_existing->close();
            return 'Unable to link the sibling to its parent account: ' . $message;
        }
        $link_existing->close();
    }

    $link_new = $conn->prepare('INSERT INTO parent_students (parent_id,student_id) VALUES (?,?)');
    if (!$link_new) {
        return 'Unable to link the new student to the shared parent account: ' . $conn->error;
    }
    $link_new->bind_param('ii', $parent_id, $new_student_id);
    if (!$link_new->execute()) {
        $message = $link_new->error;
        $link_new->close();
        return 'Unable to link the new student to the shared parent account: ' . $message;
    }
    $link_new->close();
    return '';
}
