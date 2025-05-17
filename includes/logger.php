<?php

require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/session.php';

function log_activity($action, $details = null)
{

    $user_id = get_current_user_id();
    $username = get_current_username();


    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;


    if (is_array($details) || is_object($details)) {
        $details = json_encode($details, JSON_UNESCAPED_UNICODE);
    }


    $sql = "INSERT INTO system_logs (user_id, username, action, details, ip_address, user_agent, log_time) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";


    if ($user_id === null) {
        db_execute($sql, 'issss', [null, $username, $action, $details, $ip_address, $user_agent]);
    } else {
        db_execute($sql, 'issss', [$user_id, $username, $action, $details, $ip_address, $user_agent]);
    }
}
?>