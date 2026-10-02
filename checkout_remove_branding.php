<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
include __DIR__ . '/assets/db.php'; // starts the session with the app's cookie params

if (!isset($_SESSION['id'])) {
    header('Location: /assets/login.php');
    exit;
}

$userId = (int) $_SESSION['id'];
$username = $_SESSION['username'] ?? '';

\Stripe\Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

$YOUR_DOMAIN = 'https://qrsume.com';

// Coming from the create_resume form (locked PDF import): go back there afterwards
$fromForm = ($_GET['return'] ?? '') === 'form';
$returnUrl = $fromForm ? $YOUR_DOMAIN . '/create_resume/form_with_login.php' : $YOUR_DOMAIN . '/preview.php';

try {
    $checkoutSession = \Stripe\Checkout\Session::create([
        'mode' => 'payment',

        // You can remove payment_method_types and let Stripe choose enabled methods.
        'payment_method_types' => ['card'],
        'allow_promotion_codes' => true,

        'line_items' => [[
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => 199,
                'product_data' => [
                    'name' => 'QRsume premium: remove branding forever',
                    'description' => 'Remove the QR code and qrsume.com links from your generated resume PDFs, and get 5 more CV imports from PDF.',
                ],
            ],
            'quantity' => 1,
        ]],

        'success_url' => $YOUR_DOMAIN . '/assets/payment_success.php?session_id={CHECKOUT_SESSION_ID}' . ($fromForm ? '&return=form' : ''),
        'cancel_url' => $returnUrl,

        'metadata' => [
            'user_id' => (string) $userId,
            'username' => (string) $username,
            'product_key' => 'remove_qrsume_branding',
        ],
    ]);

    header('Location: ' . $checkoutSession->url, true, 303);
    exit;

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log('Stripe Checkout error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Payment could not be started. Please try again later.';
    exit;
}
