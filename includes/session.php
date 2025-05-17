<?php


function start_session_if_needed()
{
    if (session_status() == PHP_SESSION_NONE) {

        $session_name = 'secure_session';
        $secure = false;
        $httponly = true;


        session_name($session_name);


        session_start();


        if (!isset($_SESSION['last_regeneration'])) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        } else if (time() - $_SESSION['last_regeneration'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}


start_session_if_needed();

function set_success_message($message)
{
    $_SESSION['success_message'] = $message;
}

function set_error_message($message)
{
    $_SESSION['error_message'] = $message;
}

function get_success_message()
{
    $message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
    unset($_SESSION['success_message']);
    return $message;
}

function get_error_message()
{
    $message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;
    unset($_SESSION['error_message']);
    return $message;
}

function has_success_message()
{
    return isset($_SESSION['success_message']) && !empty($_SESSION['success_message']);
}

function has_error_message()
{
    return isset($_SESSION['error_message']) && !empty($_SESSION['error_message']);
}

function get_current_username()
{
    return isset($_SESSION['username']) ? $_SESSION['username'] : null;
}

function get_current_role()
{
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

function get_current_user_id()
{
    return isset($_SESSION['id']) ? $_SESSION['id'] : null;
}

function get_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}