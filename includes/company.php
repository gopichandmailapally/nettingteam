<?php
/**
 * includes/company.php
 *
 * Loads company settings from admin/settings.json so any public page
 * can use the live address, phone, maps URL, etc.
 * Update settings once in Admin → Settings → Company Details and
 * every page on the website reflects the change automatically.
 */

if (!function_exists('gcm_company')) {
    function gcm_company(): array {
        static $cache = null;
        if ($cache !== null) return $cache;
        $file = __DIR__ . '/../admin/settings.json';
        if (!file_exists($file)) {
            $cache = [];
            return $cache;
        }
        $data = json_decode(file_get_contents($file), true);
        $cache = $data['company'] ?? [];
        return $cache;
    }
}

// Shortcut helpers ─────────────────────────────────────────────────────────
function gcm_address(): string  { return gcm_company()['address']  ?? 'Plot No.91, Road No.2, Sri Ram Nagar Colony, Karmanghat, Saroornagar - 500035, Hyderabad, Telangana'; }
function gcm_phone():   string  { return gcm_company()['phone']    ?? '9912399334'; }
function gcm_phone2():  string  { return gcm_company()['phone2']   ?? ''; }
function gcm_email():   string  { return gcm_company()['email']    ?? 'contact@nettingteam.com'; }
function gcm_maps_url():string  { return gcm_company()['maps_url'] ?? 'https://share.google/nHDBKeOvA7Pt9ozrH'; }
function gcm_name():    string  { return gcm_company()['name']     ?? 'NettingTeam'; }
function gcm_gstin():   string  { return gcm_company()['gstin']    ?? '36EBFPM5722R2ZY'; }
