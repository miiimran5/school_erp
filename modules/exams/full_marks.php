<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';

access_require_roles(array('Admin', 'Teacher'));
$conn->set_charset('utf8mb4');
access_require_feature($conn, 'exams');

function full_marks_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function full_marks_pdf_text($value)
{
    $value = (string)$value;
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        if ($converted !== false) {
            $value = $converted;
        }
    }
    $value = preg_replace('/[^\x20-\xFF]/', '?', $value);
    return str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $value);
}

function full_marks_pdf_line(&$content, $x1, $y1, $x2, $y2)
{
    $content .= sprintf("%.2f %.2f m %.2f %.2f l S\n", $x1, $y1, $x2, $y2);
}

function full_marks_pdf_label(&$content, $text, $x, $top, $size, $page_height)
{
    $baseline = $page_height - $top - $size;
    $content .= 'BT /F1 ' . number_format($size, 2, '.', '') . ' Tf 1 0 0 1 '
        . number_format($x, 2, '.', '') . ' ' . number_format($baseline, 2, '.', '')
        . ' Tm (' . full_marks_pdf_text($text) . ") Tj ET\n";
}

function full_marks_create_pdf($report)
{
    $page_width = 1191;
    $page_height = 842;
    $margin = 28;
    $student_pages = array_chunk(array_values($report['students']), 30);
    $subject_pages = array_chunk(array_values($report['subjects']), 8);
    $page_contents = array();
    foreach ($student_pages as $student_page_index => $student_page) {
        foreach ($subject_pages as $subject_page_index => $subject_page) {
            $content = "0.25 w\n";
            full_marks_pdf_label($content, 'EduCare ERP - FULL MARKS REPORT', $margin, 23, 18, $page_height);
            full_marks_pdf_label($content, 'Class: ' . $report['class_name'] . '    Section: ' . $report['section_name'] . '    Examination: ' . $report['exam_name'], $margin, 50, 10, $page_height);
            full_marks_pdf_label($content, 'Students ' . ($student_page_index + 1) . '/' . count($student_pages) . '    Subject group ' . ($subject_page_index + 1) . '/' . count($subject_pages), $margin, 68, 8, $page_height);

            $columns = array(
                array('label' => 'Admission No.', 'width' => 92),
                array('label' => 'Roll No.', 'width' => 48),
                array('label' => 'Student Name', 'width' => 190)
            );
            $subject_width = ($page_width - 2 * $margin - 392) / max(1, count($subject_page));
            foreach ($subject_page as $subject) {
                $columns[] = array('label' => $subject['subject_name'], 'width' => $subject_width);
            }
            $columns[] = array('label' => 'Total', 'width' => 62);
            $table_left = $margin;
            $table_top = 96;
            $header_height = 38;
            $row_height = 20;
            $table_width = array_sum(array_column($columns, 'width'));
            $table_bottom_top = $table_top + $header_height + count($student_page) * $row_height;
            $content .= "0.90 g\n";
            $content .= sprintf("%.2f %.2f %.2f %.2f re f\n", $table_left, $page_height - $table_top - $header_height, $table_width, $header_height);
            $content .= "0 g\n";
            $x = $table_left;
            $column_positions = array($x);
            foreach ($columns as $column) {
                full_marks_pdf_label($content, $column['label'], $x + 3, $table_top + 5, 7, $page_height);
                $x += $column['width'];
                $column_positions[] = $x;
            }
            for ($row_index = 0; $row_index <= count($student_page); $row_index++) {
                $top = $table_top + $header_height + $row_index * $row_height;
                full_marks_pdf_line($content, $table_left, $page_height - $top, $table_left + $table_width, $page_height - $top);
            }
            foreach ($column_positions as $position) {
                full_marks_pdf_line($content, $position, $page_height - $table_top, $position, $page_height - $table_bottom_top);
            }
            foreach ($student_page as $row_index => $student) {
                $top = $table_top + $header_height + $row_index * $row_height + 5;
                $x = $table_left;
                full_marks_pdf_label($content, $student['admission_no'], $x + 3, $top, 7, $page_height);
                $x += $columns[0]['width'];
                full_marks_pdf_label($content, $student['roll_no'], $x + 3, $top, 7, $page_height);
                $x += $columns[1]['width'];
                full_marks_pdf_label($content, $student['student_name'], $x + 3, $top, 7, $page_height);
                $x += $columns[2]['width'];
                foreach ($subject_page as $subject) {
                    $mark = $student['marks'][$subject['subject_name']] ?? null;
                    $value = $mark ? number_format($mark['obtained'], 2) . '/' . number_format($mark['maximum'], 2) : '-';
                    if ($mark && $mark['grade'] !== '') {
                        $value .= ' ' . $mark['grade'];
                    }
                    if ((int)$student['additional_subject_id'] === (int)$subject['id']) {
                        $value .= '*';
                    }
                    full_marks_pdf_label($content, $value, $x + 3, $top, 7, $page_height);
                    $x += $subject_width;
                }
                full_marks_pdf_label($content, number_format($student['total_obtained'], 2) . '/' . number_format($student['total_maximum'], 2), $x + 3, $top, 7, $page_height);
            }
            $page_contents[] = $content;
        }
    }

    $objects = array();
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $page_ids = array();
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    foreach ($page_contents as $index => $content) {
        $page_id = 4 + $index * 2;
        $stream_id = $page_id + 1;
        $page_ids[] = $page_id . ' 0 R';
        $objects[$page_id] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $page_width . ' ' . $page_height . '] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $stream_id . ' 0 R >>';
        $objects[$stream_id] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream";
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $page_ids) . '] /Count ' . count($page_ids) . ' >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = array(0);
    foreach ($objects as $id => $object) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref_offset = strlen($pdf);
    $max_object = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($max_object + 1) . "\n0000000000 65535 f \n";
    for ($id = 1; $id <= $max_object; $id++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
    }
    $pdf .= "trailer\n<< /Size " . ($max_object + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref_offset . "\n%%EOF";
    return $pdf;
}

$error = '';
$classes = array();
$class_result = $conn->query('SELECT id,class_name FROM classes WHERE status=1 ORDER BY class_order,id');
while ($class_result && ($row = $class_result->fetch_assoc())) {
    if (!access_is_teacher() || access_teacher_can_class($conn, (int)$row['id'], 0)) {
        $classes[] = $row;
    }
}
$sections = array();
$section_result = $conn->query('SELECT id,section_name FROM sections WHERE status=1 ORDER BY section_name,id');
while ($section_result && ($row = $section_result->fetch_assoc())) {
    $sections[] = $row;
}
$current_session_id = access_current_academic_session($conn);
$exams = array();
$exam_stmt = $conn->prepare('SELECT id,exam_name FROM exams WHERE status=1 AND (?=0 OR session_id=?) ORDER BY start_date DESC,id DESC');
$exam_stmt->bind_param('ii', $current_session_id, $current_session_id);
$exam_stmt->execute();
$exam_result = $exam_stmt->get_result();
while ($row = $exam_result->fetch_assoc()) {
    $exams[] = $row;
}
$exam_stmt->close();

$class_id = filter_var($_GET['class_id'] ?? 0, FILTER_VALIDATE_INT);
$section_id = filter_var($_GET['section_id'] ?? 0, FILTER_VALIDATE_INT);
$exam_id = filter_var($_GET['exam_id'] ?? 0, FILTER_VALIDATE_INT);
if (isset($_GET['generate'])) {
    if (!$class_id || !$section_id || !$exam_id) {
        $error = 'Select a class, section, and examination.';
    } elseif (access_is_teacher() && !access_teacher_can_class($conn, $class_id, $section_id)) {
        http_response_code(403);
        exit('You are not assigned to this class and section.');
    } else {
        $metadata_stmt = $conn->prepare(
            'SELECT c.class_name,sec.section_name,e.exam_name
             FROM exams e
             JOIN classes c ON c.id=?
             JOIN sections sec ON sec.id=?
             WHERE e.id=? AND e.status=1 AND (?=0 OR e.session_id=?) LIMIT 1'
        );
        if (!$metadata_stmt) {
            $error = 'Unable to prepare the report: ' . $conn->error;
        } else {
            $metadata_stmt->bind_param('iiiii', $class_id, $section_id, $exam_id, $current_session_id, $current_session_id);
            $metadata_stmt->execute();
            $metadata = $metadata_stmt->get_result()->fetch_assoc();
            $metadata_stmt->close();
            if (!$metadata) {
                $error = 'The selected class, section, or examination is invalid for this academic session.';
            } else {
                $subject_stmt = $conn->prepare(
                    'SELECT s.id,s.subject_name,cs.role,COALESCE(cs.is_optional,0) AS is_optional
                     FROM class_subjects cs JOIN subjects s ON s.id=cs.subject_id
                     WHERE cs.class_id=? AND cs.status=1 AND s.status=1
                     ORDER BY cs.sort_order,s.subject_name'
                );
                $subjects = array();
                $subject_by_id = array();
                if (!$subject_stmt) {
                    $error = 'Unable to load class subjects: ' . $conn->error;
                } else {
                    $subject_stmt->bind_param('i', $class_id);
                    $subject_stmt->execute();
                    $subject_result = $subject_stmt->get_result();
                    while ($subject = $subject_result->fetch_assoc()) {
                        $subject['id'] = (int)$subject['id'];
                        $subjects[] = $subject;
                        $subject_by_id[(int)$subject['id']] = $subject;
                    }
                    $subject_stmt->close();
                }
                if ($error === '' && !$subjects) {
                    $error = 'No active subjects are assigned to this class.';
                }

                $report = array(
                    'class_name' => $metadata['class_name'],
                    'section_name' => $metadata['section_name'],
                    'exam_name' => $metadata['exam_name'],
                    'subjects' => $subjects,
                    'students' => array()
                );
                if ($error === '') {
                    $student_stmt = $conn->prepare(
                        'SELECT s.id,s.admission_no,s.student_name,s.roll_no,s.additional_subject_id,
                                m.subject_id,m.max_marks,m.obtained_marks,m.grade
                         FROM students s
                         LEFT JOIN marks m ON m.student_id=s.id AND m.class_id=s.class_id AND m.section_id=s.section_id AND m.exam_id=?
                         WHERE s.class_id=? AND s.section_id=? AND s.status=1
                         ORDER BY s.roll_no,s.student_name,m.subject_id'
                    );
                    if (!$student_stmt) {
                        $error = 'Unable to load student marks: ' . $conn->error;
                    } else {
                        $student_stmt->bind_param('iii', $exam_id, $class_id, $section_id);
                        if (!$student_stmt->execute()) {
                            $error = 'Unable to load student marks: ' . $student_stmt->error;
                        } else {
                            $student_result = $student_stmt->get_result();
                            while ($row = $student_result->fetch_assoc()) {
                                $student_key = (int)$row['id'];
                                if (!isset($report['students'][$student_key])) {
                                    $report['students'][$student_key] = array(
                                        'admission_no' => $row['admission_no'],
                                        'student_name' => $row['student_name'],
                                        'roll_no' => $row['roll_no'],
                                        'additional_subject_id' => (int)$row['additional_subject_id'],
                                        'marks' => array(),
                                        'total_obtained' => 0,
                                        'total_maximum' => 0
                                    );
                                }
                                if ($row['subject_id'] !== null) {
                                    $subject_id = (int)$row['subject_id'];
                                    if (isset($subject_by_id[$subject_id])) {
                                        $subject = $subject_by_id[$subject_id];
                                        $report['students'][$student_key]['marks'][$subject['subject_name']] = array(
                                            'obtained' => (float)$row['obtained_marks'],
                                            'maximum' => (float)$row['max_marks'],
                                            'grade' => (string)$row['grade']
                                        );
                                        if ($subject['role'] === 'Main'
                                            && (int)$subject['is_optional'] === 0
                                            && $student_key > 0
                                            && (int)$row['additional_subject_id'] !== $subject_id) {
                                            $report['students'][$student_key]['total_obtained'] += (float)$row['obtained_marks'];
                                            $report['students'][$student_key]['total_maximum'] += (float)$row['max_marks'];
                                        }
                                    }
                                }
                            }
                        }
                        $student_stmt->close();
                    }
                }
                if ($error === '' && !$report['students']) {
                    $error = 'No active students were found in the selected class and section.';
                }
                if ($error === '' && count($report['students']) > 2000) {
                    $error = 'This class has more than 2,000 active students. Contact support for a larger report.';
                }
                if ($error === '') {
                    $report['students'] = array_values($report['students']);
                    $pdf = full_marks_create_pdf($report);
                    $filename = preg_replace('/[^A-Za-z0-9_-]+/', '-', $metadata['class_name'] . '-' . $metadata['exam_name']);
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="full-marks-' . trim($filename, '-') . '.pdf"');
                    header('Content-Length: ' . strlen($pdf));
                    header('X-Content-Type-Options: nosniff');
                    echo $pdf;
                    exit;
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
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-file-pdf"></i> Full Marks A3 PDF</h1></div></section>
    <section class="content"><div class="container-fluid">
        <?php if ($error): ?><div class="alert alert-danger"><?= full_marks_e($error); ?></div><?php endif; ?>
        <div class="card"><div class="card-header bg-primary text-white">Download A3 landscape report from saved marks</div><div class="card-body">
            <p>The report is created directly from the saved marks-entry records for the selected examination. It includes active students, all assigned subjects, grades and totals; additional subjects are shown but excluded from totals.</p>
            <form method="get" class="form-row">
                <div class="form-group col-md-4"><label for="class-id">Class</label><select id="class-id" name="class_id" class="form-control" required><option value="">Select class</option><?php foreach ($classes as $class): ?><option value="<?= (int)$class['id']; ?>" <?= (int)$class_id === (int)$class['id'] ? 'selected' : ''; ?>><?= full_marks_e($class['class_name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group col-md-3"><label for="section-id">Section</label><select id="section-id" name="section_id" class="form-control" required><option value="">Select section</option><?php foreach ($sections as $section): ?><option value="<?= (int)$section['id']; ?>" <?= (int)$section_id === (int)$section['id'] ? 'selected' : ''; ?>><?= full_marks_e($section['section_name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group col-md-3"><label for="exam-id">Examination</label><select id="exam-id" name="exam_id" class="form-control" required><option value="">Select examination</option><?php foreach ($exams as $exam): ?><option value="<?= (int)$exam['id']; ?>" <?= (int)$exam_id === (int)$exam['id'] ? 'selected' : ''; ?>><?= full_marks_e($exam['exam_name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group col-md-2 d-flex align-items-end"><button class="btn btn-success btn-block" name="generate" value="1"><i class="fas fa-download mr-1"></i>Download PDF</button></div>
            </form>
            <small class="form-text text-muted">Print layout: A3 landscape. Marks not yet saved appear blank.</small>
        </div></div>
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
