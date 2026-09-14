<?php
session_start();
require_once '../../includes/access.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}
if (access_is_student()) { header('Location: ../portal/student.php'); exit; }
if (access_is_teacher()) { header('Location: ../portal/teacher.php'); exit; }

require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}

$conn->set_charset('utf8mb4');

function dashboard_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dashboard_table_exists($conn, $table)
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0) > 0;
}

function dashboard_query_rows($conn, $sql)
{
    $rows = array();
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

$summary = array(
    'classes' => 0,
    'sections' => 0,
    'students' => 0,
    'girls' => 0,
    'boys' => 0,
    'concession' => 0
);

$summary['classes'] = (int)($conn->query("SELECT COUNT(*) AS total FROM classes WHERE status = 1")->fetch_assoc()['total'] ?? 0);
$summary['sections'] = (int)($conn->query("SELECT COUNT(*) AS total FROM sections WHERE status = 1")->fetch_assoc()['total'] ?? 0);
$student_summary = $conn->query("SELECT COUNT(*) AS total, SUM(gender = 'Female') AS girls, SUM(gender = 'Male') AS boys, SUM(concession > 0) AS concession FROM students WHERE status = 1");
if ($student_summary) {
    $row = $student_summary->fetch_assoc();
    $summary['students'] = (int)($row['total'] ?? 0);
    $summary['girls'] = (int)($row['girls'] ?? 0);
    $summary['boys'] = (int)($row['boys'] ?? 0);
    $summary['concession'] = (int)($row['concession'] ?? 0);
}

$class_rows = dashboard_query_rows($conn, "SELECT c.class_name, COUNT(s.id) AS total FROM classes c LEFT JOIN students s ON s.class_id = c.id AND s.status = 1 WHERE c.status = 1 GROUP BY c.id, c.class_name, c.class_order ORDER BY c.class_order, c.id");
$house_rows = dashboard_query_rows($conn, "SELECT h.house_name, COALESCE(h.house_color, '#2563eb') AS house_color, COUNT(s.id) AS total FROM houses h LEFT JOIN students s ON s.house_id = h.id AND s.status = 1 WHERE h.status = 1 GROUP BY h.id, h.house_name, h.house_color ORDER BY h.house_name");

$attendance_available = dashboard_table_exists($conn, 'student_attendance');
$attendance_rows = array();
$attendance_message = '';
if ($attendance_available) {
    $attendance_rows = dashboard_query_rows($conn, "SELECT c.class_name, SUM(a.status = 'Present') AS present_total, SUM(a.status = 'Absent') AS absent_total FROM classes c LEFT JOIN students s ON s.class_id = c.id AND s.status = 1 LEFT JOIN student_attendance a ON a.student_id = s.id AND a.attendance_date = CURDATE() WHERE c.status = 1 GROUP BY c.id, c.class_name, c.class_order ORDER BY c.class_order, c.id");
} else {
    $attendance_message = 'Attendance table is not available in the current database.';
}

$fee_available = false;
$fee_table = '';
foreach (array('fee_collections', 'fees', 'student_fees', 'fee_payments') as $candidate) {
    if (dashboard_table_exists($conn, $candidate)) {
        $fee_available = true;
        $fee_table = $candidate;
        break;
    }
}
$fee_month_rows = array();
$fee_daily_rows = array();
$fee_message = '';
if (!$fee_available) {
    $fee_message = 'Fee collection table is not available in the current database.';
} elseif ($fee_table === 'fee_payments') {
    $fee_month_result = $conn->query("SELECT DATE_FORMAT(payment_date, '%b %Y') AS label, SUM(amount) AS total FROM fee_payments GROUP BY YEAR(payment_date), MONTH(payment_date) ORDER BY YEAR(payment_date), MONTH(payment_date) LIMIT 12");
    while ($fee_month_result && ($row = $fee_month_result->fetch_assoc())) {
        $fee_month_rows[] = $row;
    }
    $fee_daily_result = $conn->query("SELECT DATE_FORMAT(payment_date, '%d %b') AS label, SUM(amount) AS total FROM fee_payments WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY payment_date ORDER BY payment_date");
    while ($fee_daily_result && ($row = $fee_daily_result->fetch_assoc())) {
        $fee_daily_rows[] = $row;
    }
    $fee_message = 'Fee payments are linked to students and shown below.';
} else {
    $fee_message = 'Fee table detected (' . $fee_table . '), but its collection columns need to be mapped before displaying financial totals.';
}

$chart_data = array(
    'classLabels' => array_column($class_rows, 'class_name'),
    'classValues' => array_map('intval', array_column($class_rows, 'total')),
    'houseLabels' => array_column($house_rows, 'house_name'),
    'houseValues' => array_map('intval', array_column($house_rows, 'total')),
    'houseColors' => array_column($house_rows, 'house_color'),
    'attendanceLabels' => array_column($attendance_rows, 'class_name'),
    'attendancePresent' => array_map('intval', array_column($attendance_rows, 'present_total')),
    'attendanceAbsent' => array_map('intval', array_column($attendance_rows, 'absent_total')),
    'feeMonthLabels' => array_column($fee_month_rows, 'label'),
    'feeMonthValues' => array_map('floatval', array_column($fee_month_rows, 'total')),
    'feeDayLabels' => array_column($fee_daily_rows, 'label'),
    'feeDayValues' => array_map('floatval', array_column($fee_daily_rows, 'total'))
);

include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';
?>

<div class="content-wrapper dashboard-screen">
    <section class="content-header dashboard-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h1><i class="fas fa-chart-pie mr-2"></i>Dashboard</h1>
                    <p class="text-muted mb-0">A live overview of your school data</p>
                </div>
                <span class="badge badge-light mt-2 mt-md-0"><i class="far fa-calendar-alt mr-1"></i><?= date('d M Y'); ?></span>
            </div>
        </div>
    </section>

    <section class="content dashboard-content">
        <div class="container-fluid">
            <div class="row dashboard-kpis">
                <?php
                $cards = array(
                    array('Total Classes', $summary['classes'], 'fa-school', 'primary'),
                    array('Total Sections', $summary['sections'], 'fa-layer-group', 'info'),
                    array('Enrolled Students', $summary['students'], 'fa-user-graduate', 'success'),
                    array('Girls', $summary['girls'], 'fa-female', 'pink'),
                    array('Boys', $summary['boys'], 'fa-male', 'warning'),
                    array('Concession Students', $summary['concession'], 'fa-hand-holding-usd', 'purple')
                );
                foreach ($cards as $card):
                ?>
                    <div class="col-6 col-xl-2 col-md-4">
                        <div class="small-box bg-<?= $card[3]; ?>">
                            <div class="inner"><h3><?= $card[1]; ?></h3><p><?= dashboard_e($card[0]); ?></p></div>
                            <div class="icon"><i class="fas <?= $card[2]; ?>"></i></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row dashboard-charts">
                <div class="col-lg-6">
                    <div class="card dashboard-chart-card">
                        <div class="card-header"><i class="fas fa-users mr-1"></i> Class-wise Strength</div>
                        <div class="card-body"><canvas id="classChart"></canvas></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card dashboard-chart-card">
                        <div class="card-header"><i class="fas fa-home mr-1"></i> House-wise Strength</div>
                        <div class="card-body"><canvas id="houseChart"></canvas></div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card dashboard-chart-card">
                        <div class="card-header"><i class="fas fa-user-check mr-1"></i> Present / Absent by Class <small class="text-muted">(today)</small></div>
                        <div class="card-body">
                            <?php if ($attendance_message !== ''): ?><div class="alert alert-warning mb-0"><?= dashboard_e($attendance_message); ?></div><?php endif; ?>
                            <canvas id="attendanceChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card dashboard-chart-card">
                        <div class="card-header"><i class="fas fa-wallet mr-1"></i> Fee Collection</div>
                        <div class="card-body">
                            <?php if ($fee_message !== ''): ?><div class="alert alert-warning mb-3"><?= dashboard_e($fee_message); ?></div><?php endif; ?>
                            <canvas id="feeChart"></canvas>
                            <small class="text-muted d-block mt-2">Monthly and daily collections will appear here after the fee collection table is configured.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
.dashboard-screen {
    min-height: calc(100vh - 57px);
}

.dashboard-header {
    padding-top: .75rem;
    padding-bottom: .35rem;
}

.dashboard-header h1 {
    margin-bottom: 0;
    font-size: 1.35rem;
}

.dashboard-content {
    padding-bottom: .65rem;
}

.dashboard-kpis {
    margin-left: -5px;
    margin-right: -5px;
}

.dashboard-kpis > [class*="col-"] {
    padding-left: 5px;
    padding-right: 5px;
}

.dashboard-kpis .small-box {
    min-height: 82px;
    margin-bottom: 10px;
}

.dashboard-kpis .small-box .inner {
    padding: 10px 12px;
}

.dashboard-kpis .small-box h3 {
    margin: 0 0 2px;
    font-size: 1.45rem;
}

.dashboard-kpis .small-box p {
    margin: 0;
    font-size: .76rem;
    line-height: 1.1;
}

.dashboard-kpis .small-box .icon {
    top: 10px;
    right: 10px;
    font-size: 2rem;
}

.dashboard-charts {
    margin-left: -5px;
    margin-right: -5px;
}

.dashboard-charts > [class*="col-"] {
    padding: 0 5px;
    margin-bottom: 10px;
}

.dashboard-chart-card {
    height: 220px;
    margin-bottom: 0;
}

.dashboard-chart-card .card-header {
    min-height: 39px;
    padding: .62rem .85rem;
    font-size: .86rem;
}

.dashboard-chart-card .card-body {
    position: relative;
    height: 179px;
    padding: .45rem .7rem;
}

.dashboard-chart-card canvas {
    width: 100% !important;
    height: 165px !important;
}

.dashboard-chart-card .alert {
    position: absolute;
    z-index: 2;
    top: 7px;
    left: 10px;
    right: 10px;
    padding: .45rem .6rem;
    margin: 0;
    font-size: .72rem;
}

.dashboard-chart-card small {
    display: none !important;
}

@media (min-width: 992px) {
    .dashboard-charts > .col-lg-7 {
        flex: 0 0 58.333333%;
        max-width: 58.333333%;
    }

    .dashboard-charts > .col-lg-5 {
        flex: 0 0 41.666667%;
        max-width: 41.666667%;
    }
}

@media (max-height: 800px) and (min-width: 992px) {
    .dashboard-chart-card {
        height: 195px;
    }

    .dashboard-chart-card .card-body {
        height: 154px;
    }

    .dashboard-chart-card canvas {
        height: 140px !important;
    }
}

@media (max-width: 991.98px) {
    .dashboard-screen {
        min-height: auto;
    }

    .dashboard-chart-card {
        height: 220px;
    }
}
</style>

<script src="<?= BASE_URL; ?>/assets/plugins/chart.js/Chart.min.js"></script>
<script>
window.addEventListener('load', function () {
    var data = <?= json_encode($chart_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var empty = function (values) { return !values || values.length === 0 || values.every(function (value) { return Number(value) === 0; }); };
    var palette = ['#2563eb', '#14b8a6', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#ef4444', '#84cc16'];

    new Chart(document.getElementById('classChart'), {
        type: 'bar',
        data: { labels: data.classLabels, datasets: [{ data: data.classValues, backgroundColor: palette, borderWidth: 2, borderColor: '#fff' }] },
        options: { responsive: true, maintainAspectRatio: false, legend: { display: false }, scales: { yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }] } }
    });

    new Chart(document.getElementById('houseChart'), {
        type: 'bar',
        data: { labels: data.houseLabels, datasets: [{ data: data.houseValues, backgroundColor: data.houseColors.length ? data.houseColors : palette, borderWidth: 2, borderColor: '#fff' }] },
        options: { responsive: true, maintainAspectRatio: false, legend: { display: false }, scales: { yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }] } }
    });

    new Chart(document.getElementById('attendanceChart'), {
        type: 'bar',
        data: { labels: data.attendanceLabels, datasets: [
            { label: 'Present', data: data.attendancePresent, backgroundColor: '#14b8a6' },
            { label: 'Absent', data: data.attendanceAbsent, backgroundColor: '#ef4444' }
        ] },
        options: { responsive: true, maintainAspectRatio: false, scales: { yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }] } }
    });

    new Chart(document.getElementById('feeChart'), {
        type: 'line',
        data: { labels: data.feeMonthLabels.length ? data.feeMonthLabels : ['No fee data'], datasets: [{ label: 'Monthly Collection', data: data.feeMonthValues.length ? data.feeMonthValues : [0], borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.12)', fill: true }] },
        options: { responsive: true, maintainAspectRatio: false, scales: { yAxes: [{ ticks: { beginAtZero: true } }] } }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
