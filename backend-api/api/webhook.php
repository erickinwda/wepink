<?php
/* backend-api/api/webhook.php
   Recebe webhook da BuckPay. Endpoint: POST /api/webhook.php
   Eventos: transaction.created, transaction.processed */

declare(strict_types=1);
require_once __DIR__ . '/_protect.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../src/BuckAPI.php';

cors_headers();
require_post();

$api = new BuckAPI();

$signature = $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', 'X-Buck-Signature'))] ?? '';
$payload = read_json_body();

$result = $api->processWebhook($payload, $signature);

if ($result['success']) {
    send_json(['success' => true, 'message' => 'Webhook processado']);
} else {
    send_json(['success' => false, 'error' => $result['error'] ?? 'Erro no webhook'], 400);
}