<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/vnd.ms-word");
header("Content-Disposition: attachment; filename=resume.doc");

// Database connection
include("assets/db.php");

// Ensure user is logged in or username is provided
if (!isset($_GET['username'])) {
    if (!isset($_SESSION['username'])) {
        header("Location: https://qrsume.com/error.php");
        exit();
    } else {
        $username = $_SESSION['username'];
    }
} else {
    $username = $_GET['username'];
}

// Prepare and execute query
$stmt_login = $db->prepare("SELECT id FROM users WHERE username = :username");
$stmt_login->bindValue(":username", $username, PDO::PARAM_STR);
$stmt_login->execute();

$user = $stmt_login->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header("Location: https://qrsume.com/error.php");
    exit();
}

$user_id = $user['id'];

try {
    $pdo = $db;

    // Fetch Personal Info
    $stmt = $pdo->prepare("SELECT * FROM personalinfo WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $personal = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch Contact Info
    $stmt = $pdo->prepare("SELECT * FROM contactinfo WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch Education
    $stmt = $pdo->prepare("SELECT * FROM education WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $education = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Experience
    $stmt = $pdo->prepare("SELECT * FROM experience WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $experience = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Languages
    $stmt = $pdo->prepare("SELECT * FROM languages WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Skills
    $stmt = $pdo->prepare("SELECT * FROM aptitudes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

echo "<html><body>";

echo "<h1>{$personal['personal_name']} {$personal['personal_lastname']}</h1>";
echo "<p>Email: {$contact['email']} | Phone: {$contact['phone_number']}</p>";
echo "<p>Website: <a href='https://qrsume.com/{$username}'>qrsume.com/{$username}</a></p>";

if (!empty($education)) {
    echo "<h2>Education</h2><table border='1'>";
    echo "<tr><th>Study</th><th>Institution</th><th>Year</th></tr>";
    foreach ($education as $edu) {
        echo "<tr><td>{$edu['name_of_studies']}</td><td>{$edu['place_of_study']}</td><td>{$edu['date']}</td></tr>";
    }
    echo "</table>";
}

if (!empty($experience)) {
    echo "<h2>Work Experience</h2><table border='1'>";
    echo "<tr><th>Job</th><th>Company</th><th>Year</th></tr>";
    foreach ($experience as $exp) {
        echo "<tr><td>{$exp['job_name']}</td><td>{$exp['place_of_work']}</td><td>{$exp['date']}</td></tr>";
    }
    echo "</table>";
}

if (!empty($languages)) {
    echo "<h2>Languages</h2><ul>";
    foreach ($languages as $lang) {
        echo "<li>{$lang['language']} ({$lang['level']})</li>";
    }
    echo "</ul>";
}

if (!empty($skills)) {
    echo "<h2>Skills</h2><ul>";
    foreach ($skills as $skill) {
        echo "<li>{$skill['aptitude']}</li>";
    }
    echo "</ul>";
}

echo "</body></html>";
?>