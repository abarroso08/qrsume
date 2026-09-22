<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
include __DIR__ . '/assets/db.php';

\Stripe\Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

$endpointSecret = env('STRIPE_WEBHOOK_SECRET');

$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sigHeader,
        $endpointSecret
    );
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    exit('Invalid payload');
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit('Invalid signature');
}

if ($event->type === 'checkout.session.completed') {
    $session = $event->data->object;

    $userId = (int) ($session->metadata->user_id ?? 0);
    $productKey = (string) ($session->metadata->product_key ?? '');

    if ($userId > 0 && $productKey === 'remove_qrsume_branding') {
        $stmt = $db->prepare("
            INSERT INTO user_purchases (
                user_id,
                product_key,
                stripe_checkout_session_id,
                stripe_payment_intent_id,
                amount_paid,
                currency,
                status
            )
            VALUES (
                :user_id,
                :product_key,
                :checkout_session_id,
                :payment_intent_id,
                :amount_paid,
                :currency,
                'paid'
            )
            ON DUPLICATE KEY UPDATE
                status = 'paid',
                stripe_payment_intent_id = VALUES(stripe_payment_intent_id),
                amount_paid = VALUES(amount_paid),
                currency = VALUES(currency)
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':product_key' => $productKey,
            ':checkout_session_id' => $session->id,
            ':payment_intent_id' => $session->payment_intent ?? null,
            ':amount_paid' => $session->amount_total ?? 0,
            ':currency' => $session->currency ?? 'eur',
        ]);
    }
}

http_response_code(200);
