<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


$display_mode = isset($_GET['mode']) ? $_GET['mode'] : 'email_form';
$error_message = '';
$success_message = '';
$mentor_email = '';
$mentor_data = null;
$available_projects = [];
$selected_projects = [];


if (isset($_POST['email']) && !empty($_POST['email'])) {
    $mentor_email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    if (!$mentor_email) {
        $error_message = "כתובת האימייל אינה תקינה";
        $display_mode = 'email_form';
    } else {

        $sql = "SELECT * FROM authorized_mentors WHERE email = ?";
        $mentor_data = db_fetch_one($sql, 's', [$mentor_email]);

        if (!$mentor_data) {
            $error_message = "האימייל שהזנת אינו רשום במערכת כשופט מורשה";
            $display_mode = 'email_form';
        } else {

            $display_mode = 'project_list';


            loadAvailableProjects($mentor_data);


            loadSelectedProjects($mentor_email);
        }
    }
}


if (isset($_POST['update_priorities']) && !empty($_POST['priorities']) && !empty($_POST['mentor_email'])) {
    $mentor_email = filter_input(INPUT_POST, 'mentor_email', FILTER_VALIDATE_EMAIL);
    $priorities = $_POST['priorities'];

    if (!$mentor_email) {
        $error_message = "כתובת האימייל אינה תקינה";
        $display_mode = 'email_form';
    } else {

        $sql = "SELECT * FROM authorized_mentors WHERE email = ?";
        $mentor_data = db_fetch_one($sql, 's', [$mentor_email]);

        if (!$mentor_data) {
            $error_message = "האימייל שהזנת אינו רשום במערכת כשופט מורשה";
            $display_mode = 'email_form';
        } else {
            db_begin_transaction();

            try {

                $delete_sql = "DELETE FROM project_selections WHERE mentor_email = ?";
                $result = db_execute($delete_sql, 's', [$mentor_email]);

                if ($result === false) {
                    throw new Exception("שגיאה במחיקת הבחירות הקודמות");
                }


                foreach ($priorities as $priority => $project_id) {
                    if (empty($project_id))
                        continue;

                    $insert_sql = "INSERT INTO project_selections (project_id, mentor_email, priority) 
                                  VALUES (?, ?, ?)";
                    $result = db_execute($insert_sql, 'isi', [$project_id, $mentor_email, $priority]);

                    if ($result === false) {
                        throw new Exception("שגיאה בשמירת בחירת הפרויקט #" . $project_id);
                    }
                }


                $update_sql = "UPDATE authorized_mentors SET 
                            selection_count = ?,
                            last_selection_date = NOW()
                            WHERE email = ?";
                $selection_count = count(array_filter($priorities));
                $result = db_execute($update_sql, 'is', [$selection_count, $mentor_email]);

                if ($result === false) {
                    throw new Exception("שגיאה בעדכון נתוני השופט");
                }

                db_commit();
                $success_message = "העדיפויות נשמרו בהצלחה!";


                loadAvailableProjects($mentor_data);
                loadSelectedProjects($mentor_email);
                $display_mode = 'project_list';

            } catch (Exception $e) {
                db_rollback();
                $error_message = $e->getMessage();
                $display_mode = 'project_list';
                loadAvailableProjects($mentor_data);
                loadSelectedProjects($mentor_email);
            }
        }
    }
}


function loadAvailableProjects($mentor_data)
{
    global $available_projects;


    $session_condition = "";
    $params = [];
    $types = "";

    if (!empty($mentor_data['session_restriction'])) {
        $session_condition = " AND p.session = ?";
        $params[] = $mentor_data['session_restriction'];
        $types .= "s";
    }


    $sql = "SELECT p.* FROM projects p
           WHERE p.is_active = 1 
           AND p.coordinator_id = ?
           $session_condition
           ORDER BY p.session, p.title";


    array_unshift($params, $mentor_data['coordinator_id']);
    $types = "i" . $types;

    $available_projects = db_fetch_all($sql, $types, $params);
}


function loadSelectedProjects($mentor_email)
{
    global $selected_projects;

    $sql = "SELECT ps.project_id, ps.priority, p.title, p.session
            FROM project_selections ps
            JOIN projects p ON ps.project_id = p.id
            WHERE ps.mentor_email = ?
            ORDER BY ps.priority ASC";

    $result = db_fetch_all($sql, 's', [$mentor_email]);

    $selected_projects = [];
    if ($result) {
        foreach ($result as $row) {
            $selected_projects[$row['priority']] = [
                'project_id' => $row['project_id'],
                'title' => $row['title'],
                'session' => $row['session']
            ];
        }
    }
}


$page_title = 'בחירת פרויקטים לשופטים';
$additional_css = ['main.css'];
$skip_sidebar = true;


include __DIR__ . '/templates/header.php';
?>

<!-- סגנונות מותאמים אישית לדף -->
<style>
    :root {
        --primary-color: #1976d2;
        --primary-light: #4791db;
        --primary-dark: #115293;
        --secondary-color: #673ab7;
        --light-bg: #f5f7fa;
        --dark-text: #333;
        --light-text: #fff;
        --card-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        --transition-speed: 0.3s;
    }

    body,
    html {
        direction: rtl;
        text-align: right;
        font-family: 'Rubik', Arial, sans-serif;
    }

    .project-selection-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 0 1rem;
    }

    .login-card {
        background: var(--light-bg);
        border-radius: 16px;
        padding: 2rem;
        box-shadow: var(--card-shadow);
        position: relative;
        overflow: hidden;
    }

    .login-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 100%;
        height: 8px;
        background: linear-gradient(to left, var(--primary-color), var(--secondary-color));
    }

    .login-card h2 {
        color: var(--primary-dark);
        text-align: center;
        margin-bottom: 1.5rem;
        font-size: 2rem;
        position: relative;
        padding-bottom: 10px;
        font-weight: 700;
    }

    .login-card h2::after {
        content: '';
        position: absolute;
        bottom: 0;
        right: 50%;
        transform: translateX(50%);
        width: 80px;
        height: 4px;
        background-color: var(--secondary-color);
        border-radius: 2px;
    }

    /* עיצוב טופס אימייל */
    .project-email-form {
        max-width: 500px;
        margin: 0 auto;
        padding: 1.5rem;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--dark-text);
    }

    .form-group input[type="email"] {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #ddd;
        border-radius: 8px;
        font-size: 1rem;
        transition: border-color var(--transition-speed);
        text-align: right;
    }

    .form-group input[type="email"]:focus {
        border-color: var(--primary-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb), 0.2);
    }

    /* כפתורים */
    .btn {
        display: inline-block;
        padding: 12px 25px;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        text-align: center;
        cursor: pointer;
        transition: all var(--transition-speed);
        text-decoration: none;
    }

    .btn-primary {
        background-color: var(--primary-color);
        color: var(--light-text);
    }

    .btn-primary:hover {
        background-color: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .btn-secondary {
        background-color: #f0f0f0;
        color: var(--dark-text);
    }

    .btn-secondary:hover {
        background-color: #e0e0e0;
    }

    /* רשימת פרויקטים */
    .projects-container {
        display: flex;
        gap: 2rem;
        margin-top: 2rem;
    }

    .available-projects {
        flex: 1;
    }

    .selected-projects {
        flex: 1;
    }

    .project-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .project-item {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        transition: transform var(--transition-speed), box-shadow var(--transition-speed);
        display: flex;
        flex-direction: column;
        height: 100%;
        position: relative;
        border: 1px solid #eee;
        cursor: pointer;
    }

    .project-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .project-item.selected {
        border: 2px solid var(--primary-color);
    }

    .project-item h3 {
        color: var(--primary-dark);
        padding: 1.25rem 1.25rem 0.5rem;
        margin: 0;
        font-size: 1.3rem;
        border-bottom: 2px solid #f0f0f0;
    }

    .project-session {
        background-color: var(--primary-light);
        color: white;
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        margin: 0 1.25rem 0.75rem;
    }

    .project-details {
        padding: 0.75rem 1.25rem 1rem;
        flex-grow: 1;
        color: #555;
        font-size: 0.95rem;
        line-height: 1.5;
    }

    .priority-list {
        list-style-type: none;
        padding: 0;
        margin: 0;
    }

    .priority-item {
        background: white;
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        position: relative;
        display: flex;
        align-items: center;
    }

    .priority-number {
        background: var(--primary-color);
        color: white;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        margin-left: 15px;
        font-weight: bold;
    }

    .priority-title {
        flex-grow: 1;
    }

    .priority-session {
        color: #666;
        font-size: 0.9rem;
        margin-left: 10px;
    }

    .priority-remove {
        color: #e74c3c;
        cursor: pointer;
        font-size: 1.2rem;
        transition: transform 0.2s;
    }

    .priority-remove:hover {
        transform: scale(1.2);
    }

    .project-search {
        margin-bottom: 20px;
        width: 100%;
        padding: 10px;
        border-radius: 8px;
        border: 2px solid #ddd;
        font-size: 1rem;
    }

    .priority-instructions {
        background-color: #e3f2fd;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        border-right: 4px solid var(--primary-color);
    }

    .priority-instructions h3 {
        margin-top: 0;
        color: var(--primary-dark);
    }

    .save-button {
        margin-top: 20px;
        width: 100%;
        padding: 12px;
        background-color: var(--primary-color);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 1.1rem;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    .save-button:hover {
        background-color: var(--primary-dark);
    }

    .recommendations-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        background: var(--secondary-color);
        color: white;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: bold;
        z-index: 2;
    }

    /* התאמה לתצוגה במסכים קטנים */
    @media (max-width: 992px) {
        .projects-container {
            flex-direction: column;
        }
    }

    /* הוספת תמיכה בפונטים עבריים */
    @font-face {
        font-family: 'Rubik';
        src: url('https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700&display=swap');
        font-display: swap;
    }

    .empty-slot {
        display: flex;
        height: 100px;
        border: 2px dashed #ccc;
        border-radius: 8px;
        justify-content: center;
        align-items: center;
        margin-bottom: 10px;
        color: #777;
        font-style: italic;
    }

    .section-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .section-title h3 {
        margin: 0;
    }

    .section-count {
        background: var(--primary-light);
        color: white;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.9rem;
    }

    .welcome-message {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        border-right: 4px solid var(--primary-color);
    }

    .message {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        text-align: center;
        font-weight: 500;
    }

    .message.error {
        background-color: #ffebee;
        color: #c62828;
        border-right: 5px solid #c62828;
        border-left: none;
    }

    .message.success {
        background-color: #e8f5e9;
        color: #2e7d32;
        border-right: 5px solid #2e7d32;
        border-left: none;
    }

    /* כפתור להוספת עדיפות חדשה */
    .add-priority-btn {
        display: flex;
        justify-content: center;
        align-items: center;
        width: 100%;
        padding: 15px;
        background-color: #f5f5f5;
        border: 2px dashed #ccc;
        border-radius: 8px;
        margin-top: 15px;
        color: #666;
        cursor: pointer;
        transition: all 0.3s;
    }

    .add-priority-btn:hover {
        background-color: #e8f5e9;
        border-color: #4caf50;
        color: #4caf50;
    }

    .add-priority-btn i {
        margin-left: 10px;
        font-size: 1.2rem;
    }
</style>

<div class="project-selection-container">
    <div class="login-card">
        <h2>בחירת פרויקטים לשופטים</h2>

        <?php if (!empty($error_message)): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($display_mode == 'email_form'): ?>
            <!-- טופס הזנת אימייל -->
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" class="project-email-form">
                <div class="form-group">
                    <label for="email">כתובת האימייל שלך:</label>
                    <input type="email" id="email" name="email" required
                        value="<?php echo htmlspecialchars($mentor_email); ?>" placeholder="הזן את כתובת האימייל שלך"
                        autocomplete="email">
                </div>
                <button type="submit" class="btn btn-primary">המשך לבחירת פרויקטים</button>
            </form>

        <?php elseif ($display_mode == 'project_list'): ?>
            <!-- רשימת פרויקטים לבחירה ולתעדוף -->
            <div class="welcome-message">
                <h3>שלום, <?php echo htmlspecialchars($mentor_email); ?></h3>
                <p>כאן תוכל/י לבחור פרויקטים ולסדר אותם לפי סדר העדיפות שלך.</p>
                <p><strong>מומלץ לבחור לפחות 10 פרויקטים</strong> כדי להגדיל את הסיכוי לקבל פרויקטים שמעניינים אותך.</p>
            </div>

            <div class="projects-container">
                <div class="selected-projects">
                    <div class="section-title">
                        <h3>רשימת העדיפויות שלך</h3>
                        <span class="section-count"><?php echo count($selected_projects); ?> פרויקטים נבחרו</span>
                    </div>

                    <div class="priority-instructions">
                        <h3>הוראות:</h3>
                        <p>1. לחץ על פרויקט מהרשימה בצד שמאל כדי להוסיף אותו לעדיפויות.</p>
                        <p>2. סדר את הפרויקטים לפי סדר העדיפויות שלך (1 = הכי גבוה).</p>
                        <p>3. לחץ על X כדי להסיר פרויקט מהרשימה.</p>
                        <p>4. לאחר שסיימת, לחץ על "שמור עדיפויות" בתחתית הדף.</p>
                    </div>

                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" id="priorities-form">
                        <input type="hidden" name="mentor_email" value="<?php echo htmlspecialchars($mentor_email); ?>">

                        <div class="priority-list-container">
                            <ul class="priority-list" id="priority-list">
                                <?php

                                $max_priority = count($selected_projects);
                                $min_display = max(10, $max_priority);

                                for ($i = 1; $i <= $min_display; $i++) {
                                    if (isset($selected_projects[$i])) {
                                        $project = $selected_projects[$i];
                                        echo '<li class="priority-item" data-project-id="' . $project['project_id'] . '">';
                                        echo '<span class="priority-number">' . $i . '</span>';
                                        echo '<span class="priority-title">' . htmlspecialchars($project['title']) . '</span>';
                                        echo '<span class="priority-session">' . htmlspecialchars($project['session']) . '</span>';
                                        echo '<span class="priority-remove" onclick="removeProject(this)">×</span>';
                                        echo '<input type="hidden" name="priorities[' . $i . ']" value="' . $project['project_id'] . '">';
                                        echo '</li>';
                                    } else {
                                        echo '<li class="empty-slot" data-priority="' . $i . '">';
                                        echo '<span>לחץ על פרויקט כדי להוסיף לעדיפות ' . $i . '</span>';
                                        echo '<input type="hidden" name="priorities[' . $i . ']" value="">';
                                        echo '</li>';
                                    }
                                }
                                ?>
                            </ul>

                            <!-- כפתור להוספת עדיפות נוספת -->
                            <div class="add-priority-btn" onclick="addMorePriorities()">
                                <i class="fas fa-plus-circle"></i> הוסף עדיפויות נוספות
                            </div>
                        </div>

                        <button type="submit" name="update_priorities" class="save-button">
                            שמור עדיפויות
                        </button>
                    </form>
                </div>

                <div class="available-projects">
                    <div class="section-title">
                        <h3>פרויקטים זמינים</h3>
                        <span class="section-count"><?php echo count($available_projects); ?> פרויקטים</span>
                    </div>

                    <input type="text" class="project-search" id="project-search" placeholder="חיפוש פרויקטים...">

                    <div class="project-grid" id="project-grid">
                        <?php foreach ($available_projects as $index => $project):

                            $selected_priority = null;
                            foreach ($selected_projects as $priority => $selected) {
                                if ($selected['project_id'] == $project['id']) {
                                    $selected_priority = $priority;
                                    break;
                                }
                            }
                            ?>
                            <div class="project-item <?php echo $selected_priority ? 'selected' : ''; ?>"
                                data-id="<?php echo $project['id']; ?>"
                                data-title="<?php echo htmlspecialchars($project['title']); ?>"
                                data-session="<?php echo htmlspecialchars($project['session']); ?>"
                                onclick="selectProject(this)">

                                <?php if ($selected_priority): ?>
                                    <div class="recommendations-badge">עדיפות <?php echo $selected_priority; ?></div>
                                <?php endif; ?>

                                <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                                <p class="project-session">מושב: <?php echo htmlspecialchars($project['session']); ?></p>
                                <div class="project-details">
                                    <?php echo nl2br(htmlspecialchars($project['details'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript לטיפול בבחירת פרויקטים ותעדוף -->
<script>
    document.addEventListener('DOMContentLoaded', function () {

        const emailInput = document.getElementById('email');
        if (emailInput) {
            emailInput.focus();
        }


        const searchInput = document.getElementById('project-search');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const searchTerm = this.value.toLowerCase();
                const projects = document.querySelectorAll('#project-grid .project-item');

                projects.forEach(project => {
                    const title = project.querySelector('h3').textContent.toLowerCase();
                    const details = project.querySelector('.project-details').textContent.toLowerCase();
                    const session = project.querySelector('.project-session').textContent.toLowerCase();

                    if (title.includes(searchTerm) || details.includes(searchTerm) || session.includes(searchTerm)) {
                        project.style.display = '';
                    } else {
                        project.style.display = 'none';
                    }
                });
            });
        }
    });


    function selectProject(element) {
        const projectId = element.getAttribute('data-id');
        const projectTitle = element.getAttribute('data-title');
        const projectSession = element.getAttribute('data-session');


        const priorityInputs = document.querySelectorAll('input[name^="priorities"]');
        let alreadySelected = false;
        let currentPriority = null;

        priorityInputs.forEach(input => {
            if (input.value === projectId) {
                alreadySelected = true;
                currentPriority = input.name.match(/\[(\d+)\]/)[1];
            }
        });

        if (alreadySelected) {

            const priorityItem = document.querySelector(`#priority-list li input[value="${projectId}"]`).parentNode;
            removeProject(priorityItem.querySelector('.priority-remove'));
            element.classList.remove('selected');
        } else {

            const emptySlot = document.querySelector('.empty-slot');
            if (emptySlot) {
                const priority = emptySlot.getAttribute('data-priority');
                const priorityItem = document.createElement('li');
                priorityItem.className = 'priority-item';
                priorityItem.setAttribute('data-project-id', projectId);

                priorityItem.innerHTML = `
                <span class="priority-number">${priority}</span>
                <span class="priority-title">${projectTitle}</span>
                <span class="priority-session">${projectSession}</span>
                <span class="priority-remove" onclick="removeProject(this)">×</span>
                <input type="hidden" name="priorities[${priority}]" value="${projectId}">
            `;

                emptySlot.parentNode.replaceChild(priorityItem, emptySlot);
                element.classList.add('selected');


                let badge = document.createElement('div');
                badge.className = 'recommendations-badge';
                badge.textContent = `עדיפות ${priority}`;
                element.appendChild(badge);
            } else {

                const priorityList = document.getElementById('priority-list');
                const lastItem = priorityList.lastElementChild;
                const newPriority = parseInt(lastItem.querySelector('.priority-number').textContent) + 1;

                const priorityItem = document.createElement('li');
                priorityItem.className = 'priority-item';
                priorityItem.setAttribute('data-project-id', projectId);

                priorityItem.innerHTML = `
                <span class="priority-number">${newPriority}</span>
                <span class="priority-title">${projectTitle}</span>
                <span class="priority-session">${projectSession}</span>
                <span class="priority-remove" onclick="removeProject(this)">×</span>
                <input type="hidden" name="priorities[${newPriority}]" value="${projectId}">
            `;

                priorityList.appendChild(priorityItem);
                element.classList.add('selected');


                let badge = document.createElement('div');
                badge.className = 'recommendations-badge';
                badge.textContent = `עדיפות ${newPriority}`;
                element.appendChild(badge);
            }
        }


        updateSelectedCount();
    }


    function removeProject(element) {
        const priorityItem = element.parentNode;
        const projectId = priorityItem.getAttribute('data-project-id');
        const priority = priorityItem.querySelector('.priority-number').textContent;


        const emptySlot = document.createElement('li');
        emptySlot.className = 'empty-slot';
        emptySlot.setAttribute('data-priority', priority);
        emptySlot.innerHTML = `
        <span>לחץ על פרויקט כדי להוסיף לעדיפות ${priority}</span>
        <input type="hidden" name="priorities[${priority}]" value="">
    `;

        priorityItem.parentNode.replaceChild(emptySlot, priorityItem);


        const projectElement = document.querySelector(`.project-item[data-id="${projectId}"]`);
        if (projectElement) {
            projectElement.classList.remove('selected');
            const badge = projectElement.querySelector('.recommendations-badge');
            if (badge) {
                badge.remove();
            }
        }


        updateSelectedCount();
    }


    function addMorePriorities() {
        const priorityList = document.getElementById('priority-list');
        const lastItem = priorityList.lastElementChild;
        const lastPriority = parseInt(lastItem.querySelector('.priority-number') ?
            lastItem.querySelector('.priority-number').textContent :
            lastItem.getAttribute('data-priority'));


        for (let i = 1; i <= 5; i++) {
            const newPriority = lastPriority + i;

            const emptySlot = document.createElement('li');
            emptySlot.className = 'empty-slot';
            emptySlot.setAttribute('data-priority', newPriority);
            emptySlot.innerHTML = `
            <span>לחץ על פרויקט כדי להוסיף לעדיפות ${newPriority}</span>
            <input type="hidden" name="priorities[${newPriority}]" value="">
        `;

            priorityList.appendChild(emptySlot);
        }
    }


    function updateSelectedCount() {
        const selectedCount = document.querySelectorAll('.priority-item').length;
        const countElement = document.querySelector('.section-count');
        if (countElement) {
            countElement.textContent = `${selectedCount} פרויקטים נבחרו`;
        }
    }
</script>

<?php

include __DIR__ . '/../templates/footer.php';
?>