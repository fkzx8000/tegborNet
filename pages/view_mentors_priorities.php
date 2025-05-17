<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['coordinator'], "גישה נדחתה. רק רכזים מורשים.");


$coordinator_id = get_current_user_id();


$mentors_sql = "SELECT DISTINCT ps.mentor_email, am.selection_count 
                FROM project_selections ps
                JOIN projects p ON ps.project_id = p.id
                JOIN authorized_mentors am ON ps.mentor_email = am.email 
                WHERE p.coordinator_id = ?
                ORDER BY ps.mentor_email";
$mentors = db_fetch_all($mentors_sql, 'i', [$coordinator_id]);


$selected_mentor = isset($_GET['mentor']) ? filter_input(INPUT_GET, 'mentor', FILTER_VALIDATE_EMAIL) : null;
$mentor_priorities = [];

if ($selected_mentor) {
    $priorities_sql = "SELECT ps.priority, p.id, p.title, p.session
                      FROM project_selections ps
                      JOIN projects p ON ps.project_id = p.id
                      WHERE ps.mentor_email = ? AND p.coordinator_id = ?
                      ORDER BY ps.priority ASC";
    $mentor_priorities = db_fetch_all($priorities_sql, 'si', [$selected_mentor, $coordinator_id]);
}


$show_all = isset($_GET['show_all']) && $_GET['show_all'] == '1';
$all_priorities = [];

if ($show_all) {
    $all_priorities_sql = "SELECT ps.mentor_email, ps.priority, p.id, p.title, p.session
                          FROM project_selections ps
                          JOIN projects p ON ps.project_id = p.id
                          WHERE p.coordinator_id = ?
                          ORDER BY ps.mentor_email, ps.priority ASC";
    $all_priorities = db_fetch_all($all_priorities_sql, 'i', [$coordinator_id]);
}


$page_title = 'עדיפויות שופטים';
$additional_css = ['coordinator.css'];


include __DIR__ . '/../templates/header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>צפייה בעדיפויות שופטים</h2>
    </div>
    
    <div class="dashboard-actions">
        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?show_all=1" class="btn btn-primary">
            <i class="fas fa-users"></i> הצג את כל השופטים
        </a>
        
        <a href="project_distribution.php" class="btn btn-secondary">
            <i class="fas fa-chart-pie"></i> חישוב חלוקה אופטימלית
        </a>
        
        <a href="manage_projects.php" class="btn btn-outline">
            <i class="fas fa-arrow-right"></i> חזרה לניהול פרויקטים
        </a>
    </div>
</div>

<div class="dashboard-card">
    <h3 class="card-title">בחירת שופט להצגת העדיפויות</h3>
    
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="search-form">
        <div class="form-group">
            <label for="mentor">בחר שופט:</label>
            <select name="mentor" id="mentor" class="select-input" onchange="this.form.submit()">
                <option value="">-- בחר שופט --</option>
                <?php foreach ($mentors as $mentor): ?>
                    <option value="<?php echo htmlspecialchars($mentor['mentor_email']); ?>" 
                            <?php echo $selected_mentor == $mentor['mentor_email'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($mentor['mentor_email']); ?> 
                        (<?php echo $mentor['selection_count']; ?> בחירות)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($selected_mentor && count($mentor_priorities) > 0): ?>
<div class="dashboard-card">
    <h3 class="card-title">עדיפויות השופט: <?php echo htmlspecialchars($selected_mentor); ?></h3>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>עדיפות</th>
                    <th>שם הפרויקט</th>
                    <th>מושב</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mentor_priorities as $priority): ?>
                    <tr>
                        <td class="priority-number"><?php echo $priority['priority']; ?></td>
                        <td><?php echo htmlspecialchars($priority['title']); ?></td>
                        <td><?php echo htmlspecialchars($priority['session']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="export-section">
        <a href="export_priorities.php?mentor=<?php echo urlencode($selected_mentor); ?>" class="btn btn-outline">
            <i class="fas fa-file-export"></i> ייצוא לקובץ CSV
        </a>
    </div>
</div>
<?php elseif ($selected_mentor): ?>
<div class="dashboard-card">
    <div class="empty-state">
        <div class="empty-state-icon">📋</div>
        <h3>אין נתוני עדיפויות</h3>
        <p>השופט <?php echo htmlspecialchars($selected_mentor); ?> לא בחר פרויקטים.</p>
    </div>
</div>
<?php endif; ?>

<?php if ($show_all && count($all_priorities) > 0): ?>
<div class="dashboard-card">
    <h3 class="card-title">כל עדיפויות השופטים</h3>
    
    <?php
    
    $grouped_priorities = [];
    foreach ($all_priorities as $priority) {
        $email = $priority['mentor_email'];
        if (!isset($grouped_priorities[$email])) {
            $grouped_priorities[$email] = [];
        }
        $grouped_priorities[$email][] = $priority;
    }
    ?>
    
    <?php foreach ($grouped_priorities as $email => $priorities): ?>
        <div class="mentor-priorities-section">
            <h4><?php echo htmlspecialchars($email); ?></h4>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>עדיפות</th>
                            <th>שם הפרויקט</th>
                            <th>מושב</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($priorities as $priority): ?>
                            <tr>
                                <td class="priority-number"><?php echo $priority['priority']; ?></td>
                                <td><?php echo htmlspecialchars($priority['title']); ?></td>
                                <td><?php echo htmlspecialchars($priority['session']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
    
    <div class="export-section">
        <a href="export_priorities.php?all=1" class="btn btn-outline">
            <i class="fas fa-file-export"></i> ייצוא הכל לקובץ CSV
        </a>
    </div>
</div>
<?php elseif ($show_all): ?>
<div class="dashboard-card">
    <div class="empty-state">
        <div class="empty-state-icon">📊</div>
        <h3>אין נתוני עדיפויות</h3>
        <p>לא נמצאו שופטים שבחרו פרויקטים.</p>
    </div>
</div>
<?php endif; ?>

<style>
/* סגנונות ייחודיים לדף זה */
.mentor-priorities-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.mentor-priorities-section h4 {
    background-color: #f8f9fa;
    padding: 10px 15px;
    border-radius: 5px;
    margin-bottom: 15px;
    color: #2980b9;
}

.priority-number {
    background-color: #2980b9;
    color: white;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.export-section {
    margin-top: 20px;
    text-align: left;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    background-color: #f9f9f9;
    border-radius: 8px;
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 15px;
    color: #7f8c8d;
}

.empty-state h3 {
    margin-bottom: 10px;
    color: #34495e;
}

.empty-state p {
    color: #7f8c8d;
}

.search-form {
    max-width: 500px;
}

.select-input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 1rem;
}

.btn-outline {
    background-color: transparent;
    border: 1px solid #2980b9;
    color: #2980b9;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    transition: all 0.3s;
}

.btn-outline:hover {
    background-color: #2980b9;
    color: white;
}

table th, table td {
    padding: 12px 15px;
}
</style>

<?php

include __DIR__ . '/../templates/footer.php';
?>