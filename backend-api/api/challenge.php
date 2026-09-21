<?php
/* backend-api/api/challenge.php
   Endpoint para validação de challenge anti-bot */

declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';

cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'error' => 'Método não permitido'], 405);
}

$input = read_json_body();
$token = $_SERVER['HTTP_X_CHALLENGE_TOKEN'] ?? '';

if (!$token) {
    send_json(['success' => false, 'error' => 'Token de challenge ausente'], 400);
}

// Verifica se o token existe no storage
$challengeKey = "challenge:" . clientIP();
$challenge = storageGet($challengeKey, ['token' => '', 'expires' => 0]);

if ($challenge['expires'] < time() || $challenge['token'] !== $token) {
    send_json(['success' => false, 'error' => 'Challenge expirado ou inválido'], 403);
}

// Valida proof-of-work (opcional - simples verificação)
$nonce = $input['nonce'] ?? 0;
$hash = $input['hash'] ?? '';
$fp = $input['fp'] ?? '';

if ($nonce && $hash && $fp) {
    // Verifica se o hash começa com 0000 (4 zeros hex)
    if (!str_starts_with($hash, '0000')) {
        send_json(['success' => false, 'error' => 'Proof-of-work inválido'], 400);
    }
    // Verifica se o nonce está em range razoável
    if ($nonce > 10000000) {
        send_json(['success' => false, 'error' => 'Nonce inválido'], 400);
    }
}

// Sucesso - marca challenge como válido por mais tempo
$challenge['verified'] = true;
$challenge['expires'] = time() + 3600; // 1 hora
storageSet($challengeKey, $challenge);

send_json(['success' => true, 'message' => 'Challenge validado']);