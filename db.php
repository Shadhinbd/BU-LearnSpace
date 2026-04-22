<?php
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "1234"; // your XAMPP password if any
$DB_NAME = "bu_learnspace";

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    die("Database connection failed: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");
?>
