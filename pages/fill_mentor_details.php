<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['mentor'], "גישה נדחתה. מתגברים בלבד.");


$mentor_id = get_current_user_id();


$mentor_details = [
    'full_name' => '',
    'id_number' => '',
    'phone' => '',
    'email' => '',
    'gender' => '',
    'study_year' => '',
    'department' => ''
];


$sql = "SELECT full_name, id_number, phone, email, gender, study_year, department 
        FROM mentor_details 
        WHERE mentor_id = ?";

$existing_details = db_fetch_one($sql, 'i', [$mentor_id]);

if ($existing_details) {
    $mentor_details = array_merge($mentor_details, $existing_details);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_details'])) {

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message('שגיאת אבטחה. אנא רענן את הדף ונסה שוב.');
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    $mentor_details = [
        'full_name' => trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING)),
        'id_number' => trim(filter_input(INPUT_POST, 'id_number', FILTER_SANITIZE_STRING)),
        'phone' => trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING)),
        'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
        'gender' => trim(filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING)),
        'study_year' => trim(filter_input(INPUT_POST, 'study_year', FILTER_SANITIZE_STRING)),
        'department' => trim(filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING))
    ];


    if (empty($mentor_details['full_name']) || empty($mentor_details['id_number'])) {
        set_error_message('אנא מלא את כל שדות החובה (שם מלא ומספר זהות).');
    } else {

        $check_sql = "SELECT mentor_id FROM mentor_details WHERE mentor_id = ?";
        $existing_record = db_fetch_one($check_sql, 'i', [$mentor_id]);


        if ($existing_record) {

            $update_sql = "UPDATE mentor_details 
                         SET full_name = ?, id_number = ?, phone = ?, email = ?, 
                             gender = ?, study_year = ?, department = ? 
                         WHERE mentor_id = ?";
            $result = db_execute($update_sql, 'sssssssi', [
                $mentor_details['full_name'],
                $mentor_details['id_number'],
                $mentor_details['phone'],
                $mentor_details['email'],
                $mentor_details['gender'],
                $mentor_details['study_year'],
                $mentor_details['department'],
                $mentor_id
            ]);
        } else {

            $insert_sql = "INSERT INTO mentor_details 
                         (mentor_id, full_name, id_number, phone, email, gender, study_year, department) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $result = db_execute($insert_sql, 'isssssss', [
                $mentor_id,
                $mentor_details['full_name'],
                $mentor_details['id_number'],
                $mentor_details['phone'],
                $mentor_details['email'],
                $mentor_details['gender'],
                $mentor_details['study_year'],
                $mentor_details['department']
            ]);
        }

        if ($result !== false) {
            set_success_message('הפרטים עודכנו בהצלחה!');
        } else {
            set_error_message('שגיאה בשמירת הפרטים. אנא נסה שוב מאוחר יותר.');
        }


        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}


$page_title = 'עדכון פרטי מתגבר';
$additional_css = ['mentor_profile.css'];


include __DIR__ . '/../templates/header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>עדכון פרטים אישיים</h2>
    </div>
</div>

<div class="dashboard-card">
    <h3 class="card-title">פרטים אישיים</h3>

    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="mentor-profile-form">
        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">

        <div class="form-grid">
            <div class="form-group">
                <label class="required" for="full_name">שם מלא:</label>
                <input type="text" name="full_name" id="full_name"
                    value="<?php echo htmlspecialchars($mentor_details['full_name']); ?>" placeholder="יוחנן כהן"
                    required>
            </div>

            <div class="form-group">
                <label class="required" for="id_number">מספר זהות:</label>
                <input type="tel" name="id_number" id="id_number" inputmode="tel"
                    value="<?php echo htmlspecialchars($mentor_details['id_number']); ?>" placeholder="123456789"
                    required>
            </div>

            <div class="form-group">
                <label for="phone">מספר טלפון:</label>
                <input type="tel" name="phone" id="phone" inputmode="tel"
                    value="<?php echo htmlspecialchars($mentor_details['phone']); ?>" placeholder="050-123-4567">
            </div>

            <div class="form-group">
                <label for="email">דוא״ל:</label>
                <input type="email" name="email" id="email" inputmode="email"
                    value="<?php echo htmlspecialchars($mentor_details['email']); ?>" placeholder="example@example.com">
            </div>

            <div class="form-group">
                <label for="gender">מגדר:</label>
                <select name="gender" id="gender">
                    <option value="">בחר מגדר</option>
                    <?php
                    $genders = ['Male' => '👨 זכר', 'Female' => '👩 נקבה', 'Other' => '⚧ אחר'];
                    foreach ($genders as $value => $text) {
                        $selected = ($mentor_details['gender'] == $value) ? 'selected' : '';
                        echo "<option value='$value' $selected>$text</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="study_year">שנת לימודים:</label>
                <input type="number" name="study_year" id="study_year" inputmode="numeric"
                    value="<?php echo htmlspecialchars($mentor_details['study_year']); ?>" placeholder="3" min="1"
                    max="10">
            </div>

            <div class="form-group full-width">
                <label for="department">מחלקה:</label>
                <input type="text" name="department" id="department"
                    value="<?php echo htmlspecialchars($mentor_details['department']); ?>" placeholder="מדעי המחשב">
            </div>
        </div>

        <button type="submit" name="save_details" class="btn btn-primary">💾 שמור פרופיל</button>
    </form>
</div>

<?php

include __DIR__ . '/../templates/footer.php';
?>