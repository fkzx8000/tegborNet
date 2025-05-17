<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['coordinator'], "גישה נדחתה. רכזים בלבד.");


$coordinator_id = get_current_user_id();


$defaultYear = intval(date('Y'));
$startYear = 2020;
$endYear = intval(date('Y'));


$selected_month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : $defaultYear;


$selected_month = validate_range($selected_month, 1, 12, intval(date('m')));
$selected_year = validate_range($selected_year, $startYear, $endYear, $defaultYear);


$date_range = calculate_date_range($selected_month, $selected_year);


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

$grand_total = $total_result && isset($total_result['total_hours']) ? $total_result['total_hours'] : 0;


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
                      c.id AS course_id,
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


$hebrew_months = get_hebrew_months();


$page_title = 'דוחות תגבור חודשיים';
$additional_css = ['reports.css'];




include __DIR__ . '/../templates/header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>📊 דוחות תגבור חודשיים</h2>
    </div>
</div>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>📊 דוחות תגבור חודשיים</h2>
    </div>
</div>

<!-- טופס סינון חודשי -->
<div class="dashboard-card">
    <h3 class="card-title">סינון לפי חודש ושנה</h3>

    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="filter-form">
        <input type="hidden" name="filter_type" value="monthly">

        <div class="form-group">
            <label for="month">חודש:</label>
            <select name="month" id="month">
                <?php foreach ($hebrew_months as $num => $name): ?>
                    <option value="<?php echo $num; ?>" <?php echo ($num == $selected_month) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="year">שנה:</label>
            <select name="year_select" id="year">
                <?php for ($y = $startYear; $y <= $endYear; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo ($y == $selected_year) ? 'selected' : ''; ?>>
                        <?php echo $y; ?>
                    </option>
                <?php endfor; ?>
            </select>
            <input type="hidden" name="year" id="year_hidden" value="<?php echo $selected_year; ?>">
            <button type="button" id="yearLockButton" onclick="toggleYearLock()">נעל שנה</button>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">סנן</button>
        </div>
    </form>
</div>

<div class="dashboard-card">
    <h3 class="card-title">תקופת הדוח: <?php echo $date_range['start_formatted']; ?> עד
        <?php echo $date_range['end_formatted']; ?>
    </h3>

    <div class="total-hours">
        <h3>סה"כ שעות לכל המתרגלים: <?php echo number_format($grand_total, 2); ?></h3>
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

                        $mentor_name = !empty($mentor['full_name']) ? $mentor['full_name'] : $mentor['username'];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($mentor_name); ?></td>
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

    <!-- פירוט שעות לפי מתרגל לפי קורס -->
    <h3>פירוט לפי מתרגל וקורס</h3>
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
                        $mentor_name = !empty($detail['full_name']) ? $detail['full_name'] : $detail['username'];
                        ?>
                        <tr>
                            <td>
                                <span class="mentor-detail-link" data-mentor-id="<?php echo $detail['mentor_id']; ?>"
                                    data-course-id="<?php echo $detail['course_id']; ?>"
                                    data-mentor-name="<?php echo htmlspecialchars($mentor_name); ?>"
                                    data-course-name="<?php echo htmlspecialchars($detail['course_name']); ?>">
                                    <?php echo htmlspecialchars($mentor_name); ?>
                                </span>
                            </td>
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

<!-- מודל להצגת פרטים נוספים -->
<div id="mentorDetailModal" class="modal">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <h3 id="detailModalTitle">פירוט תגבורים</h3>
        <div id="detailModalContent">
            <!-- תוכן ייטען דינמית ע"י JavaScript -->
        </div>
    </div>
</div>

<style>
    /* סגנונות למודל */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.4);
    }

    .modal-content {
        background-color: #fefefe;
        margin: 10% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 80%;
        max-width: 700px;
        border-radius: 5px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        position: relative;
    }

    .modal-close {
        color: #aaa;
        float: left;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        position: absolute;
        top: 10px;
        left: 20px;
    }

    .modal-close:hover,
    .modal-close:focus {
        color: black;
        text-decoration: none;
    }

    #detailModalContent {
        margin-top: 20px;
    }

    .detail-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }

    .detail-table th,
    .detail-table td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: right;
    }

    .detail-table th {
        background-color: #f2f2f2;
        font-weight: bold;
    }

    .detail-table tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    .detail-table tr:hover {
        background-color: #f1f1f1;
    }

    .loading {
        text-align: center;
        padding: 20px;
        color: #777;
    }

    .error {
        text-align: center;
        padding: 15px;
        background-color: #ffecec;
        color: #d33;
        border-radius: 4px;
        margin: 10px 0;
    }

    /* סגנון לקישור פרטי מתגבר */
    .mentor-detail-link {
        color: #3498db;
        text-decoration: underline;
        cursor: pointer;
        transition: color 0.3s;
        font-weight: bold;
    }

    .mentor-detail-link:hover {
        color: #2980b9;
        text-decoration: underline;
    }
</style>

<script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>

        function toggleYearLock() {
  var yearSelect = document.getElementById('year');
        var lockButton = document.getElementById('yearLockButton');
        var hiddenYear = document.getElementById('year_hidden');
        if (yearSelect.disabled) {
            yearSelect.disabled = false;
        lockButton.textContent = 'נעל שנה';
  } else {
            yearSelect.disabled = true;
        lockButton.textContent = 'פתח שנה';
  }
        hiddenYear.value = yearSelect.value;
}


        $(document).ready(function() {
            $("#year").on('change', function () {
                $("#year_hidden").val($(this).val());
            });


        $(document).on('click', '.mentor-detail-link', function() {
            console.log("Mentor link clicked");
        const mentorId = $(this).data('mentor-id');
        const courseId = $(this).data('course-id');
        const mentorName = $(this).data('mentor-name');
        const courseName = $(this).data('course-name');

        console.log("Mentor ID:", mentorId, "Course ID:", courseId);


        $('#detailModalTitle').text('פירוט תגבורים: ' + mentorName + ' - ' + courseName);


        $('#detailModalContent').html('<div class="loading">טוען נתונים...</div>');


        $('#mentorDetailModal').show();


        $.ajax({
            url: '../ajax/get_tutor_details.php',
        type: 'GET',
        data: {
            mentor_id: mentorId,
        course_id: courseId,
        start_date: '<?php echo $date_range['start']; ?>',
        end_date: '<?php echo $date_range['end']; ?>'
          },
        success: function(response) {
            console.log("AJAX response:", response);
        if (response.success) {
            let html = '<table class="detail-table">';
                html += '<thead><tr><th>תאריך</th><th>שעות</th><th>שמות תלמידים</th></tr></thead>';
            html += '<tbody>';
                  
                  if (response.data && response.data.length > 0) {
                    response.data.forEach(function (item) {
                        html += '<tr>';
                        html += '<td>' + formatDate(item.tutoring_date) + '</td>';
                        html += '<td>' + item.tutoring_hours + '</td>';
                        html += '<td>' + item.student_names + '</td>';
                        html += '</tr>';
                    });
                  } else {
                    html += '<tr><td colspan="3">לא נמצאו פרטים נוספים</td></tr>';
                  }

                html += '</tbody></table>';
        $('#detailModalContent').html(html);
              } else {
            $('#detailModalContent').html('<div class="error">שגיאה בטעינת הנתונים: ' + response.message + '</div>');
              }
          },
        error: function(xhr, status, error) {
            console.error("AJAX Error:", status, error);
        $('#detailModalContent').html('<div class="error">שגיאה בתקשורת עם השרת: ' + error + '</div>');
          }
      });

        return false;
  });


        function formatDate(dateString) {
      const date = new Date(dateString);
        return date.getDate().toString().padStart(2, '0') + '/' +
        (date.getMonth() + 1).toString().padStart(2, '0') + '/' +
        date.getFullYear();
  }


        $(document).on('click', '.modal-close', function() {
            $('#mentorDetailModal').hide();
  });


        $(window).on('click', function(e) {
      if ($(e.target).is('#mentorDetailModal')) {
            $('#mentorDetailModal').hide();
      }
  });
});
</script>

<?php

include __DIR__ . '/../templates/footer.php';
?>