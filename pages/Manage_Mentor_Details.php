<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['coordinator'], "גישה נדחתה. רק רכזים מורשים.");


$coordinator_id = get_current_user_id();


$mentors_sql = "SELECT u.id, u.username, md.full_name 
               FROM coordinator_mentors cm 
               JOIN users u ON cm.mentor_id = u.id
               LEFT JOIN mentor_details md ON u.id = md.mentor_id
               WHERE cm.coordinator_id = ?";
$mentors = db_fetch_all($mentors_sql, 'i', [$coordinator_id]);


$courses_sql = "SELECT id, course_name FROM courses WHERE coordinator_id = ? ORDER BY course_name ASC";
$courses = db_fetch_all($courses_sql, 'i', [$coordinator_id]);


$mentor_id = isset($_GET['mentor_id']) ? intval($_GET['mentor_id']) : 0;
$mentor_details = [
    'full_name' => "",
    'id_number' => "",
    'phone' => "",
    'email' => "",
    'gender' => "",
    'study_year' => "",
    'department' => ""
];
$assigned_courses = []; 


if ($mentor_id > 0) {
    
    $check_mentor_sql = "SELECT 1 FROM coordinator_mentors WHERE coordinator_id = ? AND mentor_id = ?";
    $mentor_belongs = db_fetch_one($check_mentor_sql, 'ii', [$coordinator_id, $mentor_id]);
    
    if (!$mentor_belongs) {
        set_error_message("אין לך הרשאה לערוך פרטים של מתגבר זה.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    
    $mentor_details_sql = "SELECT * FROM mentor_details WHERE mentor_id = ?";
    $existing_details = db_fetch_one($mentor_details_sql, 'i', [$mentor_id]);
    
    if ($existing_details) {
        $mentor_details = array_merge($mentor_details, $existing_details);
    }
    
    
    $mentor_courses_sql = "SELECT course_id FROM mentor_courses WHERE mentor_id = ?";
    $mentor_courses = db_fetch_all($mentor_courses_sql, 'i', [$mentor_id]);
    
    foreach ($mentor_courses as $course) {
        $assigned_courses[] = $course['course_id'];
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_details'])) {
    
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF'] . "?mentor_id=" . $mentor_id);
        exit();
    }
    
    $mentor_id = intval($_POST['mentor_id']);
    $mentor_details = [
        'full_name' => trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING)),
        'id_number' => trim(filter_input(INPUT_POST, 'id_number', FILTER_SANITIZE_STRING)),
        'phone' => preg_replace('/[^0-9\-\+]/', '', trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING))),
        'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
        'gender' => in_array(trim($_POST['gender']), ['Male', 'Female', 'Other']) ? trim($_POST['gender']) : '',
        'study_year' => is_numeric(trim($_POST['study_year'])) ? trim($_POST['study_year']) : '',
        'department' => trim(filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING))
    ];
    $selected_courses = isset($_POST['courses']) ? $_POST['courses'] : [];
    
    
    $check_mentor_sql = "SELECT 1 FROM coordinator_mentors WHERE coordinator_id = ? AND mentor_id = ?";
    $mentor_belongs = db_fetch_one($check_mentor_sql, 'ii', [$coordinator_id, $mentor_id]);
    
    if (!$mentor_belongs) {
        set_error_message("אין לך הרשאה לערוך פרטים של מתגבר זה.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    
    if (empty($mentor_details['full_name']) || empty($mentor_details['id_number'])) {
        set_error_message("אנא מלא את כל שדות החובה.");
        header("Location: " . $_SERVER['PHP_SELF'] . "?mentor_id=" . $mentor_id);
        exit();
    }
    
    
    db_begin_transaction();
    
    try {
        
        $check_details_sql = "SELECT mentor_id FROM mentor_details WHERE mentor_id = ?";
        $existing_details = db_fetch_one($check_details_sql, 'i', [$mentor_id]);
        
        if ($existing_details) {
            
            $update_details_sql = "UPDATE mentor_details SET 
                                  full_name = ?, 
                                  id_number = ?, 
                                  phone = ?, 
                                  email = ?, 
                                  gender = ?, 
                                  study_year = ?, 
                                  department = ? 
                                  WHERE mentor_id = ?";
            $result = db_execute($update_details_sql, 'sssssssi', [
                $mentor_details['full_name'],
                $mentor_details['id_number'],
                $mentor_details['phone'],
                $mentor_details['email'],
                $mentor_details['gender'],
                $mentor_details['study_year'],
                $mentor_details['department'],
                $mentor_id
            ]);
            
            if ($result === false) {
                throw new Exception("שגיאה בעדכון פרטי המתגבר: " . db_error());
            }
        } else {
            
            $insert_details_sql = "INSERT INTO mentor_details 
                                  (mentor_id, full_name, id_number, phone, email, gender, study_year, department) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $result = db_execute($insert_details_sql, 'isssssss', [
                $mentor_id,
                $mentor_details['full_name'],
                $mentor_details['id_number'],
                $mentor_details['phone'],
                $mentor_details['email'],
                $mentor_details['gender'],
                $mentor_details['study_year'],
                $mentor_details['department']
            ]);
            
            if ($result === false) {
                throw new Exception("שגיאה בהוספת פרטי המתגבר: " . db_error());
            }
        }
        
        
        
        $delete_courses_sql = "DELETE FROM mentor_courses WHERE mentor_id = ?";
        $result = db_execute($delete_courses_sql, 'i', [$mentor_id]);
        
        if ($result === false) {
            throw new Exception("שגיאה במחיקת קורסים קיימים: " . db_error());
        }
        
        
        if (!empty($selected_courses)) {
            $insert_course_sql = "INSERT INTO mentor_courses (mentor_id, course_id) VALUES (?, ?)";
            
            foreach ($selected_courses as $course_id) {
                $result = db_execute($insert_course_sql, 'ii', [$mentor_id, $course_id]);
                
                if ($result === false) {
                    throw new Exception("שגיאה בהוספת קורס למתגבר: " . db_error());
                }
            }
        }
        
        
        db_commit();
        set_success_message("פרטי המתגבר עודכנו בהצלחה!");
        
    } catch (Exception $e) {
        
        db_rollback();
        set_error_message($e->getMessage());
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?mentor_id=" . $mentor_id);
    exit();
}


$page_title = 'ניהול פרטי מתגבר';
$additional_css = ['coordinator.css'];


include __DIR__ . '/../templates/header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>ניהול פרטי מתגבר</h2>
    </div>
</div>

<div class="dashboard-card">
    <!-- בחירת מתגבר -->
    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="mentor-select-form">
        <div class="form-group">
            <label for="mentor_id">בחר מתגבר:</label>
            <select name="mentor_id" id="mentor_id" onchange="this.form.submit()">
                <option value="0">-- בחר מתגבר --</option>
                <?php foreach ($mentors as $mentor): 
                    $displayName = !empty($mentor['full_name']) ? $mentor['full_name'] : $mentor['username'];
                ?>
                    <option value="<?php echo $mentor['id']; ?>" <?php echo ($mentor['id'] == $mentor_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($displayName); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
    
    <?php if ($mentor_id > 0): ?>
        <!-- טופס פרטי מתגבר -->
        <h3 class="card-title">עריכת פרטי מתגבר</h3>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="mentor-details-form">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <input type="hidden" name="mentor_id" value="<?php echo $mentor_id; ?>">
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="full_name">שם מלא:</label>
                    <input type="text" name="full_name" id="full_name" 
                           value="<?php echo htmlspecialchars($mentor_details['full_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="id_number">מספר תעודת זהות:</label>
                    <input type="text" name="id_number" id="id_number" 
                           value="<?php echo htmlspecialchars($mentor_details['id_number']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">מספר טלפון:</label>
                    <input type="text" name="phone" id="phone" 
                           value="<?php echo htmlspecialchars($mentor_details['phone']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">דואר אלקטרוני:</label>
                    <input type="email" name="email" id="email" 
                           value="<?php echo htmlspecialchars($mentor_details['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="gender">מין:</label>
                    <select name="gender" id="gender">
                        <option value="Male" <?php echo ($mentor_details['gender'] == 'Male') ? 'selected' : ''; ?>>זכר</option>
                        <option value="Female" <?php echo ($mentor_details['gender'] == 'Female') ? 'selected' : ''; ?>>נקבה</option>
                        <option value="Other" <?php echo ($mentor_details['gender'] == 'Other') ? 'selected' : ''; ?>>אחר</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="study_year">שנת לימודים:</label>
                    <input type="text" name="study_year" id="study_year" 
                           value="<?php echo htmlspecialchars($mentor_details['study_year']); ?>" required>
                </div>
                
                <div class="form-group full-width">
                    <label for="department">מחלקה:</label>
                    <input type="text" name="department" id="department" 
                           value="<?php echo htmlspecialchars($mentor_details['department']); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>קורסים שהמתגבר יכול ללמד:</label>
                <div class="checkbox-group">
                    <?php foreach ($courses as $course): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="courses[]" value="<?php echo $course['id']; ?>" 
                                   <?php echo in_array($course['id'], $assigned_courses) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($course['course_name']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <button type="submit" name="save_details" class="btn btn-primary">שמור פרטים</button>
        </form>
    <?php elseif (!empty($mentors)): ?>
        <div class="alert-info">
            <p>אנא בחר מתגבר מהרשימה למעלה כדי לערוך את פרטיו.</p>
        </div>
    <?php else: ?>
        <div class="alert-warning">
            <p>לא נמצאו מתגברים המשויכים אליך. <a href="coordinator_promote_guest.php">לחץ כאן</a> להוספת מתגברים.</p>
        </div>
    <?php endif; ?>
</div>

<?php

include __DIR__ . '/../templates/footer.php';
?>