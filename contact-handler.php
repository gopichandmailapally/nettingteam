<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/mail-config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$enquiry_id = 'NT' . date('ymd') . strtoupper(substr(md5(uniqid()), 0, 6));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$form_type    = isset($_POST['form_type']) ? $_POST['form_type'] : 'contact';
$timestamp    = date('d-M-Y h:i:s A');
$ip           = $_SERVER['REMOTE_ADDR'];

$name         = '';
$email        = '';
$phone        = '';
$service      = '';
$location     = '';
$message      = '';
$has_whatsapp = false;
$redirect_page = 'index.php';

// ── Quick Contact ──────────────────────────────────────────────────────────────
if ($form_type === 'quick_contact') {
    $name         = isset($_POST['full_name']) ? trim(strip_tags($_POST['full_name'])) : '';
    $phone        = isset($_POST['mobile'])    ? trim(strip_tags($_POST['mobile']))    : '';
    $has_whatsapp = !empty($_POST['has_whatsapp']) && $_POST['has_whatsapp'] === '1';
    $redirect_page = 'index.php';

    if (empty($name) || empty($phone)) {
        header('Location: ' . $redirect_page . '?status=error&msg=' . urlencode('Please fill all required fields'));
        exit;
    }
    $subject = '[URGENT] Quick Quote #' . $enquiry_id . ' - ' . $name . ' - ' . date('d/m H:i');

// ── Full Contact ───────────────────────────────────────────────────────────────
} else {
    $name         = isset($_POST['name'])     ? trim(strip_tags($_POST['name']))     : '';
    $email        = isset($_POST['email'])    ? trim(strip_tags($_POST['email']))    : '';
    $phone        = isset($_POST['phone'])    ? trim(strip_tags($_POST['phone']))    : '';
    $service      = isset($_POST['service'])  ? trim(strip_tags($_POST['service']))  : 'Not specified';
    $location     = isset($_POST['location']) ? trim(strip_tags($_POST['location'])) : 'Not specified';
    $message      = isset($_POST['message'])  ? trim(strip_tags($_POST['message']))  : '';
    $has_whatsapp = !empty($_POST['has_whatsapp']) && $_POST['has_whatsapp'] === '1';
    $redirect_page = 'contact.php';

    $errors = [];
    if (empty($name))                                   $errors[] = 'Name is required';
    if (empty($email))                                  $errors[] = 'Email is required';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    if (empty($phone))                                  $errors[] = 'Phone number is required';

    if (!empty($errors)) {
        header('Location: ' . $redirect_page . '?status=error&msg=' . urlencode(implode(', ', $errors)));
        exit;
    }

    $service_labels = [
        'balcony-safety-nets' => 'Balcony Safety Nets',
        'bird-nets'           => 'Bird Nets',
        'pigeon-nets'         => 'Pigeon Nets',
        'sports-nets'         => 'Sports Nets',
        'invisible-grills'    => 'Invisible Grills',
        'cloth-hangers'       => 'Cloth Hangers',
        'other'               => 'Other',
    ];
    $service = isset($service_labels[$service]) ? $service_labels[$service] : $service;

    $subject = 'New Enquiry #' . $enquiry_id . ' - ' . $name . ' - ' . $service . ' - ' . date('d/m H:i');
}

// ── Helpers ────────────────────────────────────────────────────────────────────
$phone_clean = preg_replace('/\D/', '', $phone);
$h_name      = htmlspecialchars($name);
$h_phone     = htmlspecialchars($phone);
$h_email     = htmlspecialchars($email);
$h_service   = htmlspecialchars($service);
$h_location  = htmlspecialchars($location);
$h_message   = nl2br(htmlspecialchars($message));
$wa_pretext  = rawurlencode('Hi ' . $name . ', this is NettingTeam regarding your enquiry #' . $enquiry_id . '. How can we help you?');

// ── Action buttons ─────────────────────────────────────────────────────────────
$btn_call  = '<a href="tel:+91' . $phone_clean . '" style="display:inline-block;background:#1e3a8a;color:#ffffff;padding:12px 22px;border-radius:8px;text-decoration:none;font-size:15px;font-weight:700;margin:4px 4px 4px 0">&#128222; Call ' . $h_phone . '</a>';
$btn_wa    = $has_whatsapp
    ? '<a href="https://wa.me/91' . $phone_clean . '?text=' . $wa_pretext . '" style="display:inline-block;background:#25d366;color:#ffffff;padding:12px 22px;border-radius:8px;text-decoration:none;font-size:15px;font-weight:700;margin:4px 4px 4px 0">&#128172; WhatsApp ' . $h_phone . '</a>'
    : '';
$btn_email = !empty($email)
    ? '<a href="mailto:' . $h_email . '" style="display:inline-block;background:#374151;color:#ffffff;padding:12px 22px;border-radius:8px;text-decoration:none;font-size:15px;font-weight:700;margin:4px 0">&#9993; Reply by Email</a>'
    : '';
$wa_badge  = $has_whatsapp
    ? '<p style="margin:12px 0 0;padding:10px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;font-size:13px;color:#16a34a;font-weight:600">&#10003; WhatsApp confirmed active on this number</p>'
    : '<p style="margin:12px 0 0;padding:10px 14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;color:#9ca3af">Customer did not confirm WhatsApp</p>';

// ── Table rows ─────────────────────────────────────────────────────────────────
$header_bg = $form_type === 'quick_contact' ? '#dc2626' : '#1e3a8a';
$rows = '';
if ($form_type === 'quick_contact') {
    $rows .= '<tr><td style="padding:10px 14px;background:#f9fafb;font-size:12px;font-weight:700;color:#6b7280;width:32%;border-bottom:1px solid #e5e7eb">NAME</td><td style="padding:10px 14px;background:#f9fafb;font-size:15px;font-weight:700;color:#111827;border-bottom:1px solid #e5e7eb">' . $h_name . '</td></tr>';
    $rows .= '<tr><td style="padding:10px 14px;font-size:12px;font-weight:700;color:#6b7280;border-bottom:1px solid #e5e7eb">MOBILE</td><td style="padding:10px 14px;font-size:15px;font-weight:700;border-bottom:1px solid #e5e7eb"><a href="tel:+91' . $phone_clean . '" style="color:#dc2626;text-decoration:none">' . $h_phone . '</a></td></tr>';
} else {
    $rows .= '<tr><td style="padding:10px 14px;background:#f9fafb;font-size:12px;font-weight:700;color:#6b7280;width:32%;border-bottom:1px solid #e5e7eb">NAME</td><td style="padding:10px 14px;background:#f9fafb;font-size:15px;font-weight:700;color:#111827;border-bottom:1px solid #e5e7eb">' . $h_name . '</td></tr>';
    $rows .= '<tr><td style="padding:10px 14px;font-size:12px;font-weight:700;color:#6b7280;border-bottom:1px solid #e5e7eb">MOBILE</td><td style="padding:10px 14px;font-size:15px;font-weight:700;border-bottom:1px solid #e5e7eb"><a href="tel:+91' . $phone_clean . '" style="color:#dc2626;text-decoration:none">' . $h_phone . '</a></td></tr>';
    if (!empty($email)) {
        $rows .= '<tr><td style="padding:10px 14px;background:#f9fafb;font-size:12px;font-weight:700;color:#6b7280;border-bottom:1px solid #e5e7eb">EMAIL</td><td style="padding:10px 14px;background:#f9fafb;font-size:14px;border-bottom:1px solid #e5e7eb"><a href="mailto:' . $h_email . '" style="color:#1e3a8a;text-decoration:none">' . $h_email . '</a></td></tr>';
    }
    $rows .= '<tr><td style="padding:10px 14px;font-size:12px;font-weight:700;color:#6b7280;border-bottom:1px solid #e5e7eb">SERVICE</td><td style="padding:10px 14px;font-size:14px;border-bottom:1px solid #e5e7eb">' . $h_service . '</td></tr>';
    $rows .= '<tr><td style="padding:10px 14px;background:#f9fafb;font-size:12px;font-weight:700;color:#6b7280">LOCATION</td><td style="padding:10px 14px;background:#f9fafb;font-size:14px">' . $h_location . '</td></tr>';
}

$msg_block = (!empty($message) && $form_type !== 'quick_contact')
    ? '<tr><td colspan="2" style="padding:16px 32px 0"><h3 style="margin:0 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:1px;color:#6b7280">Message</h3><div style="background:#f8fafc;border-left:4px solid ' . $header_bg . ';padding:14px 16px;border-radius:0 8px 8px 0;font-size:14px;line-height:1.7;color:#374151">' . $h_message . '</div></td></tr>'
    : '';

// ── HTML email body ────────────────────────────────────────────────────────────
$header_title = $form_type === 'quick_contact' ? 'URGENT - Quick Quote Request' : 'New Customer Enquiry';
$header_sub   = $form_type === 'quick_contact' ? 'Action Required: Call Back Immediately!' : 'NettingTeam Website Contact Form';

$html_body = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:20px 0">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.10)">
  <tr><td style="background:' . $header_bg . ';padding:24px 32px;text-align:center">
    <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:800">' . $header_title . '</h1>
    <p style="margin:6px 0 0;color:rgba(255,255,255,0.85);font-size:13px">' . $header_sub . '</p>
  </td></tr>
  <tr><td style="background:' . $header_bg . '22;padding:12px 32px;text-align:center;border-bottom:1px solid #e5e7eb">
    <span style="background:' . $header_bg . ';color:#ffffff;padding:5px 16px;border-radius:50px;font-size:12px;font-weight:700;letter-spacing:1px">ID: ' . $enquiry_id . '</span>
  </td></tr>
  <tr><td style="padding:24px 32px 16px">
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">' . $rows . '</table>
    ' . $wa_badge . '
  </td></tr>
  ' . $msg_block . '
  <tr><td style="padding:20px 32px 28px;text-align:center;border-top:1px solid #f3f4f6">
    <p style="margin:0 0 12px;font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:1px">Respond Now</p>
    ' . $btn_call . $btn_wa . $btn_email . '
  </td></tr>
  <tr><td style="background:#f9fafb;padding:12px 32px;border-top:1px solid #e5e7eb">
    <table width="100%" cellpadding="0" cellspacing="0"><tr>
      <td style="font-size:11px;color:#9ca3af">Submitted: ' . $timestamp . '</td>
      <td style="font-size:11px;color:#9ca3af;text-align:right">IP: ' . $ip . '</td>
    </tr></table>
  </td></tr>
  <tr><td style="background:#1e293b;padding:14px 32px;text-align:center">
    <p style="margin:0;color:rgba(255,255,255,0.5);font-size:11px">Automated message from <strong style="color:#ffffff">NettingTeam</strong> website</p>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>';

// ── Send via PHPMailer + Gmail SMTP ────────────────────────────────────────────
$mail = new PHPMailer(true);
$mail_sent = false;
$error_msg = '';

try {
    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = MAIL_PORT;

    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    $mail->addAddress(MAIL_RECIPIENT);
    if (!empty($email)) {
        $mail->addReplyTo($email, $name);
    }

    $mail->isHTML(true);
    $mail->CharSet  = 'UTF-8';
    $mail->Subject  = $subject;
    $mail->Body     = $html_body;
    $mail->AltBody  = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html_body));

    $mail->send();
    $mail_sent = true;

} catch (Exception $e) {
    $error_msg = $mail->ErrorInfo;
}

// ── Log ────────────────────────────────────────────────────────────────────────
$log_file  = __DIR__ . '/enquiries.log';
$log_entry = '[' . date('Y-m-d H:i:s') . '] ID:' . $enquiry_id
           . ' | Type:'  . $form_type
           . ' | Name:'  . $name
           . ' | Phone:' . $phone
           . ' | Email:' . ($email ?: 'N/A')
           . ' | WA:'    . ($has_whatsapp ? 'YES' : 'NO')
           . ' | Sent:'  . ($mail_sent ? 'YES' : 'NO')
           . ($error_msg ? ' | Error:' . $error_msg : '')
           . "\n";
@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

// ── Redirect ───────────────────────────────────────────────────────────────────
if ($mail_sent) {
    if ($form_type === 'quick_contact') {
        header('Location: ' . $redirect_page . '?status=success&type=quick&msg=' . urlencode('Thank you, ' . $name . '! We will call you within 2 minutes.') . '&id=' . $enquiry_id);
    } else {
        header('Location: ' . $redirect_page . '?status=success&type=contact&msg=' . urlencode('Thank you, ' . $name . '! Your enquiry #' . $enquiry_id . ' has been received. We will contact you within 24 hours.') . '&id=' . $enquiry_id);
    }
} else {
    header('Location: ' . $redirect_page . '?status=error&msg=' . urlencode('There was an issue sending your message. Please call us directly at 9912399334.'));
}
exit;
?>
