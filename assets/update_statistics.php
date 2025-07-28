<?php
// Check if user declined cookies
$tracking_enabled = !(isset($_COOKIE['cookie_declined']) && $_COOKIE['cookie_declined'] == "1");

if ($tracking_enabled) {
    // Get Visitor Info

// Get Visitor Info
$ip = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];
$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Direct';
$page_url = $_SERVER['REQUEST_URI'];
$visit_start = microtime(true);

// Function to get browser & OS
function getBrowserOS($user_agent) {
    if (strpos($user_agent, 'Windows') !== false) $os = "Windows";
    elseif (strpos($user_agent, 'Mac') !== false) $os = "MacOS";
    elseif (strpos($user_agent, 'Linux') !== false) $os = "Linux";
    elseif (strpos($user_agent, 'Android') !== false) $os = "Android";
    elseif (strpos($user_agent, 'iPhone') !== false) $os = "iPhone";
    else $os = "Other";

    if (strpos($user_agent, 'Chrome') !== false) $browser = "Chrome";
    elseif (strpos($user_agent, 'Firefox') !== false) $browser = "Firefox";
    elseif (strpos($user_agent, 'Safari') !== false) $browser = "Safari";
    elseif (strpos($user_agent, 'Edge') !== false) $browser = "Edge";
    elseif (strpos($user_agent, 'Opera') !== false) $browser = "Opera";
    else $browser = "Other";

    return [$browser, $os];
}

// Get Browser & OS
list($browser, $os) = getBrowserOS($user_agent);
// Detect Device Type
$device = (preg_match('/Mobile|Android|iPhone/i', $user_agent)) ? "Mobile" : "Desktop";

// Check if user is new (IP-based)
$stmt = $db->prepare("SELECT COUNT(*) FROM visitor_logs WHERE ip_address = ?");
$stmt->execute([$ip]);
$is_new_visitor = $stmt->fetchColumn() == 0;

// Insert Visitor Log
$stmt = $db->prepare("INSERT INTO visitor_logs (ip_address, user_agent, browser, device, os, country, referrer) 
                      VALUES (?, ?, ?, ?, ?, 'Unknown', ?)");
$stmt->execute([$ip, $user_agent, $browser, $device, $os, $referrer]);
// Update Web Statistics
$stmt = $db->query("SELECT * FROM web_statistics LIMIT 1");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$stats) {
    $stmt = $db->prepare("INSERT INTO web_statistics (total_visits, unique_visitors, page_views) VALUES (1, 1, 1)");
    $stmt->execute();
} else {
    $stmt = $db->prepare("UPDATE web_statistics 
                          SET total_visits = total_visits + 1, 
                              unique_visitors = unique_visitors + ?, 
                              page_views = page_views + 1, 
                              last_updated = NOW()");
    $stmt->execute([$is_new_visitor ? 1 : 0]);
}

// Insert Page View Log
$stmt = $db->prepare("INSERT INTO page_views (ip_address, page_url, visit_time) VALUES (?, ?, NOW())");
$stmt->execute([$ip, $page_url]);

// Capture Time Spent on Page
register_shutdown_function(function() use ($db, $ip, $page_url, $visit_start) {
    $visit_end = microtime(true);
    $time_spent = round($visit_end - $visit_start, 2);

    $stmt = $db->prepare("UPDATE page_views SET time_spent = time_spent + ? WHERE ip_address = ? AND page_url = ? ORDER BY visit_time DESC LIMIT 1");
    $stmt->execute([$time_spent, $ip, $page_url]);
});
}
?>
