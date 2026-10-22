<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
access_require_roles(array('Admin'));

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit('Database connection failed.');
}
$conn->set_charset('utf8mb4');

if (empty($_SESSION['reports_csrf'])) {
    $_SESSION['reports_csrf'] = bin2hex(random_bytes(32));
}

$reports = array(
    'students' => array('Students', 'Student directory with class, section, contact and active status.', array('students', 'classes', 'sections'), "SELECT s.admission_no AS reference, s.student_name AS name, c.class_name AS class_name, sec.section_name AS section_name, s.admission_date AS event_date, s.status AS status, s.gender AS category, s.father_mobile AS value, s.city AS details FROM students s LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN sections sec ON sec.id=s.section_id ORDER BY c.class_order, sec.section_name, s.student_name"),
    'classes' => array('Classes and sections', 'Configured classes and sections.', array('classes', 'sections'), "SELECT CAST(c.id AS CHAR) AS reference, c.class_name AS name, c.class_name AS class_name, '' AS section_name, '' AS event_date, c.status AS status, 'Class' AS category, c.class_order AS value, '' AS details FROM classes c UNION ALL SELECT CAST(sec.id AS CHAR), sec.section_name, '', sec.section_name, '', sec.status, 'Section', sec.id, '' FROM sections sec ORDER BY category, name"),
    'employees' => array('Employees', 'Employee directory and contact details.', array('teachers'), "SELECT t.employee_code AS reference, t.full_name AS name, '' AS class_name, '' AS section_name, t.joining_date AS event_date, t.status AS status, t.designation AS category, t.mobile AS value, t.email AS details FROM teachers t ORDER BY t.full_name"),
    'teacher-subjects' => array('Employee subject assignments', 'Subjects assigned to each employee.', array('teacher_subjects', 'teachers'), "SELECT CAST(ts.id AS CHAR) AS reference, t.full_name AS name, '' AS class_name, '' AS section_name, ts.created_at AS event_date, ts.status AS status, 'Subject ID' AS category, ts.subject_id AS value, CONCAT('Employee ID: ', ts.teacher_id) AS details FROM teacher_subjects ts LEFT JOIN teachers t ON t.id=ts.teacher_id ORDER BY t.full_name, ts.subject_id"),
    'attendance' => array('Student attendance', 'Daily student attendance history, including remarks.', array('student_attendance', 'students', 'classes', 'sections'), "SELECT s.admission_no AS reference, s.student_name AS name, c.class_name AS class_name, sec.section_name AS section_name, a.attendance_date AS event_date, a.status AS status, 'Attendance' AS category, '' AS value, a.remarks AS details FROM student_attendance a INNER JOIN students s ON s.id=a.student_id LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN sections sec ON sec.id=s.section_id ORDER BY a.attendance_date DESC, c.class_order, s.student_name"),
    'assignments' => array('Teacher class assignments', 'Assigned teachers, classes, sections and subject IDs.', array('teacher_class_assignments', 'teachers', 'classes', 'sections'), "SELECT CAST(a.id AS CHAR) AS reference, t.full_name AS name, c.class_name AS class_name, sec.section_name AS section_name, a.created_at AS event_date, a.status AS status, 'Subject ID' AS category, a.subject_id AS value, CONCAT('Teacher ID: ', a.teacher_id) AS details FROM teacher_class_assignments a LEFT JOIN teachers t ON t.id=a.teacher_id LEFT JOIN classes c ON c.id=a.class_id LEFT JOIN sections sec ON sec.id=a.section_id ORDER BY c.class_order, sec.section_name, t.full_name"),
    'class-responsibilities' => array('Class teacher responsibilities', 'Teacher responsibility by class and section.', array('teacher_class_responsibilities', 'teachers', 'classes', 'sections'), "SELECT CAST(r.id AS CHAR) AS reference, t.full_name AS name, c.class_name AS class_name, sec.section_name AS section_name, r.created_at AS event_date, r.status AS status, 'Class teacher' AS category, '' AS value, CONCAT('Teacher ID: ', r.teacher_id) AS details FROM teacher_class_responsibilities r LEFT JOIN teachers t ON t.id=r.teacher_id LEFT JOIN classes c ON c.id=r.class_id LEFT JOIN sections sec ON sec.id=r.section_id ORDER BY c.class_order, sec.section_name, t.full_name"),
    'exam-types' => array('Examination types', 'Exam type names, maximum marks and active status.', array('exam_types'), "SELECT CAST(e.id AS CHAR) AS reference, e.exam_type_name AS name, '' AS class_name, '' AS section_name, e.created_at AS event_date, e.status AS status, 'Maximum marks' AS category, e.max_marks AS value, '' AS details FROM exam_types e ORDER BY e.exam_type_name"),
    'marks' => array('Marks and report-card results', 'Marks currently stored in the examination system.', array('marks', 'students', 'classes', 'sections', 'subjects'), "SELECT s.admission_no AS reference, s.student_name AS name, c.class_name AS class_name, sec.section_name AS section_name, '' AS event_date, '' AS status, CONCAT('Subject ID ', m.subject_id, ', Exam ID ', m.exam_id) AS category, CONCAT(m.obtained_marks, ' / ', m.max_marks) AS value, COALESCE(m.grade, '') AS details FROM marks m INNER JOIN students s ON s.id=m.student_id LEFT JOIN classes c ON c.id=m.class_id LEFT JOIN sections sec ON sec.id=m.section_id ORDER BY c.class_order, s.student_name"),
    'fee-structure' => array('Fee structure', 'Class fee types and configured amounts.', array('fee_structures', 'fee_types', 'classes'), "SELECT CAST(fs.id AS CHAR) AS reference, ft.fee_name AS name, c.class_name AS class_name, '' AS section_name, fs.created_at AS event_date, fs.status AS status, fs.frequency AS category, fs.amount AS value, '' AS details FROM fee_structures fs LEFT JOIN fee_types ft ON ft.id=fs.fee_type_id LEFT JOIN classes c ON c.id=fs.class_id ORDER BY c.class_order, ft.fee_name"),
    'fee-types' => array('Fee types', 'Fee names, descriptions and active status.', array('fee_types'), "SELECT CAST(f.id AS CHAR) AS reference, f.fee_name AS name, '' AS class_name, '' AS section_name, f.created_at AS event_date, f.status AS status, 'Fee type' AS category, '' AS value, f.description AS details FROM fee_types f ORDER BY f.fee_name"),
    'fee-schedule' => array('Fee schedule', 'Configured fee due dates and amounts.', array('fee_schedules', 'fee_structures', 'fee_types', 'classes'), "SELECT CAST(fsc.id AS CHAR) AS reference, ft.fee_name AS name, c.class_name AS class_name, '' AS section_name, fsc.due_date AS event_date, fsc.status AS status, 'Scheduled amount' AS category, fsc.amount AS value, '' AS details FROM fee_schedules fsc LEFT JOIN fee_structures fs ON fs.id=fsc.fee_structure_id LEFT JOIN fee_types ft ON ft.id=fs.fee_type_id LEFT JOIN classes c ON c.id=fs.class_id ORDER BY fsc.due_date DESC"),
    'student-concessions' => array('Student fee concessions', 'Student-specific fee concessions and validity dates.', array('student_fee_concessions', 'students', 'classes', 'sections'), "SELECT s.admission_no AS reference, s.student_name AS name, c.class_name AS class_name, sec.section_name AS section_name, fc.valid_from AS event_date, fc.status AS status, fc.concession_name AS category, fc.amount AS value, CONCAT('Valid to: ', COALESCE(fc.valid_to, 'Not specified'), '; fee type ID: ', COALESCE(fc.fee_type_id, 'All')) AS details FROM student_fee_concessions fc LEFT JOIN students s ON s.id=fc.student_id LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN sections sec ON sec.id=s.section_id ORDER BY s.student_name, fc.valid_from"),
    'fee-payments' => array('Fee payments', 'Recorded student payments and receipt details.', array('fee_payments', 'students', 'classes', 'sections', 'fee_types'), "SELECT COALESCE(fp.receipt_no, CAST(fp.id AS CHAR)) AS reference, s.student_name AS name, c.class_name AS class_name, sec.section_name AS section_name, fp.payment_date AS event_date, 'Paid' AS status, COALESCE(ft.fee_name, 'Fee') AS category, fp.amount AS value, fp.remarks AS details FROM fee_payments fp LEFT JOIN students s ON s.id=fp.student_id LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN sections sec ON sec.id=s.section_id LEFT JOIN fee_types ft ON ft.id=fp.fee_type_id ORDER BY fp.payment_date DESC"),
    'homework' => array('Homework', 'Homework assigned by class and section.', array('portal_homework', 'classes', 'sections'), "SELECT CAST(h.id AS CHAR) AS reference, h.title AS name, c.class_name AS class_name, sec.section_name AS section_name, h.due_date AS event_date, h.status AS status, 'Homework' AS category, h.subject_id AS value, h.description AS details FROM portal_homework h LEFT JOIN classes c ON c.id=h.class_id LEFT JOIN sections sec ON sec.id=h.section_id ORDER BY h.due_date DESC"),
    'announcements' => array('Announcements and notices', 'Published notices, audience, and publication date.', array('portal_announcements'), "SELECT CAST(a.id AS CHAR) AS reference, a.title AS name, '' AS class_name, '' AS section_name, a.publish_date AS event_date, a.status AS status, a.audience AS category, '' AS value, a.body AS details FROM portal_announcements a ORDER BY a.publish_date DESC"),
    'holidays' => array('Holidays', 'Configured holidays and active status.', array('portal_holidays'), "SELECT CAST(h.id AS CHAR) AS reference, h.holiday_name AS name, '' AS class_name, '' AS section_name, h.holiday_date AS event_date, h.status AS status, 'Holiday' AS category, '' AS value, '' AS details FROM portal_holidays h ORDER BY h.holiday_date DESC"),
    'promotions' => array('Student promotions', 'Promotion history with source and destination class/section.', array('student_promotions', 'students', 'classes', 'sections'), "SELECT s.admission_no AS reference, s.student_name AS name, fc.class_name AS class_name, fs.section_name AS section_name, p.promoted_at AS event_date, 'Promoted' AS status, CONCAT('To ', tc.class_name, ' / ', ts.section_name) AS category, '' AS value, '' AS details FROM student_promotions p LEFT JOIN students s ON s.id=p.student_id LEFT JOIN classes fc ON fc.id=p.from_class_id LEFT JOIN sections fs ON fs.id=p.from_section_id LEFT JOIN classes tc ON tc.id=p.to_class_id LEFT JOIN sections ts ON ts.id=p.to_section_id ORDER BY p.promoted_at DESC"),
    'student-profile-details' => array('Student profile details', 'Student contacts and address for authorized school administrators.', array('students', 'classes', 'sections'), "SELECT s.admission_no AS reference, s.student_name AS name, c.class_name AS class_name, sec.section_name AS section_name, s.admission_date AS event_date, s.status AS status, s.gender AS category, CONCAT('Father: ', COALESCE(s.father_name, ''), ' / ', COALESCE(s.father_mobile, ''), '; Mother: ', COALESCE(s.mother_name, ''), ' / ', COALESCE(s.mother_mobile, '')) AS value, CONCAT_WS(', ', s.address, s.city, s.district, s.state, s.pincode) AS details FROM students s LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN sections sec ON sec.id=s.section_id ORDER BY c.class_order, sec.section_name, s.student_name"),
    'student-categories' => array('Student categories', 'Configured student categories and active status.', array('categories'), "SELECT CAST(c.id AS CHAR) AS reference, c.category_name AS name, '' AS class_name, '' AS section_name, c.created_at AS event_date, c.status AS status, 'Category' AS category, '' AS value, '' AS details FROM categories c ORDER BY c.category_name"),
    'student-houses' => array('Student houses', 'House membership and configured house names.', array('students', 'houses', 'classes', 'sections'), "SELECT s.admission_no AS reference, s.student_name AS name, c.class_name AS class_name, sec.section_name AS section_name, '' AS event_date, s.status AS status, 'House' AS category, COALESCE(h.house_name, s.house, '') AS value, '' AS details FROM students s LEFT JOIN houses h ON h.id=s.house_id LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN sections sec ON sec.id=s.section_id ORDER BY c.class_order, h.house_name, s.student_name"),
    'student-games' => array('Student games', 'Game selections recorded on student profiles.', array('students', 'classes', 'sections'), "SELECT s.admission_no AS reference, s.student_name AS name, c.class_name AS class_name, sec.section_name AS section_name, '' AS event_date, s.status AS status, 'Game' AS category, COALESCE(s.game, '') AS value, '' AS details FROM students s LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN sections sec ON sec.id=s.section_id ORDER BY c.class_order, s.student_name"),
    'religion-options' => array('Religion options', 'Configured religion options; student records do not currently store a religion field.', array('religions'), "SELECT CAST(r.id AS CHAR) AS reference, r.religion_name AS name, '' AS class_name, '' AS section_name, r.created_at AS event_date, r.status AS status, 'Religion option' AS category, '' AS value, '' AS details FROM religions r ORDER BY r.religion_name"),
    'transport-routes' => array('Transport routes', 'Configured transport route names and status.', array('transport_routes'), "SELECT CAST(t.id AS CHAR) AS reference, t.route_name AS name, '' AS class_name, '' AS section_name, t.created_at AS event_date, t.status AS status, 'Transport route' AS category, '' AS value, '' AS details FROM transport_routes t ORDER BY t.route_name"),
    'hpc-observations' => array('Holistic progress observations', 'Holistic progress card values recorded for each student and term.', array('hpc_observations', 'students', 'classes', 'sections'), "SELECT s.admission_no AS reference, s.student_name AS name, c.class_name AS class_name, sec.section_name AS section_name, o.updated_at AS event_date, 'Recorded' AS status, CONCAT(o.category, ' / ', o.observation) AS category, CONCAT('Term 1: ', COALESCE(o.term1_value, ''), '; Term 2: ', COALESCE(o.term2_value, '')) AS value, CONCAT('Session ID: ', o.session_id) AS details FROM hpc_observations o LEFT JOIN students s ON s.id=o.student_id LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN sections sec ON sec.id=s.section_id ORDER BY c.class_order, s.student_name, o.category, o.observation"),
    'report-card-subjects' => array('Report-card subject selections', 'Class-specific subjects selected for report cards.', array('report_card_class_subjects', 'classes'), "SELECT CAST(rs.id AS CHAR) AS reference, CONCAT('Subject ID ', rs.subject_id) AS name, c.class_name AS class_name, '' AS section_name, '' AS event_date, 'Configured' AS status, 'Display order' AS category, rs.display_order AS value, '' AS details FROM report_card_class_subjects rs LEFT JOIN classes c ON c.id=rs.class_id ORDER BY c.class_order, rs.display_order"),
    'report-card-groups' => array('Report-card group subject selections', 'Subjects configured by report-card class group.', array('report_card_group_subjects'), "SELECT CAST(g.id AS CHAR) AS reference, CONCAT('Subject ID ', g.subject_id) AS name, g.group_key AS class_name, '' AS section_name, '' AS event_date, g.status AS status, 'Display order' AS category, g.display_order AS value, '' AS details FROM report_card_group_subjects g ORDER BY g.group_key, g.display_order"),
    'report-card-class-settings' => array('Report-card class configuration', 'Classes with individually configured report-card subject lists.', array('report_card_class_settings', 'classes'), "SELECT CAST(c.id AS CHAR) AS reference, c.class_name AS name, c.class_name AS class_name, '' AS section_name, rs.updated_at AS event_date, 'Configured' AS status, 'Class report-card setup' AS category, '' AS value, '' AS details FROM report_card_class_settings rs LEFT JOIN classes c ON c.id=rs.class_id ORDER BY c.class_order"),
    'accounts' => array('User accounts', 'Usernames, account roles and account status; passwords are never included.', array('users', 'roles'), "SELECT CAST(u.id AS CHAR) AS reference, u.full_name AS name, '' AS class_name, '' AS section_name, u.created_at AS event_date, u.status AS status, u.account_type AS category, u.username AS value, CONCAT('Role: ', COALESCE(r.role_name, CONCAT('ID ', u.role_id)), '; password is private and cannot be exported.') AS details FROM users u LEFT JOIN roles r ON r.id=u.role_id ORDER BY u.full_name"),
    'student-login-ids' => array('Student IDs and login IDs', 'Student admission IDs, portal login IDs, class and account status. Passwords are deliberately not exported.', array('students', 'classes', 'sections', 'users'), "SELECT s.admission_no AS reference,s.student_name AS name,c.class_name AS class_name,sec.section_name AS section_name,s.admission_date AS event_date,s.status AS status,'Student login ID' AS category,COALESCE(u.username,s.admission_no) AS value,CONCAT('Student database ID: ',s.id,'; password is private and must be reset by an administrator.') AS details FROM students s LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN sections sec ON sec.id=s.section_id LEFT JOIN users u ON u.account_type='Student' AND u.linked_id=s.id ORDER BY c.class_order,sec.section_name,s.student_name"),
    'employee-login-ids' => array('Employee IDs and login IDs', 'Employee codes, portal login IDs, contact and account status. Passwords are deliberately not exported.', array('teachers', 'users'), "SELECT COALESCE(NULLIF(t.employee_code,''),CAST(t.id AS CHAR)) AS reference,t.full_name AS name,'' AS class_name,'' AS section_name,t.joining_date AS event_date,t.status AS status,'Employee login ID' AS category,COALESCE(u.username,t.mobile,'') AS value,CONCAT('Employee database ID: ',t.id,'; password is private and must be reset by an administrator.') AS details FROM teachers t LEFT JOIN users u ON u.account_type='Teacher' AND u.linked_id=t.id ORDER BY t.full_name"),
    'school' => array('School settings', 'Current global school settings.', array('school_settings'), "SELECT s.setting_key AS reference, s.setting_key AS name, '' AS class_name, '' AS section_name, s.updated_at AS event_date, 'Configured' AS status, 'Setting' AS category, '' AS value, s.setting_value AS details FROM school_settings s ORDER BY s.setting_key")
);

function reports_table_exists($conn, $table)
{
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Unable to check report availability: ' . $conn->error);
    }
    $stmt->bind_param('s', $table);
    if (!$stmt->execute()) {
        throw new RuntimeException('Unable to check report availability: ' . $stmt->error);
    }
    $exists = $stmt->get_result()->num_rows === 1;
    $stmt->close();
    return $exists;
}

function reports_csv_safe_value($value)
{
    $value = (string)$value;
    if (preg_match('/^[\t\r\n ]*[=+\-@]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'export') {
    $token = (string)($_POST['csrf_token'] ?? '');
    $selected = array_values(array_unique(array_intersect(array_keys($reports), (array)($_POST['reports'] ?? array()))));
    if (!hash_equals((string)$_SESSION['reports_csrf'], $token)) {
        http_response_code(400);
        exit('This report form expired. Refresh the page and try again.');
    }
    if (!$selected) {
        http_response_code(400);
        exit('Select at least one report.');
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="school-erp-reports-' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    if ($output === false) {
        http_response_code(500);
        exit('Unable to create the report file.');
    }
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, array('Report', 'Reference', 'Name', 'Class', 'Section', 'Date', 'Status', 'Category', 'Value', 'Details'));
    foreach ($selected as $report_id) {
        list($label, , $required_tables, $sql) = $reports[$report_id];
        try {
            $missing = array();
            foreach ($required_tables as $table) {
                if (!reports_table_exists($conn, $table)) {
                    $missing[] = $table;
                }
            }
            if ($missing) {
                fputcsv($output, array($label, '', '', '', '', '', 'Unavailable', '', '', 'Required database tables are missing: ' . implode(', ', $missing)));
                continue;
            }
            $result = $conn->query($sql);
            if (!$result) {
                throw new RuntimeException($conn->error);
            }
            if ($result->num_rows === 0) {
                fputcsv($output, array($label, '', '', '', '', '', 'No records', '', '', 'No data is currently available.'));
                continue;
            }
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, array_map('reports_csv_safe_value', array(
                    $label,
                    $row['reference'] ?? '',
                    $row['name'] ?? '',
                    $row['class_name'] ?? '',
                    $row['section_name'] ?? '',
                    $row['event_date'] ?? '',
                    $row['status'] ?? '',
                    $row['category'] ?? '',
                    $row['value'] ?? '',
                    $row['details'] ?? ''
                )));
            }
            $result->free();
        } catch (Throwable $exception) {
            error_log('School ERP report "' . $report_id . '" failed: ' . $exception->getMessage());
            fputcsv($output, array($label, '', '', '', '', '', 'Error', '', '', 'This report could not be generated. Check the application error log.'));
        }
    }
    fclose($output);
    exit;
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid"><h1><i class="fas fa-chart-bar mr-2"></i>Reports</h1></div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Choose reports to download</h3></div>
                <div class="card-body">
                    <p class="text-muted">Select one or more reports to download a combined CSV file that opens in Excel. Sensitive passwords are never included. Reports unavailable in the current database will be identified in the file.</p>
                    <form method="post">
                        <input type="hidden" name="action" value="export">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['reports_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="row">
                            <?php foreach ($reports as $id => $report): ?>
                                <div class="col-lg-6">
                                    <div class="custom-control custom-checkbox mb-3">
                                        <input class="custom-control-input" type="checkbox" id="report-<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>" name="reports[]" value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                                        <label class="custom-control-label" for="report-<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                                            <strong><?= htmlspecialchars($report[0], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($report[1], ENT_QUOTES, 'UTF-8'); ?></small>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-outline-secondary mr-2" data-select-all-reports>Select all</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-download mr-1"></i>Download selected reports</button>
                    </form>
                    <div class="alert alert-warning mt-3 mb-0">
                        This installation currently has one shared school database. Multi-school onboarding and activation are not enabled because school records are not yet separated by school; enabling it without tenant isolation could expose one school's data to another.
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<?php include '../../includes/footer.php'; ?>
