<?php
/* backend-api/api/_helpers.php
   Segurança, CORS, variáveis de ambiente e respostas JSON */

declare(strict_types=1);

function env_required(string $key): string
{
    $value = getenv($key);

    if ($value === false || trim((string) $value) === '') {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => "Variável de ambiente ausente: {$key}",
        ]);
        exit;
    }

    return trim((string) $value);
}

function env_optional(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false || trim((string) $value) === '' ? $default : trim((string) $value);
}

function security_headers(): void
{
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Content-Security-Policy: default-src 'self' https:; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:; connect-src 'self' https:; frame-ancestors 'none';");
}

function cors_headers(): void
{
    security_headers();

    $allowedOrigins = array_filter(array_map('trim', explode(',', env_optional('CORS_ALLOWED_ORIGINS', ''))));
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($allowedOrigins && in_array($origin, $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Buck-Signature, X-Request-Id');
    header('Access-Control-Max-Age: 86400');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function send_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?? '', true);

    if (!is_array($data)) {
        send_json(['success' => false, 'error' => 'JSON inválido'], 400);
    }

    return $data;
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json(['success' => false, 'error' => 'Método não permitido'], 405);
    }
}

function require_get(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        send_json(['success' => false, 'error' => 'Método não permitido'], 405);
    }
}

function clean_string(string $value, int $max = 255): string
{
    return substr(strip_tags(trim($value)), 0, $max);
}

function clean_document(string $value): string
{
    return preg_replace('/\D/', '', $value) ?? '';
}

function clean_phone(string $value): string
{
    return preg_replace('/\D/', '', $value) ?? '';
}