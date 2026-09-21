<?php
/* backend-api/api/create.php
   Cria transação Pix BuckPay. Endpoint: POST /api/create.php */

declare(strict_types=1);
require_once __DIR__ . '/_protect.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../src/BuckAPI.php';

cors_headers();
require_post();

$api = new BuckAPI();

$input = read_json_body();

// Validação
if (!isset($input['kit']) || !in_array((string) $input['kit'], ['1', '2'], true)) {
    send_json(['success' => false, 'error' => 'Kit inválido'], 400);
}

$amount = isset($input['amount']) ? (int) $input['amount'] : 0;
if ($amount <= 0) {
    send_json(['success' => false, 'error' => 'Valor inválido'], 400);
}

// Monta payload para BuckPay
$payload = [
    'kit' => (string) $input['kit'],
    'amount' => $amount,
    'customer_name' => clean_string($input['customer_name'] ?? '', 255),
    'customer_email' => clean_string($input['customer_email'] ?? '', 255),
];

// Tracking opcional
if (!empty($input['tracking_parameters'])) {
    $payload['tracking_parameters'] = $input['tracking_parameters'];
}

$result = $api->createPix($payload);
send_json($result);