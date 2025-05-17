<?php


define('SITE_NAME', 'דורון הוסט');


define('DB_SERVER', 'איפה אתה מאחסן ארת זה??');
define('DB_USERNAME', 'שם משתמש');
define('DB_PASSWORD', 'סיסמה');
define('DB_NAME', 'שם של הממסד נתונים');

function get_database_connection()
{
    static $conn = null;


    if ($conn !== null) {
        return $conn;
    }


    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);


    $conn->set_charset('utf8mb4');


    if ($conn->connect_error) {

        if (defined('DISPLAY_DB_ERRORS') && DISPLAY_DB_ERRORS) {
            die("Database connection failed: " . $conn->connect_error);
        } else {

            die("Error connecting to database. Please try again later.");
        }
        return false;
    }

    return $conn;
}