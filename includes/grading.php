<?php
function grading_grade_for_percentage($conn, $class_id, $percentage)
{
    static $scales = array();
    $class_id = (int)$class_id;
    $percentage = (float)$percentage;
    if ($class_id > 0) {
        if (!array_key_exists($class_id, $scales)) {
            $scales[$class_id] = array();
            $stmt = $conn->prepare('SELECT grade,minimum_percent,maximum_percent FROM grading_scale_bands WHERE class_id=? ORDER BY minimum_percent DESC');
            if ($stmt) {
                $stmt->bind_param('i', $class_id);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $scales[$class_id][] = $row;
                    }
                } else {
                    error_log('Grading scale lookup failed: ' . $stmt->error);
                }
                $stmt->close();
            } else {
                error_log('Grading scale lookup failed: ' . $conn->error);
            }
        }
        foreach ($scales[$class_id] as $band) {
            if ($percentage >= (float)$band['minimum_percent'] && $percentage <= (float)$band['maximum_percent']) {
                return (string)$band['grade'];
            }
        }
    }

    if ($percentage >= 91) return 'A1';
    if ($percentage >= 81) return 'A2';
    if ($percentage >= 71) return 'B1';
    if ($percentage >= 61) return 'B2';
    if ($percentage >= 51) return 'C1';
    if ($percentage >= 41) return 'C2';
    if ($percentage >= 33) return 'D';
    return 'E';
}
