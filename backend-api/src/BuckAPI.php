<?php
/* BuckPay API Client - Wrapper para a API BuckPay
   TODOS os segredos vêm de variáveis de ambiente */

declare(strict_types=1);

require_once __DIR__ . '/../config/buck.php';

class BuckAPI {
    private $config;
    private $baseUrl;
    private $accessToken;
    private $userAgent;
    
    public function __construct() {
        $this->config = getBuckConfig();
        $this->baseUrl = rtrim($this->config['buck']['base_url'], '/');
        $this->accessToken = $this->config['buck']['access_token'];
        $this->userAgent = $this->config['buck']['user_agent'];
    }
    
    /**
     * Cria uma transação Pix na BuckPay
     * 
     * @param array $data Dados do pagamento
     * @return array Resposta padronizada
     */
    public function createPix(array $data): array {
        $kit = $data['kit'] ?? '';
        $product = $this->config['products'][$kit] ?? null;
        
        if (!$product) {
            return ['success' => false, 'error' => 'Kit inválido'];
        }
        
        // external_id único para idempotência
        $externalId = $product['external_id_prefix'] . bin2hex(random_bytes(8));
        
        $payload = [
            'external_id' => $externalId,
            'payment_method' => 'pix',
            'amount' => (int)$data['amount'], // já em centavos
            'buyer' => [
                'name' => clean_string($data['customer_name'] ?? '', 255),
                'email' => clean_string($data['customer_email'] ?? '', 255),
            ],
            'callback_url' => $this->config['checkout']['webhook_url'],
        ];
        
        // Tracking/metadata opcional
        if (!empty($data['tracking_parameters'])) {
            $payload['metadata'] = $data['tracking_parameters'];
        }
        
        $response = $this->request('POST', '/v1/transactions', $payload);
        
        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error'] ?? 'Erro ao criar transação Pix'
            ];
        }
        
        $tx = $response['data'];
        
        // BuckPay retorna: id, external_id, status, pix (qrcode, qrcode_image, copy_paste, expires_at)
        return [
            'success' => true,
            'payment' => [
                'id' => $tx['id'] ?? '',
                'external_id' => $tx['external_id'] ?? '',
                'transaction_id' => $tx['id'] ?? '',
                'amount' => ($tx['amount'] ?? 0) / 100,
                'currency' => 'BRL',
                'status' => $tx['status'] ?? 'pending',
                'pix_qrcode' => $tx['pix']['qrcode'] ?? '',
                'pix_copy_paste' => $tx['pix']['copy_paste'] ?? '',
                'pix_qrcode_image' => $tx['pix']['qrcode_image'] ?? '',
                'expires_at' => $tx['pix']['expires_at'] ?? date('Y-m-d H:i:s', strtotime('+15 minutes')),
            ],
            'pix_qrcode_image' => $tx['pix']['qrcode_image'] ?? '',
            'pix_copy_paste' => $tx['pix']['copy_paste'] ?? '',
            'pix_url' => $tx['pix']['qrcode'] ?? '',
            'transaction_id' => $tx['id'] ?? '',
            'expires_at' => $tx['pix']['expires_at'] ?? date('Y-m-d H:i:s', strtotime('+15 minutes')),
        ];
    }
    
    /**
     * Consulta status de uma transação pelo external_id
     */
    public function getChargeStatus(string $externalId): array {
        $response = $this->request('GET', "/v1/transactions/external_id/" . rawurlencode($externalId));
        
        if (!$response['success']) {
            return [
                'success' => false,
                'status' => 'error',
                'error' => $response['error'] ?? 'Erro ao consultar status'
            ];
        }
        
        $tx = $response['data'];
        $statusMap = [
            'pending' => 'pending',
            'processing' => 'pending',
            'paid' => 'paid',
            'approved' => 'paid',
            'expired' => 'expired',
            'cancelled' => 'refunded',
            'refunded' => 'refunded',
            'failed' => 'refunded',
        ];
        
        return [
            'success' => true,
            'status' => $statusMap[$tx['status'] ?? 'pending'] ?? 'pending',
            'payment' => $tx,
        ];
    }
    
    /**
     * Processa webhook da BuckPay
     * Eventos: transaction.created, transaction.processed
     */
    public function processWebhook(array $payload, string $signature): array {
        // TODO: Validar assinatura HMAC conforme documentação da BuckPay
        // $expectedSignature = hash_hmac('sha256', json_encode($payload), $this->config['buck']['webhook_secret']);
        // if (!hash_equals($expectedSignature, $signature)) {
        //     return ['success' => false, 'error' => 'Assinatura inválida'];
        // }
        
        $event = $payload['event'] ?? '';
        $data = $payload['data'] ?? [];
        $txId = $data['id'] ?? '';
        $externalId = $data['external_id'] ?? '';
        $status = $data['status'] ?? '';
        
        return [
            'success' => true,
            'event' => $event,
            'transaction_id' => $txId,
            'external_id' => $externalId,
            'status' => $status,
            'raw' => $payload,
        ];
    }
    
    /**
     * Faz requisição HTTP para API BuckPay
     */
    private function request(string $method, string $endpoint, array $body = []): array {
        $url = $this->baseUrl . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'User-Agent: ' . $this->userAgent,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => 'Erro de conexão: ' . $error];
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $detail = $data['error']['detail'] ?? $data['error']['message'] ?? $data['message'] ?? $data['detail'] ?? 'Erro na API BuckPay';
            return [
                'success' => false,
                'error' => $detail,
                'http_code' => $httpCode,
            ];
        }
        
        return ['success' => true, 'data' => $data];
    }
}