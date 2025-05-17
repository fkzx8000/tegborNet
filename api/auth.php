<?php

require_once __DIR__ . '/../includes/auth.php';


header('Content-Type: application/json; charset=utf-8');


$action = isset($_GET['action']) ? $_GET['action'] : '';


$response = [
    'success' => false,
    'message' => '',
    'redirect' => ''
];


switch ($action) {
    case 'login':

        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';


        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            $response['message'] = "שגיאת אבטחה. אנא רענן את הדף ונסה שוב.";
            break;
        }


        if (login_user($username, $password)) {
            $response['success'] = true;
            $response['message'] = "ההתחברות בוצעה בהצלחה!";
            $response['redirect'] = get_site_url() . '/index.php';
        } else {
            $response['message'] = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : "שגיאה בהתחברות";
            unset($_SESSION['error_message']);
        }
        break;

    case 'register':

        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';


        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            $response['message'] = "שגיאת אבטחה. אנא רענן את הדף ונסה שוב.";
            break;
        }


        if (empty($username) || strlen($username) < 3) {
            $response['message'] = "שם המשתמש חייב להכיל לפחות 3 תווים";
            break;
        }

        if (empty($password) || strlen($password) < 8) {
            $response['message'] = "הסיסמה חייבת להכיל לפחות 8 תווים";
            break;
        }


        if (register_user($username, $password)) {
            $response['success'] = true;
            $response['message'] = "הרישום בוצע בהצלחה! אנא התחבר למערכת.";
            $response['redirect'] = get_site_url() . '/pages/login.php';
        } else {
            $response['message'] = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : "שגיאה בתהליך הרישום";
            unset($_SESSION['error_message']);
        }
        break;

    case 'logout':

        logout_user();


        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

            $response['success'] = true;
            $response['message'] = "ההתנתקות בוצעה בהצלחה";
            $response['redirect'] = get_site_url() . '/index.php';
        } else {

            header("Location: " . get_site_url() . '/index.php');
            exit();
        }
        break;

    default:
        $response['message'] = "פעולה לא חוקית";
        break;
}


echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();