<?php
/**
 * Records a view, download, or QR scan for a given user.
 * Updates both daily (page_stats) and lifetime (user_statistics) counters.
 *
 * @param PDO    $db     PDO connection
 * @param int    $userId ID of the user whose profile is being viewed/scanned/etc.
 * @param string $type   One of: 'views', 'downloads', 'qr_scans'
 */
function recordStat(PDO $db, int $userId, string $type): void {
    $allowed = ['views', 'downloads', 'qr_scans'];
    if (!in_array($type, $allowed, true)) {
        throw new InvalidArgumentException("Invalid stat type: $type");
    }

    // Step 1: Update daily stats (page_stats)
    $stmt = $db->prepare("
        INSERT INTO page_stats (user_id, stat_date, {$type})
        VALUES (:uid, CURDATE(), 1)
        ON DUPLICATE KEY UPDATE {$type} = {$type} + 1
    ");
    $stmt->execute([
        ':uid' => $userId
    ]);

    // Step 2: Update lifetime stats (user_statistics)
    $stmt = $db->prepare("
        INSERT INTO user_statistics (user_id, {$type})
        VALUES (:uid, 1)
        ON DUPLICATE KEY UPDATE {$type} = {$type} + 1
    ");
    $stmt->execute([':uid' => $userId]);
}

/**
 * Record a page view.
 */
function recordPageView(PDO $db, int $userId): void {
    recordStat($db, $userId, 'views');
}

/**
 * Record a download.
 */
function recordDownload(PDO $db, int $userId): void {
    recordStat($db, $userId, 'downloads');
}

/**
 * Record a QR scan.
 */
function recordQRScan(PDO $db, int $userId): void {
    recordStat($db, $userId, 'qr_scans');
}
