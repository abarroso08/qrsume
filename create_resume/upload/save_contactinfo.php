<?php
include("../../assets/head.php");

header('Content-Type: application/json'); // For AJAX responses

if ($_SERVER["REQUEST_METHOD"] === "POST" 
    && isset($_POST['action']) 
    && $_POST['action'] === "save_contactinfo") {

    // Check if user is authenticated
    if (!isset($_SESSION['id'])) {
        $response = [
            'success' => false,
            'message' => 'Unauthorized access.'
        ];
        http_response_code(401);
        echo json_encode($response);
        exit();
    }

    $user_id = $_SESSION['id'];

    // Clean inputs
    $phone_number = cleanInput($_POST['phone_number'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $github = cleanInput($_POST['github'] ?? '');
    $facebook = cleanInput($_POST['facebook'] ?? '');
    $linkedin = cleanInput($_POST['linkedin'] ?? '');
    $twitter = cleanInput($_POST['twitter'] ?? '');

    // Validate email (basic check)
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response = [
            'success' => false,
            'message' => 'Invalid email format.'
        ];
        http_response_code(400);
        echo json_encode($response);
        exit();
    }

    // Validate URLs (basic check)
    $urls = ['github', 'facebook', 'linkedin', 'twitter'];
    foreach ($urls as $url_field) {
        if (!empty($$url_field) && $$url_field !== 'https://' && !filter_var($$url_field, FILTER_VALIDATE_URL)) {
            $response = [
                'success' => false,
                'message' => "Invalid URL format for $url_field."
            ];
            http_response_code(400);
            echo json_encode($response);
            exit();
        }
    }

    $data = [
        'phone_number' => $phone_number,
        'email' => $email,
        'github' => $github === 'https://' ? '' : $github, // Store empty string if unchanged
        'facebook' => $facebook,
        'linkedin' => $linkedin,
        'twitter' => $twitter
    ];

    // Check if record exists
    $query = "SELECT COUNT(*) FROM contactinfo WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $record_exists = $stmt->fetchColumn() > 0;
    $visibility = $_POST['visibility'] ?? [];
    $fields = [
    'phone' => isset($visibility['phone_number']) ? 0 : 1,
    'email' => isset($visibility['email']) ? 0 : 1
];

    foreach ($fields as $field_name => $is_visible) {
    $check = $db->prepare("SELECT COUNT(*) FROM visibility_settings WHERE user_id = ? AND field_name = ?");
    $check->execute([$user_id, $field_name]);
    $exists = $check->fetchColumn() > 0;

    if ($exists) {
        $update = $db->prepare("UPDATE visibility_settings SET is_visible = ? WHERE user_id = ? AND field_name = ?");
        $update->execute([$is_visible, $user_id, $field_name]);
    } else {
        $insert = $db->prepare("INSERT INTO visibility_settings (user_id, field_name, is_visible) VALUES (?, ?, ?)");
        $insert->execute([$user_id, $field_name, $is_visible]);
    }
}
    
    if ($record_exists) {
        // Update existing record
        $ok = db_update($db, 'contactinfo', $data, ['user_id' => $user_id]);
        
    } else {
        // Insert new record
        $data['user_id'] = $user_id;
        $ok = db_insert($db, 'contactinfo', $data);
    }
    

    if ($ok) {
        // Success — return JSON for AJAX and redirect for fallback
        $response = [
            'success' => true,
            'message' => 'Contact information saved successfully.'
        ];
        echo json_encode($response);
        // Redirect to next section (Education)
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=2");
        exit();
    } else {
        // Error — return JSON for AJAX and redirect for fallback
        $response = [
            'success' => false,
            'message' => 'Failed to save contact information.'
        ];
        http_response_code(500);
        echo json_encode($response);
        // Redirect back to contact section with error
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=1&error=save_failed");
        exit();
    }

} else {
    // Bad request
    $response = [
        'success' => false,
        'message' => 'Invalid request.'
    ];
    http_response_code(400);
    echo json_encode($response);
    // Redirect to contact section with error
    header("Location: https://qrsume.com/create_resume/form_with_login.php?section=1&error=bad_request");
    exit();
}