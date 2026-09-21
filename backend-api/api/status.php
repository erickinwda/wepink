<?php
/* backend-api/api/status.php
   Consulta status de transação BuckPay. Endpoint: GET /api/status.php?external_id= */

declare(strict_types=1);
require_once __DIR__ . '/_protect.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../src/BuckAPI.php';

cors_headers();
require_get();

$api = new BuckAPI();

$externalId = $_GET['external_id'] ?? '';
if ($externalId === '') {
    send_json(['success' => false, 'error' => 'external_id é obrigatório'], 400);
}

$result = $api->getChargeStatus($externalId);
send_json($result);