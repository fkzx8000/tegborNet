<?php

require_once __DIR__ . '/db_functions.php';

function is_rate_limited($endpoint, $max_requests = 60, $time_window = 60)
{
    $conn = get_database_connection();
    $ip_address = $_SERVER['REMOTE_ADDR'];


    $window_start = date('Y-m-d H:i:s', time() - $time_window);


    $log_sql = "INSERT INTO api_requests (ip_address, endpoint, request_time) VALUES (?, ?, NOW())";
    db_execute($log_sql, 'ss', [$ip_address, $endpoint]);


    $count_sql = "SELECT COUNT(*) as request_count 
                 FROM api_requests 
                 WHERE ip_address = ? 
                 AND endpoint = ? 
                 AND request_time > ?";

    $result = db_fetch_one($count_sql, 'sss', [$ip_address, $endpoint, $window_start]);


    $cleanup_time = date('Y-m-d H:i:s', time() - ($time_window * 5));
    $cleanup_sql = "DELETE FROM api_requests WHERE request_time < ?";
    db_execute($cleanup_sql, 's', [$cleanup_time]);


    return ($result['request_count'] > $max_requests);
}

function handle_rate_limit()
{
    header('HTTP/1.1 429 Too Many Requests');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'יותר מדי בקשות. אנא נסה שוב מאוחר יותר.',
        'retry_after' => 60
    ]);
    exit();
}
?>