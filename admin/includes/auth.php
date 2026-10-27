<?php
/**
 * NettingTeam Admin Authentication System
 * - 30-Day Device Trust (No OTP required for 30 days on trusted devices)
 * - 2-Hour Inactivity Auto-Logout (Requires password login after 120 minutes of idle time)
 * - Multi-Device Session Management & Remote Logout (Logout single device or all devices)
 */

define('DEVICE_COOKIE', 'gcm_td');
define('DEVICE_EXPIRY', 30 * 86400);            // 30 days in seconds
define('SESSION_INACTIVITY_TIMEOUT', 7200);     // 2 hours in seconds (120 minutes)

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 86400);   // 24 hours server session retention
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 86400,                // 24 hours cookie lifetime so PHP doesn't expire early
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params(86400, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
    }
    session_start();
}

// ─── Session data helpers ─────────────────────────────────────────────────────

function sessionsPath() {
    return __DIR__ . '/../data/sessions.json';
}

function getSessions() {
    $p = sessionsPath();
    if (!file_exists($p)) {
        return ['otp' => null, 'trusted_devices' => [], 'active_sessions' => [], 'revoked_before' => 0, 'reset_tokens' => []];
    }
    $d = json_decode(file_get_contents($p), true);
    if (!is_array($d)) {
        return ['otp' => null, 'trusted_devices' => [], 'active_sessions' => [], 'revoked_before' => 0, 'reset_tokens' => []];
    }
    if (!isset($d['trusted_devices']) || !is_array($d['trusted_devices'])) {
        $d['trusted_devices'] = [];
    }
    if (!isset($d['active_sessions']) || !is_array($d['active_sessions'])) {
        $d['active_sessions'] = [];
    }
    if (!isset($d['revoked_before'])) {
        $d['revoked_before'] = 0;
    }
    if (!isset($d['reset_tokens']) || !is_array($d['reset_tokens'])) {
        $d['reset_tokens'] = [];
    }
    return $d;
}

function saveSessions($data) {
    $p = sessionsPath();
    $dir = dirname($p);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    file_put_contents($p, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ─── Device identification & fingerprinting ───────────────────────────────────

function deviceLabel() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $os = 'Unknown Device';
    if (preg_match('/iPhone/i', $ua)) {
        $os = 'Apple iPhone';
    } elseif (preg_match('/iPad/i', $ua)) {
        $os = 'Apple iPad';
    } elseif (preg_match('/Android/i', $ua)) {
        $os = 'Android Device';
    } elseif (preg_match('/Macintosh|Mac OS X/i', $ua)) {
        $os = 'Mac (macOS)';
    } elseif (preg_match('/Windows NT 10/i', $ua)) {
        $os = 'Windows 10/11';
    } elseif (preg_match('/Windows/i', $ua)) {
        $os = 'Windows PC';
    } elseif (preg_match('/Linux/i', $ua)) {
        $os = 'Linux';
    }

    $browser = 'Browser';
    if (preg_match('/Edg\/([0-9]+)/i', $ua, $m)) {
        $browser = "Edge {$m[1]}";
    } elseif (preg_match('/Chrome\/([0-9]+)/i', $ua, $m)) {
        $browser = "Chrome {$m[1]}";
    } elseif (preg_match('/Safari\/([0-9]+)/i', $ua, $m) && !preg_match('/Chrome/i', $ua)) {
        $browser = 'Safari';
    } elseif (preg_match('/Firefox\/([0-9]+)/i', $ua, $m)) {
        $browser = "Firefox {$m[1]}";
    }

    return "{$browser} on {$os}";
}

function clientIp() {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ─── 30-Day Trusted Devices ───────────────────────────────────────────────────

function isDeviceTrusted() {
    $token = $_COOKIE[DEVICE_COOKIE] ?? '';
    if (!$token || strlen($token) < 16) {
        return false;
    }

    $sess = getSessions();
    $devices = $sess['trusted_devices'] ?? [];
    $now = time();
    $matched = false;
    $updated = false;

    $validDevices = [];
    foreach ($devices as $d) {
        $created = !empty($d['created_at']) ? strtotime($d['created_at']) : 0;
        $exp = isset($d['expires_ts']) ? (int)$d['expires_ts'] : ($created > 0 ? $created + DEVICE_EXPIRY : 0);

        // Filter out expired or revoked
        if ($exp > $now && empty($d['revoked'])) {
            if (hash_equals((string)($d['token'] ?? ''), (string)$token)) {
                $d['last_used'] = date('c', $now);
                $d['ip']        = clientIp();
                $matched        = true;
                $updated        = true;
            }
            $validDevices[] = $d;
        } else {
            $updated = true; // Pruning expired entry
        }
    }

    if ($updated) {
        $sess['trusted_devices'] = $validDevices;
        saveSessions($sess);
    }

    return $matched;
}

function trustThisDevice() {
    $token = bin2hex(random_bytes(24));
    $now   = time();
    $sess  = getSessions();

    $device = [
        'id'          => 'dev_' . substr(bin2hex(random_bytes(6)), 0, 10),
        'token'       => $token,
        'label'       => deviceLabel(),
        'ip'          => clientIp(),
        'created_at'  => date('c', $now),
        'expires_at'  => date('c', $now + DEVICE_EXPIRY),
        'expires_ts'  => $now + DEVICE_EXPIRY,
        'last_used'   => date('c', $now),
        'revoked'     => false,
    ];

    // Clean up expired or duplicate tokens
    $sess['trusted_devices'] = array_values(array_filter($sess['trusted_devices'] ?? [], function($d) use ($now, $token) {
        $created = !empty($d['created_at']) ? strtotime($d['created_at']) : 0;
        $exp = isset($d['expires_ts']) ? (int)$d['expires_ts'] : ($created > 0 ? $created + DEVICE_EXPIRY : 0);
        return ($exp > $now) && empty($d['revoked']) && !hash_equals((string)($d['token'] ?? ''), (string)$token);
    }));

    $sess['trusted_devices'][] = $device;
    saveSessions($sess);

    // Set 30-day persistent cookie
    if (PHP_VERSION_ID >= 70300) {
        setcookie(DEVICE_COOKIE, $token, [
            'expires'  => $now + DEVICE_EXPIRY,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        setcookie(DEVICE_COOKIE, $token, $now + DEVICE_EXPIRY, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
    }
    $_COOKIE[DEVICE_COOKIE] = $token;

    return $device;
}

function getAllTrustedDevices() {
    $sess = getSessions();
    $now  = time();
    $trusted = [];
    foreach ($sess['trusted_devices'] ?? [] as $d) {
        $created = !empty($d['created_at']) ? strtotime($d['created_at']) : 0;
        $exp = isset($d['expires_ts']) ? (int)$d['expires_ts'] : ($created > 0 ? $created + DEVICE_EXPIRY : 0);
        if ($exp > $now && empty($d['revoked'])) {
            $trusted[] = $d;
        }
    }
    return $trusted;
}

function getAllConnectedDevices() {
    $sess = getSessions();
    $trusted = $sess['trusted_devices'] ?? [];
    $active = $sess['active_sessions'] ?? [];
    $currentToken = $_COOKIE[DEVICE_COOKIE] ?? '';
    $currentUuid = $_SESSION['gcm_session_uuid'] ?? '';
    $now = time();

    $devices = [];

    // Map trusted devices
    foreach ($trusted as $td) {
        $tok = $td['token'] ?? '';
        if (!$tok) continue;
        $created = !empty($td['created_at']) ? strtotime($td['created_at']) : 0;
        $exp = isset($td['expires_ts']) ? (int)$td['expires_ts'] : ($created > 0 ? $created + DEVICE_EXPIRY : 0);
        if ($exp <= $now || !empty($td['revoked'])) continue;

        $isCurrent = ($currentToken && hash_equals($tok, $currentToken));
        $daysRemaining = max(1, ceil(($exp - $now) / 86400));

        $devices[$tok] = [
            'token'          => $tok,
            'id'             => $td['id'] ?? ('dev_' . substr(md5($tok), 0, 8)),
            'label'          => $td['label'] ?? 'Unknown Device',
            'ip'             => $td['ip'] ?? '0.0.0.0',
            'is_current'     => $isCurrent,
            'is_trusted'     => true,
            'is_active'      => false,
            'created_at'     => $td['created_at'] ?? date('c'),
            'expires_at'     => $td['expires_at'] ?? date('c', $exp),
            'days_remaining' => $daysRemaining,
            'last_used'      => $td['last_used'] ?? ($td['created_at'] ?? date('c')),
        ];
    }

    // Merge active sessions
    foreach ($active as $as) {
        $tok = $as['device_token'] ?? '';
        $asUuid = $as['session_uuid'] ?? '';
        $isCurrent = ($currentUuid && $currentUuid === $asUuid) || ($tok && $currentToken && hash_equals($tok, $currentToken));
        $lastAct = isset($as['last_activity']) ? strtotime($as['last_activity']) : 0;
        $isActive = ($now - $lastAct) <= SESSION_INACTIVITY_TIMEOUT;

        if ($tok && isset($devices[$tok])) {
            $devices[$tok]['is_active'] = $isActive;
            if ($isCurrent) $devices[$tok]['is_current'] = true;
            if (!empty($as['last_activity'])) $devices[$tok]['last_used'] = $as['last_activity'];
        } else {
            $key = $tok ?: ('session_' . $asUuid);
            $devices[$key] = [
                'token'          => $tok,
                'session_uuid'   => $asUuid,
                'id'             => 'sess_' . substr($asUuid, 0, 8),
                'label'          => $as['label'] ?? 'Unknown Device',
                'ip'             => $as['ip'] ?? '0.0.0.0',
                'is_current'     => $isCurrent,
                'is_trusted'     => false,
                'is_active'      => $isActive,
                'created_at'     => $as['login_at'] ?? date('c'),
                'expires_at'     => null,
                'days_remaining' => 0,
                'last_used'      => $as['last_activity'] ?? date('c'),
            ];
        }
    }

    // Sort: current device first, then by last_used desc
    uasort($devices, function($a, $b) {
        if ($a['is_current'] && !$b['is_current']) return -1;
        if (!$a['is_current'] && $b['is_current']) return 1;
        return strcmp($b['last_used'] ?? '', $a['last_used'] ?? '');
    });

    return array_values($devices);
}

// ─── Device Logout & Remote Revocation ────────────────────────────────────────

function revokeTrustedDevice($token) {
    logoutDevice($token);
}

function logoutDevice($targetToken) {
    if (!$targetToken) return;
    $sess = getSessions();
    $currentToken = $_COOKIE[DEVICE_COOKIE] ?? '';

    // Revoke from trusted devices
    if (!empty($sess['trusted_devices'])) {
        foreach ($sess['trusted_devices'] as &$d) {
            if (($d['token'] ?? '') === $targetToken) {
                $d['revoked'] = true;
            }
        }
        unset($d);
        $sess['trusted_devices'] = array_values(array_filter($sess['trusted_devices'], fn($d) => empty($d['revoked'])));
    }

    // Revoke active sessions matching this device token
    if (!empty($sess['active_sessions'])) {
        foreach ($sess['active_sessions'] as &$as) {
            if (($as['device_token'] ?? '') === $targetToken) {
                $as['revoked'] = true;
            }
        }
        unset($as);
    }

    saveSessions($sess);

    // If target was this device, clear cookie and session
    if ($targetToken === $currentToken) {
        if (PHP_VERSION_ID >= 70300) {
            setcookie(DEVICE_COOKIE, '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        } else {
            setcookie(DEVICE_COOKIE, '', time() - 3600, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
        }
        doLogout();
    }
}

function logoutOtherDevices() {
    $sess = getSessions();
    $currentToken = $_COOKIE[DEVICE_COOKIE] ?? '';
    $currentUuid  = $_SESSION['gcm_session_uuid'] ?? '';

    // Retain only current device in trusted_devices
    if (!empty($sess['trusted_devices'])) {
        $sess['trusted_devices'] = array_values(array_filter($sess['trusted_devices'], function($d) use ($currentToken) {
            return $currentToken && hash_equals((string)($d['token'] ?? ''), (string)$currentToken);
        }));
    }

    // Revoke all active sessions except current session
    if (!empty($sess['active_sessions'])) {
        foreach ($sess['active_sessions'] as &$as) {
            if (($as['session_uuid'] ?? '') !== $currentUuid) {
                $as['revoked'] = true;
            }
        }
        unset($as);
        $sess['active_sessions'] = array_values(array_filter($sess['active_sessions'], function($as) use ($currentUuid) {
            return ($as['session_uuid'] ?? '') === $currentUuid;
        }));
    }

    saveSessions($sess);
}

function logoutAllDevices() {
    $sess = getSessions();
    $sess['trusted_devices'] = [];
    $sess['active_sessions'] = [];
    $sess['revoked_before']  = time();
    saveSessions($sess);

    if (PHP_VERSION_ID >= 70300) {
        setcookie(DEVICE_COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        setcookie(DEVICE_COOKIE, '', time() - 3600, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
    }
    doLogout();
}

// ─── Active Session Management ────────────────────────────────────────────────

function registerUserSession($username, $role = 'admin') {
    $now = time();
    $sessUuid = bin2hex(random_bytes(16));
    $deviceToken = $_COOKIE[DEVICE_COOKIE] ?? '';

    $_SESSION['gcm_session_uuid']   = $sessUuid;
    $_SESSION['gcm_login_time']     = $now;
    $_SESSION['gcm_last_activity']  = $now;
    $_SESSION['gcm_last_sess_sync'] = $now;
    $_SESSION['gcm_device_token']   = $deviceToken;

    $sess = getSessions();
    $cutoff = $now - SESSION_INACTIVITY_TIMEOUT;

    // Prune stale sessions older than 2 hours
    $sess['active_sessions'] = array_values(array_filter($sess['active_sessions'] ?? [], function($s) use ($cutoff) {
        $last = isset($s['last_activity']) ? strtotime($s['last_activity']) : 0;
        return $last > $cutoff && empty($s['revoked']);
    }));

    $sess['active_sessions'][] = [
        'session_uuid'  => $sessUuid,
        'device_token'  => $deviceToken,
        'user'          => $username,
        'role'          => $role,
        'ip'            => clientIp(),
        'label'         => deviceLabel(),
        'login_at'      => date('c', $now),
        'last_activity' => date('c', $now),
        'revoked'       => false,
    ];

    saveSessions($sess);
}

function updateActiveSessionActivity($sessUuid, $timestamp) {
    if (!$sessUuid) return;
    $sess = getSessions();
    $found = false;
    foreach ($sess['active_sessions'] as &$as) {
        if (($as['session_uuid'] ?? '') === $sessUuid) {
            $as['last_activity'] = date('c', $timestamp);
            $as['ip'] = clientIp();
            $found = true;
            break;
        }
    }
    unset($as);
    if ($found) saveSessions($sess);
}

function removeActiveSession($sessUuid) {
    if (!$sessUuid) return;
    $sess = getSessions();
    $sess['active_sessions'] = array_values(array_filter($sess['active_sessions'] ?? [], function($s) use ($sessUuid) {
        return ($s['session_uuid'] ?? '') !== $sessUuid;
    }));
    saveSessions($sess);
}

// ─── 2-Hour Inactivity & Remote Revocation Checker ────────────────────────────

function checkSessionInactivity() {
    if (!isLoggedIn()) {
        return;
    }

    $now = time();
    $lastActivity = $_SESSION['gcm_last_activity'] ?? null;

    // 1. Check 2-hour inactivity timeout (7200 seconds)
    if ($lastActivity !== null && ($now - $lastActivity) > SESSION_INACTIVITY_TIMEOUT) {
        $sessUuid = $_SESSION['gcm_session_uuid'] ?? null;
        if ($sessUuid) {
            removeActiveSession($sessUuid);
        }
        doLogout();
        header('Location: ' . adminUrl('login.php?timeout=2hr'));
        exit;
    }

    // 2. Check remote revocation (Logout All Devices)
    $sess = getSessions();
    $revokedBefore = $sess['revoked_before'] ?? 0;
    $loginTime = $_SESSION['gcm_login_time'] ?? 0;
    if ($revokedBefore > 0 && $loginTime < $revokedBefore) {
        doLogout();
        header('Location: ' . adminUrl('login.php?revoked=1'));
        exit;
    }

    // 3. Check individual session revocation (Logout This Device)
    $currentUuid = $_SESSION['gcm_session_uuid'] ?? '';
    if ($currentUuid && !empty($sess['active_sessions'])) {
        foreach ($sess['active_sessions'] as $as) {
            if (($as['session_uuid'] ?? '') === $currentUuid && !empty($as['revoked'])) {
                doLogout();
                header('Location: ' . adminUrl('login.php?revoked=1'));
                exit;
            }
        }
    }

    // 4. Update activity timestamp (exclude background polling endpoints)
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $isPolling = isset($_GET['is_polling']) || (strpos($uri, 'visitors_live.php') !== false);
    if (!$isPolling) {
        $_SESSION['gcm_last_activity'] = $now;
        if ($currentUuid && (!isset($_SESSION['gcm_last_sess_sync']) || ($now - $_SESSION['gcm_last_sess_sync'] > 60))) {
            updateActiveSessionActivity($currentUuid, $now);
            $_SESSION['gcm_last_sess_sync'] = $now;
        }
    }
}

// Run inactivity check
checkSessionInactivity();

// ─── OTP ─────────────────────────────────────────────────────────────────────

function generateOTP() {
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $sess = getSessions();
    $sess['otp'] = ['code' => $code, 'expires' => time() + 600]; // 10 min
    saveSessions($sess);
    return $code;
}

function verifyOTP($code) {
    $sess = getSessions();
    $otp  = $sess['otp'] ?? null;
    if (!$otp) return false;
    if (time() > ($otp['expires'] ?? 0)) {
        $sess['otp'] = null;
        saveSessions($sess);
        return false;
    }
    if (!hash_equals((string)$otp['code'], trim($code))) return false;
    $sess['otp'] = null;
    saveSessions($sess);
    return true;
}

function getOTPExpiry() {
    $otp = getSessions()['otp'] ?? null;
    return $otp ? max(0, ($otp['expires'] ?? 0) - time()) : 0;
}

function sendAdminEmail($subject, $htmlBody, $toEmail = null) {
    $mailConfig = __DIR__ . '/../../mail-config.php';
    if (file_exists($mailConfig) && !defined('MAIL_RECIPIENT')) require_once $mailConfig;

    if (!$toEmail) {
        $toEmail = defined('MAIL_RECIPIENT') ? MAIL_RECIPIENT : null;
    }
    if (!$toEmail) {
        $settings = getSettings();
        $toEmail  = $settings['auth']['admin_email'] ?? null;
    }
    if (!$toEmail) return ['ok' => false, 'msg' => 'No recipient email found in mail-config.php.'];

    $vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
    if (!file_exists($vendorAutoload)) return ['ok' => false, 'msg' => 'PHPMailer not found.'];
    require_once $vendorAutoload;

    if (!defined('MAIL_HOST')) {
        $mailConfig = __DIR__ . '/../../mail-config.php';
        if (file_exists($mailConfig)) require_once $mailConfig;
    }

    $host     = defined('MAIL_HOST')       ? MAIL_HOST       : 'smtp.gmail.com';
    $port     = defined('MAIL_PORT')       ? MAIL_PORT       : 587;
    $user     = defined('MAIL_USERNAME')   ? MAIL_USERNAME   : '';
    $pass     = defined('MAIL_PASSWORD')   ? MAIL_PASSWORD   : '';
    $fromMail = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : $user;
    $fromName = defined('MAIL_FROM_NAME')  ? MAIL_FROM_NAME  : 'NettingTeam Admin';

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Timeout    = 10;
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $port;
        $mail->setFrom($fromMail, $fromName);
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->send();
        return ['ok' => true];
    } catch (\Exception $e) {
        return ['ok' => false, 'msg' => $e->getMessage()];
    }
}

function sendOTPEmail($code) {
    $body = <<<HTML
<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#0d1b3e;margin:0;padding:30px;">
<div style="max-width:480px;margin:0 auto;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.15);border-radius:20px;padding:36px;text-align:center;">
  <div style="width:64px;height:64px;background:#c0392b;border-radius:50%;margin:0 auto 20px;display:flex;align-items:center;justify-content:center;">
    <span style="font-size:28px;">🔐</span>
  </div>
  <h2 style="color:#fff;margin:0 0 8px;">Admin Login OTP</h2>
  <p style="color:#aab;margin:0 0 28px;font-size:14px;">NettingTeam Admin Panel</p>
  <div style="background:rgba(192,57,43,0.15);border:2px solid #c0392b;border-radius:12px;padding:20px;margin-bottom:24px;">
    <div style="font-size:42px;font-weight:900;color:#fff;letter-spacing:12px;font-family:monospace;">{$code}</div>
    <div style="color:#c0392b;font-size:13px;margin-top:8px;font-weight:600;">Valid for 10 minutes only</div>
  </div>
  <p style="color:#888;font-size:12px;margin:0;">If you didn't request this, ignore this email.<br>Do not share this code with anyone.</p>
</div>
</body></html>
HTML;
    return sendAdminEmail('🔐 Your NettingTeam Admin Login OTP: ' . $code, $body);
}

// ─── Password reset tokens ────────────────────────────────────────────────────

function generateResetToken() {
    $token = bin2hex(random_bytes(32));
    $sess  = getSessions();
    $sess['reset_tokens'] = array_values(
        array_filter($sess['reset_tokens'] ?? [], fn($t) => !$t['used'] && time() < $t['expires'])
    );
    $sess['reset_tokens'][] = ['token' => $token, 'expires' => time() + 3600, 'used' => false];
    saveSessions($sess);
    return $token;
}

function verifyResetToken($token) {
    $sess = getSessions();
    foreach ($sess['reset_tokens'] ?? [] as $t) {
        if (!$t['used'] && time() < $t['expires'] && hash_equals($t['token'], $token)) {
            return true;
        }
    }
    return false;
}

function consumeResetToken($token) {
    $sess = getSessions();
    foreach ($sess['reset_tokens'] as &$t) {
        if (hash_equals($t['token'], $token)) { $t['used'] = true; break; }
    }
    unset($t);
    saveSessions($sess);
}

function sendPasswordResetEmail($token) {
    $proto = isset($_SERVER['HTTPS']) ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'nettingteam.com';
    $base  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    if (strpos($base, '/admin') === false) $base .= '/admin';
    $link  = "{$proto}://{$host}{$base}/reset.php?token=" . urlencode($token);

    $body = <<<HTML
<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#0d1b3e;margin:0;padding:30px;">
<div style="max-width:480px;margin:0 auto;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.15);border-radius:20px;padding:36px;text-align:center;">
  <h2 style="color:#fff;">Reset Your Password</h2>
  <p style="color:#aab;font-size:14px;">You requested a password reset for NettingTeam Admin Panel.</p>
  <a href="{$link}" style="display:inline-block;background:#c0392b;color:#fff;text-decoration:none;padding:14px 32px;border-radius:10px;font-weight:700;margin:20px 0;font-size:16px;">Reset Password</a>
  <p style="color:#888;font-size:12px;">This link expires in 1 hour.<br>If you didn't request this, ignore this email.</p>
  <p style="color:#555;font-size:11px;word-break:break-all;">{$link}</p>
</div></body></html>
HTML;
    return sendAdminEmail('🔑 Reset Your NettingTeam Admin Password', $body);
}

function sendUsernameEmail() {
    $settings = getSettings();
    $username = $settings['auth']['username'] ?? 'admin';

    $body = <<<HTML
<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#0d1b3e;margin:0;padding:30px;">
<div style="max-width:480px;margin:0 auto;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.15);border-radius:20px;padding:36px;text-align:center;">
  <h2 style="color:#fff;">Your Admin Username</h2>
  <p style="color:#aab;font-size:14px;">NettingTeam Admin Panel</p>
  <div style="background:rgba(192,57,43,0.15);border:2px solid #c0392b;border-radius:12px;padding:20px;margin:20px 0;">
    <div style="font-size:28px;font-weight:900;color:#fff;font-family:monospace;">{$username}</div>
  </div>
  <p style="color:#888;font-size:12px;">Keep this safe. If you also forgot your password, use the Forgot Password option.</p>
</div></body></html>
HTML;
    return sendAdminEmail('👤 Your NettingTeam Admin Username', $body);
}

// ─── Employee user helpers ────────────────────────────────────────────────────

function employeesPath() {
    return __DIR__ . '/../data/employees.json';
}
function getEmployees() {
    $p = employeesPath();
    if (!file_exists($p)) return [];
    $d = json_decode(file_get_contents($p), true);
    return $d['employees'] ?? [];
}
function saveEmployees($list) {
    file_put_contents(employeesPath(), json_encode(['employees' => $list], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
function getEmployee($id) {
    foreach (getEmployees() as $e) {
        if ($e['id'] === $id) return $e;
    }
    return null;
}
function addEmployee($data) {
    $list = getEmployees();
    $list[] = $data;
    saveEmployees($list);
}
function updateEmployee($id, $data) {
    $list = getEmployees();
    foreach ($list as &$e) {
        if ($e['id'] === $id) { $e = array_merge($e, $data); break; }
    }
    unset($e);
    saveEmployees($list);
}
function deleteEmployee($id) {
    $list = array_values(array_filter(getEmployees(), fn($e) => $e['id'] !== $id));
    saveEmployees($list);
}

// ─── Permission helpers ────────────────────────────────────────────────────────

function currentRole() {
    return $_SESSION['gcm_role'] ?? 'admin';
}
function isAdmin() {
    return currentRole() === 'admin';
}
function currentPermissions() {
    if (isAdmin()) {
        return [
            'documents'=>'full','companies'=>'full','customers'=>'full',
            'settings'=>'full','seo_stats'=>'full','employees'=>'full'
        ];
    }
    return $_SESSION['gcm_permissions'] ?? [];
}
function currentLimits() {
    if (isAdmin()) return ['max_docs_per_day'=>0,'can_delete'=>true,'can_export'=>true];
    return $_SESSION['gcm_limits'] ?? ['max_docs_per_day'=>0,'can_delete'=>false,'can_export'=>false];
}
function canAccess($module, $level = 'view') {
    if (isAdmin()) return true;
    $perms = currentPermissions();
    $p = $perms[$module] ?? 'none';
    $order = ['none'=>0,'view'=>1,'edit'=>2,'full'=>3];
    return ($order[$p] ?? 0) >= ($order[$level] ?? 1);
}
function requireAccess($module, $level = 'view') {
    requireLogin();
    if (!canAccess($module, $level)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;text-align:center;padding:4rem;"><h2 style="color:#dc2626;">Access Denied</h2><p>You do not have permission to access this page.</p><a href="index.php" style="color:#2563eb">← Back to Dashboard</a></div>');
    }
}
function currentEmployeeId() {
    return $_SESSION['gcm_employee_id'] ?? null;
}
function currentEmployeeName() {
    if (isAdmin()) {
        $settings = getSettings();
        return $settings['auth']['username'] ?? 'Admin';
    }
    return $_SESSION['gcm_employee_name'] ?? 'Employee';
}

// ─── Core auth ────────────────────────────────────────────────────────────────

function isLoggedIn() {
    return isset($_SESSION['gcm_admin']) && $_SESSION['gcm_admin'] === true;
}
function isPendingOTP() {
    return isset($_SESSION['gcm_pending_user']);
}

function requireLogin() {
    checkSessionInactivity();
    if (!isLoggedIn()) {
        header('Location: ' . adminUrl('login.php'));
        exit;
    }
}

function adminUrl($page = '') {
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''), '/');
    if (substr($base, -4) === '/api') {
        $base = substr($base, 0, -4);
    }
    if (strpos($base, '/admin') === false) {
        $base .= '/admin';
    }
    return '/' . trim($base, '/') . ($page ? '/' . ltrim($page, '/') : '');
}
function adminBase() {
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''), '/');
    if (substr($base, -4) === '/api') {
        $base = substr($base, 0, -4);
    }
    if (strpos($base, '/admin') === false) {
        $base .= '/admin';
    }
    return '/' . trim($base, '/');
}
function redirect($page) {
    header('Location: ' . adminBase() . '/' . $page);
    exit;
}

function doLogin($username, $password, $requestedTrust = false) {
    $settings = getSettings();
    $auth     = $settings['auth'] ?? [];

    // ── Check admin credentials ───────────────────────────────────
    if (($auth['username'] ?? '') === $username && password_verify($password, $auth['password'] ?? '')) {
        // If device is trusted, skip OTP!
        if (isDeviceTrusted()) {
            $_SESSION['gcm_admin'] = true;
            $_SESSION['gcm_user']  = $username;
            $_SESSION['gcm_role']  = 'admin';
            unset($_SESSION['gcm_pending_user'], $_SESSION['gcm_pending_role'], $_SESSION['gcm_pending_trust']);
            registerUserSession($username, 'admin');
            return 'ok';
        }

        // Device not yet trusted: require OTP
        $_SESSION['gcm_pending_user']  = $username;
        $_SESSION['gcm_pending_role']  = 'admin';
        $_SESSION['gcm_pending_trust'] = (bool)$requestedTrust;

        $code   = generateOTP();
        $result = sendOTPEmail($code);
        return $result['ok'] ? 'otp_sent' : ('otp_fail:' . ($result['msg'] ?? 'Email error'));
    }

    // ── Check employee credentials ────────────────────────────────
    foreach (getEmployees() as $emp) {
        if (!($emp['active'] ?? true)) continue;
        if (($emp['username'] ?? '') === $username && password_verify($password, $emp['password'] ?? '')) {
            $_SESSION['gcm_admin']         = true;
            $_SESSION['gcm_user']          = $username;
            $_SESSION['gcm_role']          = 'employee';
            $_SESSION['gcm_employee_id']   = $emp['id'];
            $_SESSION['gcm_employee_name'] = $emp['name'] ?? $username;
            $_SESSION['gcm_permissions']   = $emp['permissions'] ?? [];
            $_SESSION['gcm_limits']        = $emp['limits'] ?? [];
            unset($_SESSION['gcm_pending_user'], $_SESSION['gcm_pending_role'], $_SESSION['gcm_pending_trust']);
            registerUserSession($username, 'employee');
            return 'ok';
        }
    }

    return 'invalid';
}

function completeLogin($trustDevice = false) {
    $user = $_SESSION['gcm_pending_user'] ?? 'admin';
    $role = $_SESSION['gcm_pending_role'] ?? 'admin';
    $_SESSION['gcm_admin'] = true;
    $_SESSION['gcm_user']  = $user;
    $_SESSION['gcm_role']  = $role;

    $shouldTrust = $trustDevice || !empty($_SESSION['gcm_pending_trust']);
    unset($_SESSION['gcm_pending_user'], $_SESSION['gcm_pending_role'], $_SESSION['gcm_pending_trust']);

    if ($shouldTrust) {
        trustThisDevice();
    }
    registerUserSession($user, $role);
}

function doLogout() {
    $sessUuid = $_SESSION['gcm_session_uuid'] ?? null;
    if ($sessUuid) {
        removeActiveSession($sessUuid);
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function csrf() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
