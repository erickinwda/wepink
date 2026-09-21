<?php
/* backend-api/api/_protect.php
   Camadas de proteção anti-scraping / anti-bot / anti-HTTrack
   Inclua no topo de TODOS os endpoints: require_once __DIR__ . '/_protect.php'; */

declare(strict_types=1);

// ==================== CONFIGURAÇÃO ====================
const PROTECT = [
    'rate_limit' => [
        'window_sec' => 60,           // janela de tempo
        'max_requests' => 30,         // reqs por IP/janela (ajuste conforme tráfego real)
        'burst_allowance' => 5,       // burst inicial permitido
        'lockout_sec' => 300,         // bloqueio após exceder (5 min)
    ],
    'challenge' => [
        'enabled' => true,            // exige challenge JS para requests suspeitos
        'threshold' => 15,            // reqs antes de forçar challenge
        'ttl_sec' => 3600,            // validade do token de challenge
    ],
    'honeypot' => [
        'enabled' => true,
        'field_name' => 'website_url', // campo invisível que humanos não preenchem
    ],
    'headers' => [
        'required' => ['user-agent', 'accept'],
        'blocked_ua_patterns' => [
            '/httrack/i', '/wget/i', '/curl/i', '/python/i', '/bot/i', '/crawler/i',
            '/spider/i', '/scraper/i', '/scrapy/i', '/selenium/i', '/phantom/i',
            '/headless/i', '/puppeteer/i', '/playwright/i', '/libwww/i',
            '/java/i', '/perl/i', '/ruby/i', '/go-http/i', '/axios/i',
        ],
    ],
    'behavior' => [
        'min_request_interval_ms' => 200, // intervalo mínimo entre reqs do mesmo IP
        'max_sequential_errors' => 5,     // erros 4xx/5xx seguidos antes de lockout
    ],
    'fingerprint' => [
        'enabled' => true,
        'cookie_name' => '_fp',
        'ttl_sec' => 86400 * 30,
    ],
];

// ==================== STORAGE (arquivo simples — use Redis em produção) ====================
$storageDir = __DIR__ . '/../storage';
if (!is_dir($storageDir)) { @mkdir($storageDir, 0755, true); }

function storageGet(string $key, array $default = []): array {
    $file = __DIR__ . '/../storage/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) . '.json';
    if (!file_exists($file)) return $default;
    $data = @json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : $default;
}

function storageSet(string $key, array $data): void {
    $file = __DIR__ . '/../storage/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) . '.json';
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

function storageDel(string $key): void {
    $file = __DIR__ . '/../storage/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) . '.json';
    @unlink($file);
}

// ==================== HELPERS ====================
function clientIP(): string {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function sendError(int $code, string $msg, array $extra = []): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $msg] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

function generateToken(int $len = 32): string {
    return bin2hex(random_bytes($len));
}

// ==================== 1. BLOQUEIO DE USER-AGENTS CONHECIDOS ====================
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
foreach (PROTECT['headers']['blocked_ua_patterns'] as $pattern) {
    if (preg_match($pattern, $ua)) {
        // Log silencioso
        @file_put_contents(__DIR__ . '/../storage/blocked_ua.log', date('c') . " | $ua | " . clientIP() . "\n", FILE_APPEND | LOCK_EX);
        sendError(403, 'Acesso negado');
    }
}

// Verifica headers obrigatórios
foreach (PROTECT['headers']['required'] as $h) {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $h));
    if (empty($_SERVER[$key])) {
        sendError(400, 'Header obrigatório ausente: ' . $h);
    }
}

// ==================== 2. HONEYPOT (campo invisível em formulários) ====================
if (PROTECT['honeypot']['enabled'] && ($_SERVER['REQUEST_METHOD'] === 'POST')) {
    $hpField = PROTECT['honeypot']['field_name'];
    if (!empty($_POST[$hpField]) || !empty($_GET[$hpField])) {
        // Bot preencheu campo invisível
        @file_put_contents(__DIR__ . '/../storage/honeypot.log', date('c') . " | " . clientIP() . " | " . json_encode($_POST) . "\n", FILE_APPEND | LOCK_EX);
        sendError(403, 'Atividade suspeita detectada');
    }
}

// ==================== 3. FINGERPRINT / COOKIE DE IDENTIFICAÇÃO ====================
$fpCookie = PROTECT['fingerprint']['cookie_name'];
$fingerprint = $_COOKIE[$fpCookie] ?? null;
if (!$fingerprint) {
    $fingerprint = 'fp_' . generateToken(16);
    setcookie($fpCookie, $fingerprint, [
        'expires' => time() + PROTECT['fingerprint']['ttl_sec'],
        'path' => '/',
        'httponly' => true,
        'secure' => true,
        'samesite' => 'Lax',
    ]);
}
$ip = clientIP();
$fpKey = "fp:$fingerprint";

// ==================== 4. RATE LIMITING POR IP + FINGERPRINT ====================
$now = time();
$window = PROTECT['rate_limit']['window_sec'];
$maxReq = PROTECT['rate_limit']['max_requests'];
$burst = PROTECT['rate_limit']['burst_allowance'];
$lockout = PROTECT['rate_limit']['lockout_sec'];

// Limpeza de entries antigos
$rlKey = "rl:$ip";
$rl = storageGet($rlKey, ['requests' => [], 'locked_until' => 0, 'errors' => 0]);

if ($rl['locked_until'] > $now) {
    $retry = $rl['locked_until'] - $now;
    sendError(429, 'Muitas requisições. Tente novamente em ' . $retry . 's', ['retry_after' => $retry]);
}

// Remove requisições fora da janela
$rl['requests'] = array_filter($rl['requests'], fn($t) => $t > $now - $window);

// Verifica burst allowance
$recentCount = count($rl['requests']);
$effectiveLimit = $maxReq + ($recentCount < $burst ? $burst - $recentCount : 0);

if ($recentCount >= $effectiveLimit) {
    $rl['locked_until'] = $now + $lockout;
    storageSet($rlKey, $rl);
    @file_put_contents(__DIR__ . '/../storage/rate_limit.log', date('c') . " | RATE LIMIT | $ip | $fingerprint\n", FILE_APPEND | LOCK_EX);
    sendError(429, 'Rate limit excedido. Tente novamente em ' . $lockout . 's', ['retry_after' => $lockout]);
}

// Registra esta requisição
$rl['requests'][] = $now;
storageSet($rlKey, $rl);

// ==================== 5. INTERVALO MÍNIMO ENTRE REQUISIÇÕES ====================
$lastReqKey = "last_req:$ip";
$lastReq = storageGet($lastReqKey, ['time' => 0]);
$minInterval = PROTECT['behavior']['min_request_interval_ms'] / 1000;
if ($now - $lastReq['time'] < $minInterval) {
    sendError(429, 'Requisições muito rápidas');
}
storageSet($lastReqKey, ['time' => $now]);

// ==================== 6. CONTROLE DE ERROS SEQUENCIAIS ====================
$errKey = "err:$ip";
$err = storageGet($errKey, ['count' => 0, 'first' => $now]);
if ($err['count'] >= PROTECT['behavior']['max_sequential_errors']) {
    sendError(429, 'Muitos erros. Tente novamente mais tarde');
}

// ==================== 7. CHALLENGE JS PARA CLIENTES SUSPEITOS ====================
if (PROTECT['challenge']['enabled']) {
    $challengeKey = "challenge:$ip";
    $challenge = storageGet($challengeKey, ['count' => 0, 'token' => '', 'expires' => 0]);

    // Incrementa contador de requests sem challenge válido
    if ($challenge['expires'] < $now || empty($challenge['token'])) {
        $challenge['count']++;
        if ($challenge['count'] >= PROTECT['challenge']['threshold']) {
            // Exige challenge — retorna 403 com instruções
            if (empty($_SERVER['HTTP_X_CHALLENGE_TOKEN']) || $_SERVER['HTTP_X_CHALLENGE_TOKEN'] !== $challenge['token']) {
                // Gera novo challenge
                $challenge['token'] = generateToken(24);
                $challenge['expires'] = $now + PROTECT['challenge']['ttl_sec'];
                $challenge['count'] = 0;
                storageSet($challengeKey, $challenge);

                // Retorna página de challenge (ou JSON para API)
                if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') !== false) {
                    // Página HTML com challenge JS
                    echo challengePage($challenge['token']);
                } else {
                    sendError(403, 'Challenge requerido', [
                        'challenge_required' => true,
                        'challenge_endpoint' => '/api/challenge',
                        'token' => $challenge['token'],
                    ]);
                }
                exit;
            }
            // Token válido — reseta contador
            $challenge['count'] = 0;
            $challenge['expires'] = $now + PROTECT['challenge']['ttl_sec'];
            storageSet($challengeKey, $challenge);
        } else {
            storageSet($challengeKey, $challenge);
        }
    }
}

// ==================== FUNÇÃO AUXILIAR: PÁGINA DE CHALLENGE ====================
function challengePage(string $token): string {
    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verificação de segurança</title>
<style>
body{font-family:system-ui,sans-serif;background:#0a0a0a;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
.card{background:#111;border:1px solid #333;border-radius:16px;padding:2rem;max-width:400px;text-align:center}
.spinner{width:40px;height:40px;border:3px solid #333;border-top-color:#ff0080;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 1rem}
@keyframes spin{to{transform:rotate(360deg)}}
h1{font-size:1.2rem;margin-bottom:.5rem}
p{color:#aaa;font-size:.9rem}
</style>
</head>
<body>
<div class="card">
<div class="spinner"></div>
<h1>Verificando seu navegador...</h1>
<p>Aguarde, redirecionando automaticamente.</p>
</div>
<script>
(function(){
  // Proof-of-work simples (CPU-bound) para desacelerar bots
  var target = "0000"; // 4 zeros hex = ~65k hashes médios
  var nonce = 0;
  var start = Date.now();
  var data = "fp_" + navigator.userAgent + "_" + screen.width + "x" + screen.height + "_" + Date.now();
  
  function hash(str) {
    var h = 0;
    for (var i = 0; i < str.length; i++) {
      h = ((h << 5) - h) + str.charCodeAt(i);
      h |= 0;
    }
    return ("00000000" + (h >>> 0).toString(16)).slice(-8);
  }
  
  function work() {
    var batch = 1000;
    for (var i = 0; i < batch; i++) {
      var attempt = hash(data + "_" + nonce++);
      if (attempt.startsWith(target)) {
        // Encontrou — envia token
        fetch('/api/challenge', {
          method: 'POST',
          headers: {'Content-Type': 'application/json', 'X-Challenge-Token': '$token'},
          body: JSON.stringify({nonce: nonce, hash: attempt, fp: data})
        }).then(function(r){
          if (r.ok) window.location.reload();
        });
        return;
      }
    }
    if (Date.now() - start < 30000) requestAnimationFrame(work);
    else document.querySelector('p').textContent = 'Tempo esgotado. Recarregue a página.';
  }
  requestAnimationFrame(work);
})();
</script>
</body>
</html>
HTML;
}

// ==================== FUNÇÃO PARA REGISTRAR ERRO (chame em catch blocks) ====================
function recordError(): void {
    global $errKey, $now;
    $err = storageGet($errKey, ['count' => 0, 'first' => $now]);
    $err['count']++;
    if ($err['count'] === 1) $err['first'] = $now;
    // Reset after 5 min without errors
    if ($now - $err['first'] > 300) $err['count'] = 0;
    storageSet($errKey, $err);
}

// ==================== MIDDLEWARE PARA ENDPOINTS SENSÍVEIS ====================
function protectEndpoint(array $options = []): void {
    // Pode adicionar validações extras por endpoint aqui
    // Ex: require_auth, require_csrf, etc.
}