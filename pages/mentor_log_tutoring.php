<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['mentor', 'coordinator'], "גישה נדחתה. גישה למתגבר או לרכז בלבד.");


$mentor_id = get_current_user_id();


$coordinator_id = get_mentor_coordinator_id($mentor_id);


$courses = get_coordinator_courses($coordinator_id);


$current_date = new DateTime();
$month = (int) $current_date->format('m');
$year = (int) $current_date->format('Y');
$date_range = calculate_date_range($month, $year);


$sql = "SELECT tl.tutoring_date, tl.student_names, tl.tutoring_hours, c.course_name
        FROM tutoring_logs tl
        JOIN courses c ON tl.course_id = c.id
        WHERE tl.mentor_id = ?
        AND tl.tutoring_date BETWEEN ? AND ?
        ORDER BY tl.tutoring_date DESC";

$logs = db_fetch_all($sql, 'iss', [$mentor_id, $date_range['start'], $date_range['end']]);


$page_title = 'רישום תגבור';
$additional_css = ['mentor.css'];
$additional_js_footer = ['mentor.js'];


include __DIR__ . '/../templates/header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>רישום מפגש תגבור</h2>
    </div>
</div>

<div class="dashboard-card">
    <h3 class="card-title">רישום מפגש תגבור חדש</h3>

    <form action="<?php echo get_site_url(); ?>/api/tutoring.php?action=add" method="post" class="log-tutoring-form"
        id="tutoring-form">
        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">

        <div class="form-group">
            <label for="course_id">קורס:</label>
            <select name="course_id" id="course_id" required>
                <option value="" disabled selected>בחר קורס</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="student_names">שמות תלמידים (מופרדים בפסיקים):</label>
            <textarea id="student_names" name="student_names"></textarea>
        </div>

        <div class="form-group">
            <label for="tutoring_date">תאריך תגבור:</label>
            <input type="date" id="tutoring_date" name="tutoring_date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="form-group">
            <label for="tutoring_hours">שעות תגבור:</label>
            <input type="number" id="tutoring_hours" name="tutoring_hours" step="0.25" min="0.25" required>
        </div>

        <button type="submit" class="btn btn-primary" name="log_tutoring">שמור רישום</button>
    </form>
</div>

<div class="dashboard-card">
    <h3 class="card-title">רשומות תגבור שלך (חודש נוכחי)</h3>
    <div class="date-range-info">
        מציג רשומות מהתאריך <?php echo $date_range['start_formatted']; ?> עד <?php echo $date_range['end_formatted']; ?>
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
                        <td colspan="4">אין רשומות תגבור לחודש זה.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>

    document.getElementById('tutoring-form').addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch(this.action, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('התגבור נרשם בהצלחה!');

                    window.location.reload();
                } else {
                    alert(data.message || 'שגיאה בשמירת רישום התגבור');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('שגיאה בתקשורת עם השרת');
            });
    });
</script>

<?php

include __DIR__ . '/../templates/footer.php';
?>