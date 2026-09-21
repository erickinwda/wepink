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

$config = getBuckConfig();
$api = new BuckAPI();

// BuckPay envia assinatura no header X-Buck-Signature (HMAC SHA256 do body raw)
$receivedSig = $_SERVER['HTTP_X_BUCK_SIGNATURE'] ?? '';
$rawBody = file_get_contents('php://input');

// Valida assinatura HMAC SHA256
$expectedSig = hash_hmac('sha256', $rawBody, $config['buck']['webhook_secret']);
if (!$receivedSig || !hash_equals($expectedSig, $receivedSig)) {
    // Log para debug
    @file_put_contents(__DIR__ . '/../storage/webhook_invalid_sig.log', date('c') . " | Invalid signature\n", FILE_APPEND | LOCK_EX);
    send_json(['success' => false, 'error' => 'Assinatura inválida'], 401);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    send_json(['success' => false, 'error' => 'JSON inválido'], 400);
}

$result = $api->processWebhook($payload, $receivedSig);

if ($result['success']) {
    // Log do evento recebido
    @file_put_contents(__DIR__ . '/../storage/webhook_events.log', date('c') . " | " . json_encode([
        'event' => $result['event'],
        'transaction_id' => $result['transaction_id'],
        'external_id' => $result['external_id'],
        'status' => $result['status'],
    ]) . "\n", FILE_APPEND | LOCK_EX);
    
    send_json(['success' => true, 'message' => 'Webhook processado']);
} else {
    send_json(['success' => false, 'error' => $result['error'] ?? 'Erro no webhook'], 400);
}