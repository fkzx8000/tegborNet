<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['coordinator'], "גישה נדחתה. רק רכזים מורשים.");


$coordinator_id = get_current_user_id();


$projects_per_mentor = isset($_POST['projects_per_mentor']) ? intval($_POST['projects_per_mentor']) : 2;
$prioritize_by = isset($_POST['prioritize_by']) ? $_POST['prioritize_by'] : 'priority';
$max_mentors_per_project = isset($_POST['max_mentors_per_project']) ? intval($_POST['max_mentors_per_project']) : 1;


$calculate = isset($_POST['calculate']);
$projects = [];
$mentors = [];
$distribution = [];
$project_popularity = [];
$mentors_count = 0;
$projects_count = 0;

if ($calculate) {

    $mentors_sql = "SELECT DISTINCT ps.mentor_email 
                    FROM project_selections ps
                    JOIN projects p ON ps.project_id = p.id
                    WHERE p.coordinator_id = ?
                    ORDER BY ps.mentor_email";
    $mentors = db_fetch_all($mentors_sql, 'i', [$coordinator_id]);
    $mentors_count = count($mentors);


    $projects_sql = "SELECT p.id, p.title, p.session, 
                     (SELECT COUNT(*) FROM project_selections ps WHERE ps.project_id = p.id) as popularity
                     FROM projects p
                     WHERE p.coordinator_id = ? AND p.is_active = 1
                     ORDER BY p.session, p.title";
    $projects = db_fetch_all($projects_sql, 'i', [$coordinator_id]);
    $projects_count = count($projects);


    $priorities_sql = "SELECT ps.project_id, ps.mentor_email, ps.priority
                      FROM project_selections ps
                      JOIN projects p ON ps.project_id = p.id
                      WHERE p.coordinator_id = ?
                      ORDER BY ps.priority";
    $priorities = db_fetch_all($priorities_sql, 'i', [$coordinator_id]);

    $priority_matrix = [];
    $project_popularity = [];

    foreach ($priorities as $priority) {
        $project_id = $priority['project_id'];
        $mentor = $priority['mentor_email'];
        $priority_value = $priority['priority'];

        if (!isset($priority_matrix[$project_id])) {
            $priority_matrix[$project_id] = [];
            $project_popularity[$project_id] = 0;
        }

        $priority_matrix[$project_id][$mentor] = $priority_value;
        $project_popularity[$project_id]++;
    }


    $distribution = calculateOptimalDistribution($projects, $mentors, $priority_matrix, $project_popularity, $projects_per_mentor, $prioritize_by, $max_mentors_per_project);
}

function calculateOptimalDistribution($projects, $mentors, $priority_matrix, $project_popularity, $projects_per_mentor, $prioritize_by, $max_mentors_per_project)
{

    $distribution = [];
    foreach ($mentors as $mentor) {
        $distribution[$mentor['mentor_email']] = [];
    }


    $project_allocation_count = [];
    foreach ($projects as $project) {
        $project_allocation_count[$project['id']] = 0;
    }


    if ($prioritize_by === 'popularity') {

        usort($projects, function ($a, $b) use ($project_popularity) {
            $a_popularity = isset($project_popularity[$a['id']]) ? $project_popularity[$a['id']] : 0;
            $b_popularity = isset($project_popularity[$b['id']]) ? $project_popularity[$b['id']] : 0;
            return $b_popularity - $a_popularity;
        });
    }


    foreach ($projects as $project) {
        $project_id = $project['id'];


        if (!isset($priority_matrix[$project_id]) || empty($priority_matrix[$project_id])) {
            continue;
        }


        $project_priorities = $priority_matrix[$project_id];
        asort($project_priorities);


        foreach ($project_priorities as $mentor_email => $priority) {

            if ($project_allocation_count[$project_id] >= $max_mentors_per_project) {
                break;
            }


            if (count($distribution[$mentor_email]) < $projects_per_mentor) {
                $distribution[$mentor_email][] = [
                    'project_id' => $project_id,
                    'title' => $project['title'],
                    'session' => $project['session'],
                    'priority' => $priority
                ];

                $project_allocation_count[$project_id]++;
            }
        }
    }


    foreach ($mentors as $mentor) {
        $mentor_email = $mentor['mentor_email'];


        if (count($distribution[$mentor_email]) >= $projects_per_mentor) {
            continue;
        }


        foreach ($priority_matrix as $project_id => $priorities) {

            if (isset($priorities[$mentor_email]) && $project_allocation_count[$project_id] < $max_mentors_per_project) {

                $project_info = null;
                foreach ($projects as $project) {
                    if ($project['id'] == $project_id) {
                        $project_info = $project;
                        break;
                    }
                }

                if ($project_info) {
                    $distribution[$mentor_email][] = [
                        'project_id' => $project_id,
                        'title' => $project_info['title'],
                        'session' => $project_info['session'],
                        'priority' => $priorities[$mentor_email]
                    ];

                    $project_allocation_count[$project_id]++;


                    if (count($distribution[$mentor_email]) >= $projects_per_mentor) {
                        break;
                    }
                }
            }
        }
    }

    return $distribution;
}


$page_title = 'חלוקת פרויקטים לשופטים';
$additional_css = ['main.css'];


include __DIR__ . '/../templates/header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>חישוב חלוקה אופטימלית של פרויקטים</h2>
    </div>

    <div class="dashboard-actions">
        <a href="view_mentors_priorities.php" class="btn btn-primary">
            <i class="fas fa-list-ol"></i> צפייה בעדיפויות שופטים
        </a>

        <a href="manage_projects.php" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> חזרה לניהול פרויקטים
        </a>
    </div>
</div>

<div class="dashboard-card">
    <h3 class="card-title">הגדרת פרמטרים לחישוב</h3>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" class="distribution-form">
        <div class="form-grid">
            <div class="form-group">
                <label for="projects_per_mentor">כמות פרויקטים לשופט:</label>
                <input type="number" id="projects_per_mentor" name="projects_per_mentor"
                    value="<?php echo $projects_per_mentor; ?>" min="1" max="10" required>
                <small>מספר הפרויקטים המקסימלי שיוקצה לכל שופט</small>
            </div>

            <div class="form-group">
                <label for="max_mentors_per_project">כמות שופטים לפרויקט:</label>
                <input type="number" id="max_mentors_per_project" name="max_mentors_per_project"
                    value="<?php echo $max_mentors_per_project; ?>" min="1" max="10" required>
                <small>מספר השופטים המקסימלי שיוקצה לכל פרויקט</small>
            </div>
        </div>

        <div class="form-group">
            <label>חלוקה לפי:</label>
            <div class="radio-group">
                <label class="radio-label">
                    <input type="radio" name="prioritize_by" value="priority" <?php echo $prioritize_by === 'priority' ? 'checked' : ''; ?>>
                    עדיפות השופטים (מעדיף יותר את בחירות השופטים)
                </label>
                <label class="radio-label">
                    <input type="radio" name="prioritize_by" value="popularity" <?php echo $prioritize_by === 'popularity' ? 'checked' : ''; ?>>
                    פופולריות הפרויקטים (מבטיח יותר כיסוי של פרויקטים פופולריים)
                </label>
            </div>
        </div>

        <button type="submit" name="calculate" class="btn btn-primary">
            <i class="fas fa-calculator"></i> חשב חלוקה אופטימלית
        </button>
    </form>
</div>

<?php if ($calculate): ?>
    <div class="dashboard-card">
        <h3 class="card-title">תוצאות החלוקה האופטימלית</h3>

        <div class="stats-summary">
            <div class="stat-item">
                <span class="stat-value"><?php echo $mentors_count; ?></span>
                <span class="stat-label">שופטים</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo $projects_count; ?></span>
                <span class="stat-label">פרויקטים</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo $projects_per_mentor; ?></span>
                <span class="stat-label">פרויקטים לשופט</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo $max_mentors_per_project; ?></span>
                <span class="stat-label">שופטים לפרויקט</span>
            </div>
        </div>

        <div class="distribution-results">
            <?php if (empty($distribution)): ?>
                <div class="alert-warning">
                    <p>לא נמצאו שופטים או פרויקטים מתאימים לחישוב.</p>
                </div>
            <?php else: ?>
                <!-- תצוגת החלוקה לפי שופטים -->
                <div class="view-toggle">
                    <button class="btn btn-small active" data-view="mentors">הצג לפי שופטים</button>
                    <button class="btn btn-small" data-view="projects">הצג לפי פרויקטים</button>
                </div>

                <div id="mentors-view" class="distribution-view">
                    <?php foreach ($distribution as $mentor_email => $assigned_projects): ?>
                        <div class="distribution-item">
                            <h4><?php echo htmlspecialchars($mentor_email); ?></h4>

                            <?php if (empty($assigned_projects)): ?>
                                <p class="no-assignments">לא הוקצו פרויקטים</p>
                            <?php else: ?>
                                <ul class="assignments-list">
                                    <?php foreach ($assigned_projects as $project): ?>
                                        <li>
                                            <span class="project-priority">עדיפות <?php echo $project['priority']; ?></span>
                                            <span class="project-title"><?php echo htmlspecialchars($project['title']); ?></span>
                                            <span class="project-session">(<?php echo htmlspecialchars($project['session']); ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- תצוגת החלוקה לפי פרויקטים -->
                <div id="projects-view" class="distribution-view" style="display: none;">
                    <?php
                    $projects_distribution = [];


                    foreach ($distribution as $mentor_email => $assigned_projects) {
                        foreach ($assigned_projects as $project) {
                            $project_id = $project['project_id'];
                            if (!isset($projects_distribution[$project_id])) {
                                $projects_distribution[$project_id] = [
                                    'title' => $project['title'],
                                    'session' => $project['session'],
                                    'mentors' => []
                                ];
                            }

                            $projects_distribution[$project_id]['mentors'][] = [
                                'email' => $mentor_email,
                                'priority' => $project['priority']
                            ];
                        }
                    }


                    uksort($projects_distribution, function ($a, $b) use ($projects_distribution) {
                        return strcmp($projects_distribution[$a]['title'], $projects_distribution[$b]['title']);
                    });
                    ?>

                    <?php foreach ($projects_distribution as $project_id => $project_data): ?>
                        <div class="distribution-item">
                            <h4><?php echo htmlspecialchars($project_data['title']); ?>
                                (<?php echo htmlspecialchars($project_data['session']); ?>)</h4>

                            <?php if (empty($project_data['mentors'])): ?>
                                <p class="no-assignments">לא הוקצו שופטים</p>
                            <?php else: ?>
                                <ul class="assignments-list">
                                    <?php foreach ($project_data['mentors'] as $mentor): ?>
                                        <li>
                                            <span class="mentor-email"><?php echo htmlspecialchars($mentor['email']); ?></span>
                                            <span class="mentor-priority">(עדיפות <?php echo $mentor['priority']; ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <a href="export_distribution.php?projects=<?php echo $projects_per_mentor; ?>&mentors=<?php echo $max_mentors_per_project; ?>&by=<?php echo $prioritize_by; ?>"
                class="btn btn-secondary">
                <i class="fas fa-file-export"></i> ייצוא החלוקה לקובץ CSV
            </a>
        </div>
    </div>
<?php endif; ?>

<div class="dashboard-card">
    <h3 class="card-title">מידע על פופולריות פרויקטים</h3>

    <?php if ($calculate && !empty($project_popularity)): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>שם הפרויקט</th>
                        <th>מושב</th>
                        <th>פופולריות</th>
                        <th>גרף</th>
                    </tr>
                </thead>
                <tbody>
                    <?php

                    usort($projects, function ($a, $b) use ($project_popularity) {
                        $a_popularity = isset($project_popularity[$a['id']]) ? $project_popularity[$a['id']] : 0;
                        $b_popularity = isset($project_popularity[$b['id']]) ? $project_popularity[$b['id']] : 0;
                        return $b_popularity - $a_popularity;
                    });


                    $top_projects = array_slice($projects, 0, 15);
                    ?>

                    <?php foreach ($top_projects as $project): ?>
                        <?php
                        $popularity = isset($project_popularity[$project['id']]) ? $project_popularity[$project['id']] : 0;
                        $popularity_percent = $mentors_count > 0 ? ($popularity / $mentors_count) * 100 : 0;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($project['title']); ?></td>
                            <td><?php echo htmlspecialchars($project['session']); ?></td>
                            <td><?php echo $popularity; ?> שופטים</td>
                            <td>
                                <div class="popularity-bar">
                                    <div class="popularity-fill" style="width: <?php echo min(100, $popularity_percent); ?>%">
                                    </div>
                                    <span class="popularity-text"><?php echo round($popularity_percent); ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert-info">
            <p>חשב חלוקה כדי לראות את פופולריות הפרויקטים.</p>
        </div>
    <?php endif; ?>
</div>

<script>

    document.addEventListener('DOMContentLoaded', function () {

        const viewButtons = document.querySelectorAll('.view-toggle button');

        viewButtons.forEach(button => {
            button.addEventListener('click', function () {

                viewButtons.forEach(btn => btn.classList.remove('active'));


                this.classList.add('active');


                document.querySelectorAll('.distribution-view').forEach(view => {
                    view.style.display = 'none';
                });


                const viewToShow = this.getAttribute('data-view');
                document.getElementById(viewToShow + '-view').style.display = 'block';
            });
        });
    });
</script>

<style>
    /* סגנונות ייחודיים לדף זה */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
    }

    .form-group input[type="number"] {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 1rem;
    }

    .form-group small {
        display: block;
        margin-top: 5px;
        color: #666;
        font-size: 0.85rem;
    }

    .radio-group {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .radio-label {
        display: flex;
        align-items: center;
        font-weight: normal;
        margin-bottom: 8px;
    }

    .radio-label input[type="radio"] {
        margin-left: 10px;
    }

    .stats-summary {
        display: flex;
        justify-content: space-around;
        margin-bottom: 30px;
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
    }

    .stat-item {
        text-align: center;
    }

    .stat-value {
        display: block;
        font-size: 2rem;
        font-weight: bold;
        color: #2980b9;
        margin-bottom: 5px;
    }

    .stat-label {
        color: #7f8c8d;
        font-size: 1rem;
    }

    .distribution-results {
        margin-top: 20px;
    }

    .distribution-item {
        background-color: white;
        border: 1px solid #eee;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
    }

    .distribution-item h4 {
        margin-top: 0;
        padding-bottom: 8px;
        border-bottom: 1px solid #eee;
        color: #2c3e50;
    }

    .assignments-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .assignments-list li {
        padding: 8px 0;
        border-bottom: 1px solid #f5f5f5;
    }

    .assignments-list li:last-child {
        border-bottom: none;
    }

    .project-priority,
    .mentor-priority {
        background-color: #2980b9;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.85rem;
        margin-left: 8px;
    }

    .project-title,
    .mentor-email {
        font-weight: 600;
    }

    .project-session {
        color: #7f8c8d;
        font-size: 0.9rem;
    }

    .no-assignments {
        color: #e74c3c;
        font-style: italic;
        padding: 10px 0;
    }

    .view-toggle {
        display: flex;
        justify-content: center;
        margin-bottom: 20px;
    }

    .btn-small {
        padding: 5px 12px;
        font-size: 0.9rem;
        background-color: #f8f9fa;
        border: 1px solid #ddd;
        color: #333;
    }

    .btn-small.active {
        background-color: #2980b9;
        color: white;
        border-color: #2980b9;
    }

    .alert-info,
    .alert-warning {
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .alert-info {
        background-color: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }

    .alert-warning {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
    }

    .popularity-bar {
        position: relative;
        height: 20px;
        background-color: #ecf0f1;
        border-radius: 10px;
        overflow: hidden;
        width: 200px;
    }

    .popularity-fill {
        height: 100%;
        background-color: #3498db;
        border-radius: 10px;
    }

    .popularity-text {
        position: absolute;
        top: 0;
        right: 8px;
        line-height: 20px;
        color: white;
        font-size: 0.85rem;
        font-weight: bold;
        text-shadow: 0 0 2px rgba(0, 0, 0, 0.5);
    }

    .form-actions {
        margin-top: 20px;
        text-align: left;
    }

    .table-responsive {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    table th,
    table td {
        padding: 10px;
        border-bottom: 1px solid #e9ecef;
        text-align: right;
    }

    table th {
        background-color: #f8f9fa;
        font-weight: 600;
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }

        .stats-summary {
            flex-wrap: wrap;
        }

        .stat-item {
            width: 50%;
            margin-bottom: 15px;
        }
    }
</style>

<?php

include __DIR__ . '/../templates/footer.php';
?>