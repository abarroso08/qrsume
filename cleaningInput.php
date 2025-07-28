<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clean Input</title>
</head>
<body>
    <h2>Input Cleaner</h2>
    <form method="post">
        <label for="user_input">Enter your text:</label><br>
        <textarea name="user_input" id="user_input" rows="4" cols="50"><?= htmlspecialchars($_POST['user_input'] ?? '') ?></textarea><br><br>
        <input type="submit" value="Clean Text">
    </form>

    <?php
    function cleanInput($input) {
        // Force UTF-8 encoding
        $input = mb_convert_encoding($input, 'UTF-8', 'auto');

        // Allow letters (with accents), numbers, spaces, and some punctuation
        $cleaned = preg_replace("/[^a-zA-Z0-9 ñÑáéíóúÁÉÍÓÚüÜ\-.,()?!@#^*_+=:\/\x0A\x0D]/u", "", trim($input));
        return $cleaned;
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['user_input'])) {
        $original = $_POST['user_input'];
        $cleaned = cleanInput($original);

        echo "<h3>Cleaned Output:</h3>";
        echo "<pre>" . htmlspecialchars($cleaned) . "</pre>";
    }
    ?>
</body>
</html>
