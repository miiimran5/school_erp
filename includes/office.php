<?php
function office_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function office_table_exists($conn, $table)
{
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    if (!$stmt->execute()) {
        error_log('Student Office query failed: ' . $stmt->error);
        $stmt->close();
        http_response_code(500);
        exit('Unable to load student records. Check the database error log.');
    }
    $exists = $stmt->get_result()->num_rows === 1;
    $stmt->close();
    return $exists;
}

function office_column_exists($conn, $table, $column)
{
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    if (!$stmt->execute()) {
        error_log('Student Office column lookup failed: ' . $stmt->error);
        $stmt->close();
        return false;
    }
    $exists = $stmt->get_result()->num_rows === 1;
    $stmt->close();
    return $exists;
}

function office_ensure_schema($conn)
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $required_columns = array('registration_no', 'previous_school', 'parking', 'practical');
    foreach ($required_columns as $column) {
        if (!office_column_exists($conn, 'students', $column)) {
            http_response_code(503);
            exit('Student Office database update is required. Ask the administrator to import database/migrations/021_student_office.sql.');
        }
    }
    foreach (array('admission_inquiries', 'calendar_events', 'school_leaving_certificates') as $table) {
        if (!office_table_exists($conn, $table)) {
            http_response_code(503);
            exit('Student Office database update is required. Ask the administrator to import database/migrations/021_student_office.sql.');
        }
    }
    $ready = true;
}

function office_process_student_photo($upload)
{
    if (!extension_loaded('gd')) {
        throw new RuntimeException('Photo resizing is unavailable. Enable the GD extension in the XAMPP PHP configuration, then restart Apache.');
    }
    if (!isset($upload['error'], $upload['tmp_name'], $upload['size']) || $upload['error'] !== UPLOAD_ERR_OK || (int)$upload['size'] <= 0 || (int)$upload['size'] > 5242880 || !is_uploaded_file($upload['tmp_name'])) {
        throw new RuntimeException('Choose a valid photo no larger than 5 MB.');
    }
    $image_info = @getimagesize($upload['tmp_name']);
    if ($image_info === false || !in_array($image_info['mime'], array('image/jpeg', 'image/png', 'image/webp'), true)) {
        throw new RuntimeException('Choose a valid JPG, PNG, or WEBP photo.');
    }
    $width = (int)$image_info[0];
    $height = (int)$image_info[1];
    if ($width < 1 || $height < 1 || $width > 8000 || $height > 8000 || $width * $height > 15000000) {
        throw new RuntimeException('The photo dimensions are too large. Please choose a smaller image.');
    }
    $loaders = array('image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp');
    $loader = $loaders[$image_info['mime']];
    if (!function_exists($loader)) {
        throw new RuntimeException('This PHP installation cannot process this photo format. Enable GD with JPEG, PNG, and WEBP support.');
    }
    $source = @$loader($upload['tmp_name']);
    if (!$source) {
        throw new RuntimeException('Unable to read the selected photo.');
    }
    $directory = __DIR__ . '/../uploads/students';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        imagedestroy($source);
        throw new RuntimeException('Unable to create the student photo folder.');
    }
    $long_edge = max($width, $height);
    $scale = $long_edge > 1600 ? 1600 / $long_edge : 1;
    $target_width = max(1, (int)round($width * $scale));
    $target_height = max(1, (int)round($height * $scale));
    $target = imagecreatetruecolor($target_width, $target_height);
    $white = imagecolorallocate($target, 255, 255, 255);
    imagefill($target, 0, 0, $white);
    imagecopyresampled($target, $source, 0, 0, 0, 0, $target_width, $target_height, $width, $height);
    imagedestroy($source);

    $filename = 'student_' . bin2hex(random_bytes(16)) . '.jpg';
    $path = $directory . '/' . $filename;
    $saved = false;
    for ($attempt = 0; $attempt < 8; $attempt++) {
        for ($quality = 85; $quality >= 35; $quality -= 10) {
            if (imagejpeg($target, $path, $quality)) {
                clearstatcache(true, $path);
            }
            if (is_file($path) && filesize($path) <= 307200) {
                $saved = true;
                break 2;
            }
        }
        if (max($target_width, $target_height) <= 160) {
            break;
        }
        $target_width = max(1, (int)floor($target_width * 0.8));
        $target_height = max(1, (int)floor($target_height * 0.8));
        $smaller = imagecreatetruecolor($target_width, $target_height);
        $white = imagecolorallocate($smaller, 255, 255, 255);
        imagefill($smaller, 0, 0, $white);
        imagecopyresampled($smaller, $target, 0, 0, 0, 0, $target_width, $target_height, imagesx($target), imagesy($target));
        imagedestroy($target);
        $target = $smaller;
    }
    imagedestroy($target);
    if (!$saved) {
        if (is_file($path)) {
            unlink($path);
        }
        throw new RuntimeException('Unable to compress this photo below 300 KB. Please choose another image.');
    }
    return $filename;
}

function office_delete_student_photo($filename)
{
    $filename = basename((string)$filename);
    if ($filename === '') {
        return;
    }
    $directory = realpath(__DIR__ . '/../uploads/students');
    $path = $directory !== false ? $directory . DIRECTORY_SEPARATOR . $filename : '';
    if ($path !== '' && is_file($path) && realpath($path) !== false && dirname(realpath($path)) === $directory) {
        unlink($path);
    }
}

function office_gender_words($gender)
{
    $is_female = strcasecmp((string)$gender, 'Female') === 0;
    return array(
        'child' => $is_female ? 'Kumari' : 'Master',
        'son_daughter' => $is_female ? 'daughter' : 'son',
        'pronoun' => $is_female ? 'she' : 'he',
        'pronoun_cap' => $is_female ? 'She' : 'He',
        'pronoun_is' => $is_female ? 'she is' : 'he is',
        'possessive' => $is_female ? 'her' : 'his',
        'possessive_cap' => $is_female ? 'Her' : 'His'
    );
}

function office_certificate_text($template, $replacements)
{
    return strtr((string)$template, $replacements);
}

function office_setting($conn, $key, $fallback = '')
{
    $stmt = $conn->prepare('SELECT setting_value FROM school_settings WHERE setting_key=? LIMIT 1');
    if (!$stmt) {
        return $fallback;
    }
    $stmt->bind_param('s', $key);
    if (!$stmt->execute()) {
        error_log('Student Office settings query failed: ' . $stmt->error);
        $stmt->close();
        http_response_code(500);
        exit('Unable to load school settings. Check the database error log.');
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $value = trim((string)($row['setting_value'] ?? ''));
    return $value !== '' ? $value : $fallback;
}

function office_student_certificate_map($conn, $student, $extra = array())
{
    $words = office_gender_words($student['gender'] ?? '');
    $section = trim((string)($student['section_name'] ?? ''));
    $map = array(
        '{school_name}' => school_setting('school_name', APP_NAME),
        '{student_name}' => (string)($student['student_name'] ?? ''),
        '{father_name}' => (string)($student['father_name'] ?? ''),
        '{class}' => (string)($student['class_name'] ?? ''),
        '{section}' => $section !== '' ? ' / ' . $section : '',
        '{admission_no}' => (string)($student['admission_no'] ?? ($student['registration_no'] ?? '')),
        '{registration_no}' => (string)($student['registration_no'] ?? ''),
        '{dob}' => !empty($student['dob']) ? date('d-m-Y', strtotime($student['dob'])) : '',
        '{session}' => school_setting('academic_session', date('Y') . '-' . (date('Y') + 1)),
        '{date}' => date('d-m-Y'),
        '{gender_child}' => $words['son_daughter'],
        '{gender_title}' => $words['child'],
        '{gender_pronoun}' => $words['pronoun'],
        '{gender_pronoun_cap}' => $words['pronoun_cap'],
        '{gender_pronoun_is}' => $words['pronoun_is'],
        '{gender_possessive}' => $words['possessive'],
        '{gender_possessive_cap}' => $words['possessive_cap']
    );
    foreach ($extra as $key => $value) {
        $map[$key] = $value;
    }
    return $map;
}

function office_download_spreadsheet($filename, $headers, $rows)
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    if ($out === false) {
        http_response_code(500);
        exit('Unable to create the requested spreadsheet.');
    }
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        $line = array();
        foreach ($headers as $key) {
            $value = (string)($row[$key] ?? '');
            if (preg_match('/^[\t\r\n ]*[=+\-@]/', $value)) {
                $value = "'" . $value;
            }
            $line[] = $value;
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

function office_print_pdf_page($title, $headers, $rows)
{
    $safe_title = office_e($title);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . $safe_title . '</title>';
    echo '<style>body{font-family:Segoe UI,Arial,sans-serif;padding:24px;color:#111}h1{font-size:20px}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #cbd5e1;padding:6px 8px;text-align:left}th{background:#f1f5f9}@page{size:A4 landscape;margin:12mm}@media print{.no-print{display:none}body{padding:0}thead{display:table-header-group}tr{break-inside:avoid}}</style></head><body>';
    echo '<p class="no-print"><button onclick="window.print()">Save / Print PDF</button></p>';
    echo '<h1>' . $safe_title . '</h1><table><thead><tr>';
    foreach ($headers as $header) {
        echo '<th>' . office_e($header) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($headers as $key) {
            echo '<td>' . office_e(isset($row[$key]) ? $row[$key] : '') . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table><script>window.addEventListener("load",function(){setTimeout(function(){window.print()},400);});</script></body></html>';
    exit;
}

function office_student_filters()
{
    $name = trim((string)($_GET['name'] ?? ''));
    if (strlen($name) > 100) {
        $name = substr($name, 0, 100);
    }
    if (strlen($name) > 0 && strlen($name) < 3) {
        $name = '';
    }
    $status = (string)($_GET['status'] ?? 'active');
    if (!in_array($status, array('active', 'inactive', 'all'), true)) {
        $status = 'active';
    }
    if (isset($_GET['all_students'])) {
        return array(
            'class_id' => 0,
            'section_id' => 0,
            'name' => '',
            'admission_no' => '',
            'registration_no' => '',
            'receipt_no' => '',
            'route' => '',
            'filter' => '',
            'status' => 'all'
        );
    }
    return array(
        'class_id' => (int)($_GET['class_id'] ?? 0),
        'section_id' => (int)($_GET['section_id'] ?? 0),
        'name' => $name,
        'admission_no' => trim((string)($_GET['admission_no'] ?? '')),
        'registration_no' => trim((string)($_GET['registration_no'] ?? '')),
        'receipt_no' => trim((string)($_GET['receipt_no'] ?? '')),
        'route' => trim((string)($_GET['route'] ?? '')),
        'filter' => trim((string)($_GET['filter'] ?? '')),
        'status' => $status
    );
}

function office_fetch_students($conn, $filters, $teacher_only = false)
{
    $sql = "SELECT s.id, s.admission_no, s.registration_no, s.admission_date, s.roll_no, s.student_name, s.gender, s.dob,
            s.father_name, s.father_mobile, s.mother_name, s.address, s.city, s.status, s.class_id, s.section_id,
            s.transport, s.parking, s.practical, s.concession, s.photo,
            c.class_name, sec.section_name
        FROM students s
        LEFT JOIN classes c ON c.id = s.class_id
        LEFT JOIN sections sec ON sec.id = s.section_id";
    $joins = '';
    $where = array('1=1');
    $types = '';
    $params = array();

    if ($filters['receipt_no'] !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM fee_payments fp WHERE fp.student_id = s.id AND fp.receipt_no = ?)';
        $types .= 's';
        $params[] = $filters['receipt_no'];
    }
    $sql .= $joins;

    if ($filters['class_id'] > 0) {
        $where[] = 's.class_id = ?';
        $types .= 'i';
        $params[] = $filters['class_id'];
    }
    if ($filters['section_id'] > 0) {
        $where[] = 's.section_id = ?';
        $types .= 'i';
        $params[] = $filters['section_id'];
    }
    if ($filters['name'] !== '') {
        $where[] = 's.student_name LIKE ?';
        $types .= 's';
        $params[] = '%' . $filters['name'] . '%';
    }
    if ($filters['admission_no'] !== '') {
        $where[] = 's.admission_no = ?';
        $types .= 's';
        $params[] = $filters['admission_no'];
    }
    if ($filters['registration_no'] !== '') {
        $where[] = 's.registration_no = ?';
        $types .= 's';
        $params[] = $filters['registration_no'];
    }
    if ($filters['route'] !== '') {
        $where[] = 's.transport = ?';
        $types .= 's';
        $params[] = $filters['route'];
    }
    if ($filters['status'] === 'active') {
        $where[] = 's.status = 1';
    } elseif ($filters['status'] === 'inactive') {
        $where[] = 's.status = 0';
    }

    switch ($filters['filter']) {
        case 'with_transport':
            $where[] = "s.transport IS NOT NULL AND s.transport <> ''";
            break;
        case 'without_transport':
            $where[] = "(s.transport IS NULL OR s.transport = '')";
            break;
        case 'with_parking':
            $where[] = 's.parking = 1';
            break;
        case 'without_parking':
            $where[] = 's.parking = 0';
            break;
        case 'with_practical':
            $where[] = 's.practical = 1';
            break;
        case 'without_practical':
            $where[] = 's.practical = 0';
            break;
        case 'with_concession':
            $where[] = 's.concession > 0';
            break;
        case 'without_concession':
            $where[] = 's.concession <= 0';
            break;
        case 'boys':
            $where[] = "s.gender = 'Male'";
            break;
        case 'girls':
            $where[] = "s.gender = 'Female'";
            break;
    }

    $sql .= ' WHERE ' . implode(' AND ', $where) . ' ORDER BY c.class_order, sec.section_name, s.student_name';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('Student Office query prepare failed: ' . $conn->error);
        http_response_code(500);
        exit('Unable to load student records. Check the database error log.');
    }
    if ($types !== '') {
        $bind = array($types);
        foreach ($params as $i => $value) {
            $bind[] = &$params[$i];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind);
    }
    if (!$stmt->execute()) {
        error_log('Student Office query failed: ' . $stmt->error);
        $stmt->close();
        http_response_code(500);
        exit('Unable to load student records. Check the database error log.');
    }
    $result = $stmt->get_result();
    $students = array();
    while ($row = $result->fetch_assoc()) {
        if ($teacher_only && !access_teacher_can_class($conn, (int)$row['class_id'], (int)$row['section_id'])) {
            continue;
        }
        $students[] = $row;
    }
    $stmt->close();
    return $students;
}

function office_credential_rows($conn, $type)
{
    $rows = array();
    if ($type === 'student' || $type === 'all') {
        $sql = "SELECT s.id, s.student_name AS name, s.admission_no, COALESCE(u.username, s.admission_no) AS login_id,
                c.class_name, sec.section_name, s.status
            FROM students s
            LEFT JOIN users u ON u.account_type='Student' AND u.linked_id=s.id
            LEFT JOIN classes c ON c.id=s.class_id
            LEFT JOIN sections sec ON sec.id=s.section_id
            ORDER BY s.student_name";
        $result = $conn->query($sql);
        if (!$result) {
            error_log('Student login ID export failed: ' . $conn->error);
            http_response_code(500);
            exit('Unable to prepare the student login ID export.');
        }
        while ($result && ($row = $result->fetch_assoc())) {
            $rows[] = array(
                'Type' => 'Student',
                'Name' => $row['name'],
                'Class / Designation' => trim((string)$row['class_name'] . ' ' . (string)$row['section_name']),
                'School ID' => $row['admission_no'],
                'Record ID' => $row['id'],
                'Login ID' => $row['login_id'],
                'Status' => (int)$row['status'] === 1 ? 'Active' : 'Inactive'
            );
        }
    }
    if ($type === 'employee' || $type === 'all') {
        $sql = "SELECT t.id, t.employee_code, t.full_name AS name, COALESCE(u.username, t.mobile, t.employee_code) AS login_id,
                t.designation, t.department, t.status
            FROM teachers t
            LEFT JOIN users u ON u.account_type='Teacher' AND u.linked_id=t.id
            ORDER BY t.full_name";
        $result = $conn->query($sql);
        if (!$result) {
            error_log('Employee login ID export failed: ' . $conn->error);
            http_response_code(500);
            exit('Unable to prepare the employee login ID export.');
        }
        while ($result && ($row = $result->fetch_assoc())) {
            $rows[] = array(
                'Type' => 'Employee',
                'Name' => $row['name'],
                'Class / Designation' => trim((string)$row['designation'] . ' ' . (string)$row['department']),
                'School ID' => $row['employee_code'],
                'Record ID' => $row['id'],
                'Login ID' => $row['login_id'],
                'Status' => (int)$row['status'] === 1 ? 'Active' : 'Inactive'
            );
        }
    }
    return $rows;
}

function office_defaulter_rows($conn)
{
    $sql = "SELECT s.id, s.student_name, s.admission_no, c.class_name, sec.section_name, s.father_mobile,
            COALESCE(billed.total,0) AS billed, COALESCE(paid.total,0) AS paid, COALESCE(conc.total,0) AS concession
        FROM students s
        LEFT JOIN classes c ON c.id=s.class_id
        LEFT JOIN sections sec ON sec.id=s.section_id
        LEFT JOIN (
            SELECT st.id AS student_id, SUM(f.amount) AS total
            FROM students st
            JOIN fee_schedules f ON f.status=1
            JOIN fee_structures fs ON fs.id=f.fee_structure_id AND fs.class_id=st.class_id AND fs.status=1
            GROUP BY st.id
        ) billed ON billed.student_id=s.id
        LEFT JOIN (SELECT student_id, SUM(amount) AS total FROM fee_payments GROUP BY student_id) paid ON paid.student_id=s.id
        LEFT JOIN (SELECT student_id, SUM(amount) AS total FROM student_fee_concessions WHERE status=1 GROUP BY student_id) conc ON conc.student_id=s.id
        WHERE s.status=1
          AND (COALESCE(billed.total,0) - COALESCE(conc.total,0) - COALESCE(paid.total,0)) > 0
        ORDER BY c.class_name, s.student_name";
    $rows = array();
    $result = $conn->query($sql);
    if (!$result) {
        error_log('Fee defaulters export failed: ' . $conn->error);
        http_response_code(500);
        exit('Unable to prepare the fee defaulters export.');
    }
    while ($result && ($row = $result->fetch_assoc())) {
        $balance = (float)$row['billed'] - (float)$row['concession'] - (float)$row['paid'];
        $rows[] = array(
            'Admission No' => $row['admission_no'],
            'Student Name' => $row['student_name'],
            'Class' => $row['class_name'],
            'Section' => $row['section_name'],
            'Father Mobile' => $row['father_mobile'],
            'Billed' => number_format((float)$row['billed'], 2, '.', ''),
            'Concession' => number_format((float)$row['concession'], 2, '.', ''),
            'Paid' => number_format((float)$row['paid'], 2, '.', ''),
            'Balance' => number_format($balance, 2, '.', '')
        );
    }
    return $rows;
}
