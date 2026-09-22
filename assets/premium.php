<?php

declare(strict_types=1);


function userHasPurchase(PDO $db, int $userId, string $productKey): bool
{
    $stmt = $db->prepare("
        SELECT 1
        FROM user_purchases
        WHERE user_id = :user_id
          AND product_key = :product_key
          AND status = 'paid'
        LIMIT 1
    ");

    $stmt->execute([
        ':user_id' => $userId,
        ':product_key' => $productKey,
    ]);

    return (bool) $stmt->fetchColumn();
}
