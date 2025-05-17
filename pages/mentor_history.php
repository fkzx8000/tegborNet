<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['mentor', 'coordinator'], "גישה נדחתה. מתגברים ורכזים בלבד.");


$current_date = new DateTime();
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : (int) $current_date->format('m');
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : (int) $current_date->format('Y');


$selected_month = validate_range($selected_month, 1, 12, (int) $current_date->format('m'));
$selected_year = validate_range($selected_year, $current_date->format('Y') - 5, $current_date->format('Y'), (int) $current_date->format('Y'));


$date_range = calculate_date_range($selected_month, $selected_year);


$mentor_id = get_current_user_id();


$sql = "SELECT tl.tutoring_date, tl.student_names, tl.tutoring_hours, c.course_name
        FROM tutoring_logs tl
        JOIN courses c ON tl.course_id = c.id
        WHERE tl.mentor_id = ?
        AND tl.tutoring_date BETWEEN ? AND ?
        ORDER BY tl.tutoring_date DESC";

$logs = db_fetch_all($sql, 'iss', [$mentor_id, $date_range['start'], $date_range['end']]);


$total_hours = 0;
foreach ($logs as $log) {
    $total_hours += $log['tutoring_hours'];
}


$hebrew_months = get_hebrew_months();


$page_title = 'היסטוריית תגבורים';
$additional_css = ['mentor.css'];


include __DIR__ . '/../templates/header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>היסטוריית תגבורים</h2>
    </div>
</div>

<div class="dashboard-card">
    <h3 class="card-title">סינון לפי חודש ושנה</h3>

    <form method="GET" class="filter-form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <div class="form-group">
            <label for="month">בחר חודש:</label>
            <select name="month" id="month">
                <?php foreach ($hebrew_months as $num => $name): ?>
                    <option value="<?php echo $num; ?>" <?php echo ($num == $selected_month) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="year">בחר שנה:</label>
            <select name="year" id="year">
                <?php for ($y = $current_date->format('Y') - 5; $y <= $current_date->format('Y'); $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo ($y == $selected_year) ? 'selected' : ''; ?>>
                        <?php echo $y; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">סנן</button>
    </form>
</div>

<div class="dashboard-card">
    <h3 class="card-title">רשימת תגבורים</h3>
    <div class="date-range-info">
        מציג רשומות מהתאריך <?php echo $date_range['start_formatted']; ?> עד <?php echo $date_range['end_formatted']; ?>
    </div>

    <div class="total-summary">
        <strong>סה"כ שעות בתקופה זו:</strong> <?php echo number_format($total_hours, 2); ?> שעות
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>תאריך</th>
                    <th>קורס</th>
                    <th>שמות תלמידים</th>
                    <th>שעות</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($log['tutoring_date'])); ?></td>
                            <td><?php echo htmlspecialchars($log['course_name']); ?></td>
                            <td><?php echo htmlspecialchars($log['student_names']); ?></td>
                            <td><?php echo htmlspecialchars($log['tutoring_hours']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">אין רשומות תגבור לתקופה זו.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php

include __DIR__ . '/../templates/footer.php';
?>