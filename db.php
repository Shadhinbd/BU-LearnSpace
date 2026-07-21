<?php
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "1234"; // your XAMPP password if any
$DB_NAME = "bu_study";

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    die("Database connection failed: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

/**
 * Safely ensures a column exists on a table, without relying on
 * "ADD COLUMN IF NOT EXISTS" — that syntax isn't supported on older
 * MySQL/MariaDB versions and can throw a fatal SQL error. This checks
 * first with SHOW COLUMNS (supported everywhere) and only alters the
 * table if the column is actually missing.
 */
function ensure_column($mysqli, $table, $column, $definition) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    $result = @$mysqli->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result && $result->num_rows === 0) {
        @$mysqli->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

/**
 * Builds a clear "uploads folder not writable" message that also reports
 * exactly which OS user PHP is actually running as, so an administrator
 * doesn't have to guess which chown/chmod target to use.
 */
function uploads_permission_message($dir) {
    $runtime_user = null;
    if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
        $info = @posix_getpwuid(posix_geteuid());
        if ($info && !empty($info['name'])) {
            $runtime_user = $info['name'];
        }
    }
    if (!$runtime_user) {
        $runtime_user = get_current_user();
    }

    $msg = 'The server "uploads" folder is not writable. Please ask your administrator to run: '
         . 'sudo chown -R ' . $runtime_user . ':' . $runtime_user . ' ' . realpath($dir) . ' && '
         . 'sudo chmod -R 775 ' . realpath($dir);
    $msg .= ' (PHP is currently running as user: "' . $runtime_user . '")';
    return $msg;
}
?>
