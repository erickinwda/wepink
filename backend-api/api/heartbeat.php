<?php
/* backend-api/api/heartbeat.php
   Recebe heartbeat comportamental do anti-scraping client-side */

declare(strict_types=1);
require_once __DIR__ . '/_protect.php';
require_once __DIR__ . '/_helpers.php';

cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'error' => 'Método não permitido'], 405);
}

$input = read_json_body();

// Log comportamental para análise posterior
$log = [
    'ts' => date('c'),
    'ip' => clientIP(),
    'fp' => $input['fp'] ?? '',
    'duration' => $input['duration'] ?? 0,
    'clicks' => $input['clicks'] ?? 0,
    'moves' => $input['moves'] ?? 0,
    'scrolls' => $input['scrolls'] ?? 0,
    'keys' => $input['keys'] ?? 0,
    'cpm' => $input['cpm'] ?? 0,
    'url' => $input['url'] ?? '',
    'ua' => $input['ua'] ?? '',
];

@file_put_contents(__DIR__ . '/../storage/heartbeat.log', json_encode($log) . "\n", FILE_APPEND | LOCK_EX);

// Análise básica de anomalia
$anomaly = false;
if ($log['duration'] > 0) {
    $cpm = $log['cpm'];
    if ($cpm > 300) $anomaly = true; // cliques impossíveis humanamente
    if ($log['moves'] === 0 && $log['clicks'] > 0) $anomaly = true; // cliques sem mover mouse
    if ($log['scrolls'] === 0 && $log['duration'] > 30000) $anomaly = true; // 30s sem scroll
}

if ($anomaly) {
    @file_put_contents(__DIR__ . '/../storage/anomaly.log', json_encode($log) . "\n", FILE_APPEND | LOCK_EX);
    // Marca IP para desafio mais agressivo
    $anomKey = "anom:" . clientIP();
    $anom = storageGet($anomKey, ['count' => 0]);
    $anom['count']++;
    storageSet($anomKey, $anom);
}

send_json(['success' => true]);