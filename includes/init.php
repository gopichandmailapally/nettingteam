<?php
/**
 * NettingTeam Core Bootstrap & Resilience Engine
 * Loads site configuration, data dictionaries, WhatsApp helper, and security headers.
 */

if (!defined('GCM_INIT_LOADED')) {
    define('GCM_INIT_LOADED', true);

    // 1. Timezone & Locale
    date_default_timezone_set('Asia/Kolkata');

    // 2. Global Site Constants
    if (!defined('SITE_NAME'))        define('SITE_NAME', 'NettingTeam');
    if (!defined('SITE_DOMAIN'))      define('SITE_DOMAIN', 'nettingteam.com');
    if (!defined('SITE_URL'))         define('SITE_URL', 'https://nettingteam.com');
    if (!defined('PRIMARY_PHONE'))    define('PRIMARY_PHONE', '+919912399334');
    if (!defined('WHATSAPP_NUMBER'))  define('WHATSAPP_NUMBER', '919912399334');
    if (!defined('PRIMARY_EMAIL'))    define('PRIMARY_EMAIL', 'support@nettingteam.com');

    // 3. Load Data Dictionary if exists
    $dataFile = dirname(__DIR__) . '/_data.php';
    if (file_exists($dataFile)) {
        require_once $dataFile;
    }

    // 4. Load Smart Dynamic WhatsApp Helper
    $waHelper = __DIR__ . '/whatsapp_helper.php';
    if (file_exists($waHelper)) {
        require_once $waHelper;
    }

    // 5. Global HTML Escaping Helper
    if (!function_exists('h')) {
        function h($str) {
            return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
        }
    }
}
