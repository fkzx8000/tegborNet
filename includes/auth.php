<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';

function is_logged_in()
{
    return isset($_SESSION['username']) && !empty($_SESSION['username']);
}


function is_admin()
{
    return is_logged_in() && $_SESSION['role'] === 'admin';
}


function is_coordinator()
{
    return is_logged_in() && $_SESSION['role'] === 'coordinator';
}

function is_mentor()
{
    return is_logged_in() && $_SESSION['role'] === 'mentor';
}


function has_permission($allowed_roles)
{
    if (!is_logged_in()) {
        return false;
    }


    return in_array($_SESSION['role'], $allowed_roles);
}


function require_permission($allowed_roles, $error_message = "אין לך הרשאה לצפות בדף זה")
{
    if (!has_permission($allowed_roles)) {
        $_SESSION['error_message'] = $error_message;
        header("Location: " . get_site_url());
        exit();
    }
}


function login_user($username, $password)
{
    $conn = get_database_connection();


    if (empty($username) || empty($password)) {
        $_SESSION['error_message'] = "אנא הזן שם משתמש וסיסמה";
        return false;
    }


    $sql = "SELECT u.id, u.password, r.role_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.username = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['error_message'] = "שגיאת מערכת, אנא נסה שוב מאוחר יותר";
        return false;
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($user_id, $hashed_password, $role_name);

    if ($stmt->fetch() && password_verify($password, $hashed_password)) {

        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role_name;
        $_SESSION['id'] = $user_id;

        $stmt->close();
        return true;
    } else {
        $_SESSION['error_message'] = "שם משתמש או סיסמה שגויים";
        $stmt->close();
        return false;
    }
}


function register_user($username, $password)
{
    $conn = get_database_connection();


    $checkStmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        $_SESSION['error_message'] = "שם המשתמש כבר קיים במערכת";
        $checkStmt->close();
        return false;
    }
    $checkStmt->close();


    $roleStmt = $conn->prepare("SELECT id FROM roles WHERE role_name = 'guest'");
    $roleStmt->execute();
    $roleStmt->bind_result($role_id);

    if (!$roleStmt->fetch()) {
        $_SESSION['error_message'] = "שגיאת מערכת בתהליך הרישום";
        $roleStmt->close();
        return false;
    }
    $roleStmt->close();


    $hashed_password = password_hash($password, PASSWORD_BCRYPT);


    $stmt = $conn->prepare("INSERT INTO users (username, password, role_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $username, $hashed_password, $role_id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "הרישום בוצע בהצלחה!";
        $stmt->close();
        return true;
    } else {
        $_SESSION['error_message'] = "שגיאה בתהליך הרישום: " . $conn->error;
        $stmt->close();
        return false;
    }
}


function logout_user()
{
    session_start();
    session_unset();
    session_destroy();


    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 86400, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}


function get_site_url()
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";


    $domain = filter_var($_SERVER['HTTP_HOST'], FILTER_SANITIZE_STRING);


    $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');


    $base_path = preg_replace('/(\/pages|\/api|\/includes)$/', '', $path);

    return $protocol . $domain . $base_path;
}