<?php

require_once __DIR__ . '/../config/env.php';

$is_local_dev = php_sapi_name() === 'cli-server'; // true only when served via `php -S` (PHP's built-in dev server)

session_set_cookie_params([
    'lifetime' => 0,  // 0 means it lasts until the browser is closed
    'path' => '/',  // Available across the entire site
    'domain' => $is_local_dev ? '' : 'qrsume.com', // Make sure this matches your domain
    'secure' => !$is_local_dev, // Ensures session is sent only over HTTPS
    'httponly' => true, // Prevents JavaScript from accessing the session
    'samesite' => 'Lax' // Prevents CSRF but allows navigation
]);

session_start();
if ($is_local_dev) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
define('DB_SERVER', env('DB_SERVER', '127.0.0.1:3306'));
define('DB_USERNAME', env('DB_USERNAME', ''));
define('DB_PASSWORD', env('DB_PASSWORD', ''));
define('DB_NAME', env('DB_NAME', ''));

//Function to clean inputs

function cleanInput($input)
{
    // Force UTF-8 encoding
    $input = mb_convert_encoding($input, 'UTF-8', 'auto');

    // Allow letters (with accents), numbers, spaces, and some punctuation
    $cleaned = preg_replace("/[^a-zA-Z0-9 ñÑáéíóúÁÉÍÓÚüÜ\-.,()?!@#^*_+=:\/•\x0A\x0D]/u", "", trim($input));
    return $cleaned;
}

/* Attempt to connect to the database */
try {
    if ($is_local_dev) {
        $db = new PDO("sqlite:" . __DIR__ . "/../local_dev.sqlite");
        $db->exec('PRAGMA foreign_keys = ON');
        // Shim MySQL-only date functions used across the codebase so raw SQL keeps working against SQLite
        $db->sqliteCreateFunction('CURDATE', fn () => date('Y-m-d'), 0);
        $db->sqliteCreateFunction('NOW', fn () => date('Y-m-d H:i:s'), 0);
    } else {
        $db = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    }
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
    exit();
}

//SQL FUNCTIONS
// 🔁 Generic function to retrieve data
/**
 * Realiza una consulta SELECT sobre cualquier tabla de la base de datos.
 *
 * @param PDO $db Conexión PDO a la base de datos.
 * @param string $table Nombre de la tabla a consultar.
 * @param string $columns Columnas a seleccionar (por defecto: '*').
 * @param array $conditions Filtros en formato ['columna' => valor].
 * @param string $orderBy Orden opcional (por ejemplo: "id DESC").
 * @param string $limit Límite de resultados (por ejemplo: "10").
 *
 * @return array Resultado de la consulta como array asociativo.
 */
function db_select($db, $table, $columns = '*', $conditions = [], $orderBy = '', $limit = '')
{
    $sql = "SELECT $columns FROM `$table`";
    $params = [];

    if (!empty($conditions)) {
        $whereClauses = [];
        foreach ($conditions as $column => $value) {
            $whereClauses[] = "`$column` = :$column";
            $params[$column] = $value;
        }
        $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
    }

    if ($orderBy) {
        $sql .= " ORDER BY $orderBy";
    }
    if ($limit) {
        $sql .= " LIMIT $limit";
    }

    $stmt = $db->prepare($sql);
    foreach ($params as $param => $value) {
        $stmt->bindValue(":$param", $value);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ➕ Insert data into table
/**
 * Inserta un nuevo registro en la base de datos.
 *
 * @param PDO $db Conexión PDO a la base de datos.
 * @param string $table Nombre de la tabla donde insertar.
 * @param array $data Datos a insertar, en formato ['columna' => valor].
 *
 * @return bool True si la inserción fue exitosa, False si falló.
 */

function db_insert($db, $table, $data)
{
    $columns = implode(", ", array_keys($data));
    $placeholders = ":" . implode(", :", array_keys($data));

    $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";
    $stmt = $db->prepare($sql);

    foreach ($data as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }

    return $stmt->execute();
}

// ✏️ Update data
/**
 * Actualiza registros existentes en la base de datos.
 *
 * @param PDO $db Conexión PDO a la base de datos.
 * @param string $table Nombre de la tabla a actualizar.
 * @param array $data Nuevos valores, en formato ['columna' => nuevo_valor].
 * @param array $conditions Filtros para seleccionar los registros, en formato ['columna' => valor].
 *
 * @return bool True si la actualización fue exitosa, False si falló.
 */
function db_update($db, $table, $data, $conditions)
{
    $setClause = [];
    foreach ($data as $column => $value) {
        $setClause[] = "`$column` = :set_$column";
    }

    $whereClause = [];
    foreach ($conditions as $column => $value) {
        $whereClause[] = "`$column` = :cond_$column";
    }

    $sql = "UPDATE `$table` SET " . implode(", ", $setClause) . " WHERE " . implode(" AND ", $whereClause);
    $stmt = $db->prepare($sql);

    foreach ($data as $column => $value) {
        $stmt->bindValue(":set_$column", $value);
    }
    foreach ($conditions as $column => $value) {
        $stmt->bindValue(":cond_$column", $value);
    }

    return $stmt->execute();
}

// ❌ Delete data
/**
 * Elimina registros de la base de datos según ciertas condiciones.
 *
 * @param PDO $db Conexión PDO a la base de datos.
 * @param string $table Nombre de la tabla de donde eliminar.
 * @param array $conditions Filtros para seleccionar los registros, en formato ['columna' => valor].
 *
 * @return bool True si el borrado fue exitoso, False si falló.
 */

function db_delete($db, $table, $conditions)
{
    $whereClause = [];
    foreach ($conditions as $column => $value) {
        $whereClause[] = "`$column` = :$column";
    }

    $sql = "DELETE FROM `$table` WHERE " . implode(" AND ", $whereClause);
    $stmt = $db->prepare($sql);

    foreach ($conditions as $column => $value) {
        $stmt->bindValue(":$column", $value);
    }

    return $stmt->execute();
}



// Sanitize to prevent XSS
$username_url = isset($_GET['username']) ? htmlspecialchars($_GET['username']) : '';

$user_id = 0; // Default to invalid user

// 1️⃣ **Check if a username is provided in the URL**
if (!empty($username_url)) {
    $stmt_user = $db->prepare("SELECT id FROM users WHERE username = :username");
    $stmt_user->bindValue(":username", $username_url, PDO::PARAM_STR);
    $stmt_user->execute();
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $user_id = $user['id']; // ✅ Valid username found, set user_id
    } else {
        header('Location: https://qrsume.com'); // ❌ Invalid username, redirect to main page
        exit();
    }
}

// 2️⃣ **If no valid username, check for a valid `user_id` in GET**
elseif (isset($_GET['user_id']) && ctype_digit($_GET['user_id'])) {
    $user_id = (int) $_GET['user_id']; // Convert to integer for security

    $stmt_check = $db->prepare("SELECT username FROM users WHERE id = :user_id");
    $stmt_check->bindValue(":user_id", $user_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $username_stmt = $stmt_check->fetch(PDO::FETCH_ASSOC);
    if (!$username_stmt) {
        header('Location: https://qrsume.com'); // ❌ Invalid user_id, redirect
        exit();
    }
    $username_url = $username_stmt['username'];

}

// 3️⃣ **If neither username nor GET user_id is valid, check if admin is logged in**
elseif (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $user_id = $_SESSION['id']; // ✅ Admin logged in, use their session user_id
}

// 4️⃣ **If no valid user was found, redirect to main page**
if (!$user_id == 0) {
    // ✅ **At this point, `user_id` is valid** ✅

    // Getting basic information
    $quick_access_tables = ['personalinfo', 'contactinfo', 'aptitudes', 'education', 'experience', 'interests', 'languages','custom_sections',
    'visibility_settings'];
    $results = [];

    // Fetch data from multiple tables
    foreach ($quick_access_tables as $table) {
        $stmt = $db->prepare("SELECT * FROM `$table` WHERE `user_id` = :user_id");
        $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $results[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Assign primary profile data
    $personalinfo = $results["personalinfo"][0] ?? null;
    $contactinfo = $results["contactinfo"][0] ?? null;
    $visibility = [];
    foreach ($results["visibility_settings"] as $element) {
        $visibility[$element["field_name"]] = $element["is_visible"];
    }


}
