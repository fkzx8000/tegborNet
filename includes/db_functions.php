<?php

require_once __DIR__ . '/../config/database.php';

function db_fetch_all($sql, $types = null, $params = [])
{
    $conn = get_database_connection();
    $result = [];

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }


    if ($types !== null && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $query_result = $stmt->get_result();


    if (!$query_result) {
        $stmt->close();
        return [];
    }


    while ($row = $query_result->fetch_assoc()) {
        $result[] = $row;
    }

    $stmt->close();
    return $result;
}

function db_fetch_one($sql, $types = null, $params = [])
{
    $results = db_fetch_all($sql, $types, $params);

    if ($results && count($results) > 0) {
        return $results[0];
    }

    return null;
}

function db_execute($sql, $types = null, $params = [])
{
    $conn = get_database_connection();

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }


    if ($types !== null && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $result = $stmt->execute();

    if (!$result) {
        $stmt->close();
        return false;
    }

    $affected_rows = $stmt->affected_rows;
    $stmt->close();

    return $affected_rows;
}

function db_insert($sql, $types = null, $params = [])
{
    $conn = get_database_connection();

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }


    if ($types !== null && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $result = $stmt->execute();

    if (!$result) {
        $stmt->close();
        return false;
    }

    $insert_id = $conn->insert_id;
    $stmt->close();

    return $insert_id;
}

function db_error()
{
    $conn = get_database_connection();
    return $conn->error;
}

function db_escape($input)
{
    $conn = get_database_connection();
    return $conn->real_escape_string($input);
}

function db_begin_transaction()
{
    $conn = get_database_connection();
    return $conn->begin_transaction();
}

function db_commit()
{
    $conn = get_database_connection();
    return $conn->commit();
}

function db_rollback()
{
    $conn = get_database_connection();
    return $conn->rollback();
}

function db_save($table, $data, $where = null)
{

    if ($where === null) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $types = '';
        $params = [];

        foreach ($data as $value) {
            $types .= get_param_type($value);
            $params[] = $value;
        }

        return db_insert($sql, $types, $params);
    } else {
        $set_clause = '';
        $types = '';
        $params = [];

        foreach ($data as $column => $value) {
            $set_clause .= "$column = ?, ";
            $types .= get_param_type($value);
            $params[] = $value;
        }

        $set_clause = rtrim($set_clause, ', ');
        $sql = "UPDATE $table SET $set_clause WHERE $where";

        return db_execute($sql, $types, $params);
    }
}

function get_param_type($value)
{
    if (is_int($value)) {
        return 'i';
    } else if (is_float($value)) {
        return 'd';
    } else if (is_string($value)) {
        return 's';
    } else {
        return 's';
    }
}