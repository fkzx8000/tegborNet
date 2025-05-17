<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['coordinator'], "גישה נדחתה. רק רכזים מורשים.");


$coordinator_id = get_current_user_id();


$defaultYear = intval(date('Y'));
$startYear = 2020;
$endYear = intval(date('Y'));


$selected_semester = isset($_GET['semester']) ? $_GET['semester'] : 'A';
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : $defaultYear;


if (!in_array($selected_semester, ['A', 'B', 'C'])) {
    $selected_semester = 'A';
}
$selected_year = validate_range($selected_year, $startYear, $endYear, $defaultYear);


$date_range = calculate_semester_range($selected_semester, $selected_year);


$total_hours_sql = "SELECT SUM(t.tutoring_hours) AS total_hours
                   FROM tutoring_logs t
                   JOIN coordinator_mentors cm ON t.mentor_id = cm.mentor_id
                   WHERE cm.coordinator_id = ?
                     AND t.tutoring_date BETWEEN ? AND ?";

$total_result = db_fetch_one($total_hours_sql, 'iss', [
    $coordinator_id,
    $date_range['start'],
    $date_range['end']
]);

$grandTotal = $total_result && isset($total_result['total_hours']) ? $total_result['total_hours'] : 0;


$mentor_hours_sql = "SELECT 
                     m.id AS mentor_id, 
                     m.username, 
                     md.full_name,
                     SUM(t.tutoring_hours) AS total_hours
                   FROM tutoring_logs t
                   JOIN coordinator_mentors cm ON t.mentor_id = cm.mentor_id
                   JOIN users m ON m.id = t.mentor_id
                   LEFT JOIN mentor_details md ON md.mentor_id = m.id
                   WHERE cm.coordinator_id = ?
                     AND t.tutoring_date BETWEEN ? AND ?
                   GROUP BY t.mentor_id
                   ORDER BY m.username";

$mentor_hours = db_fetch_all($mentor_hours_sql, 'iss', [
    $coordinator_id,
    $date_range['start'],
    $date_range['end']
]);


$course_details_sql = "SELECT 
                      m.id AS mentor_id, 
                      m.username, 
                      md.full_name,
                      c.course_name, 
                      SUM(t.tutoring_hours) AS total_hours
                     FROM tutoring_logs t
                     JOIN coordinator_mentors cm ON t.mentor_id = cm.mentor_id
                     JOIN users m ON m.id = t.mentor_id
                     LEFT JOIN mentor_details md ON md.mentor_id = m.id
                     JOIN courses c ON c.id = t.course_id
                     WHERE cm.coordinator_id = ?
                       AND t.tutoring_date BETWEEN ? AND ?
                     GROUP BY t.mentor_id, t.course_id
                     ORDER BY m.username, c.course_name";

$course_details = db_fetch_all($course_details_sql, 'iss', [
    $coordinator_id,
    $date_range['start'],
    $date_range['end']
]);


$page_title = 'דוחות תגבור לפי סמסטר';
$additional_css = ['reports.css'];
$additional_scripts = "
    function toggleYearLockSemester() {
      var yearSelect = document.getElementById('year_semester');
      var lockButton = document.getElementById('yearLockButtonSemester');
      var hiddenYear = document.getElementById('year_hidden_semester');
      if (yearSelect.disabled) {
          yearSelect.disabled = false;
          lockButton.textContent = 'נעל שנה';
      } else {
          yearSelect.disabled = true;
          lockButton.textContent = 'פתח שנה';
      }
      hiddenYear.value = yearSelect.value;
    }
    document.addEventListener('DOMContentLoaded', function(){
      document.getElementById('year_semester').addEventListener('change', function(){
          document.getElementById('year_hidden_semester').value = this.value;
      });
    });
";


include __DIR__ . '/../templates/header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>📊 דוחות תגבור לפי סמסטר</h2>
    </div>
</div>

<!-- טופס סינון לסמסטר -->
<div class="dashboard-card">
    <h3 class="card-title">סינון לפי סמסטר ושנה</h3>

    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="filter-form">
        <input type="hidden" name="filter_type" value="semester">

        <div class="form-group">
            <label for="semester">סמסטר:</label>
            <select name="semester" id="semester">
                <option value="A" <?php echo ($selected_semester == 'A') ? 'selected' : ''; ?>>סמסטר א (אוקטובר-פברואר)
                </option>
                <option value="B" <?php echo ($selected_semester == 'B') ? 'selected' : ''; ?>>סמסטר ב (מרץ-אוגוסט)
                </option>
                <option value="C" <?php echo ($selected_semester == 'C') ? 'selected' : ''; ?>>סמסטר קיץ (אוגוסט-אוקטובר)
                </option>
            </select>
        </div>

        <div class="form-group">
            <label for="year_semester">שנה:</label>
            <select name="year_select" id="year_semester">
                <?php for ($y = $startYear; $y <= $endYear; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo ($y == $selected_year) ? 'selected' : ''; ?>>
                        <?php echo $y; ?>
                    </option>
                <?php endfor; ?>
            </select>
            <input type="hidden" name="year" id="year_hidden_semester" value="<?php echo $selected_year; ?>">
            <button type="button" id="yearLockButtonSemester" onclick="toggleYearLockSemester()">נעל שנה</button>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">סנן</button>
        </div>
    </form>
</div>

<div class="dashboard-card">
    <h3 class="card-title">תקופת הדוח: <?php echo $date_range['start_formatted']; ?> עד
        <?php echo $date_range['end_formatted']; ?></h3>

    <div class="total-hours">
        סה"כ שעות לתקופה: <?php echo number_format($grandTotal, 2); ?> שעות
    </div>

    <!-- סיכום שעות לכל מתרגל -->
    <h3>👨🏫 סיכום מתרגלים</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>מתרגל</th>
                    <th>סה"כ שעות</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($mentor_hours)): ?>
                    <?php foreach ($mentor_hours as $mentor):

                        $mentorName = !empty($mentor['full_name']) ? $mentor['full_name'] : $mentor['username'];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($mentorName); ?></td>
                            <td><?php echo number_format($mentor['total_hours'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2">לא נמצאו רשומות.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- פירוט שעות לפי מתרגל וקורס -->
    <h3>📚 פירוט קורסים לפי מתרגל</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>מתרגל</th>
                    <th>קורס</th>
                    <th>סה"כ שעות</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($course_details)): ?>
                    <?php foreach ($course_details as $detail):

                        $mentorName = !empty($detail['full_name']) ? $detail['full_name'] : $detail['username'];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($mentorName); ?></td>
                            <td><?php echo htmlspecialchars($detail['course_name']); ?></td>
                            <td><?php echo number_format($detail['total_hours'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">לא נמצאו רשומות.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php

include __DIR__ . '/../templates/footer.php';
?>