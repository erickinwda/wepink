<?php
/* backend-api/config/buck.php
   TODOS os segredos vêm de variáveis de ambiente. NADA é hard-coded aqui. */

declare(strict_types=1);

require_once __DIR__ . '/../api/_helpers.php';

$kit1 = (int) round((float) env_optional('KIT_1_PRICE', '34.90') * 100);
$kit2 = (int) round((float) env_optional('KIT_2_PRICE', '58.80') * 100);

function getBuckConfig(): array
{
    static $config = null;
    if ($config === null) {
        $config = [
            'buck' => [
                'base_url' => env_required('BUCK_BASE_URL'),
                'access_token' => env_required('BUCK_ACCESS_TOKEN'),
                'user_agent' => env_required('BUCK_USER_AGENT'),
                'webhook_secret' => env_required('BUCK_WEBHOOK_SECRET'),
                'environment' => env_optional('BUCK_ENVIRONMENT', 'production'),
                'signature_header' => env_optional('BUCK_WEBHOOK_SIGNATURE_HEADER', 'X-Buck-Signature'),
            ],

            'checkout' => [
                'webhook_url' => env_required('CHECKOUT_WEBHOOK_URL'),
                'pix_expiration_minutes' => (int) env_optional('PIX_EXPIRATION_MINUTES', '15'),
                'allowed_kits' => ['1', '2'],
            ],

            'products' => [
                '1' => [
                    'id' => '1',
                    'external_id_prefix' => 'kit1_',
                    'name' => 'Kit Body Splash',
                    'price_cents' => $kit1,
                    'units' => 1,
                    'description' => 'Kit de 1 Body Splash WePink',
                ],
                '2' => [
                    'id' => '2',
                    'external_id_prefix' => 'kit2_',
                    'name' => 'Kit 2 Body Splash',
                    'price_cents' => $kit2,
                    'units' => 2,
                    'description' => 'Kit de 2 Body Splash WePink',
                ],
            ],

            'facebook' => [
                'pixel_id' => env_optional('FB_PIXEL_ID'),
                'access_token' => env_optional('FB_ACCESS_TOKEN'),
                'test_event_code' => env_optional('FB_TEST_EVENT_CODE'),
            ],
        ];
    }
    return $config;
}

return getBuckConfig();