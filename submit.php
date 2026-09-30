<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

function out(bool $ok, string $msg, int $code = 200): never {
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(false, 'Invalid request.', 405);

// Honeypot: real users never fill this in. Pretend success to bots.
if (!empty($_POST['website'])) out(true, 'Thanks! Please check your email to confirm.');

$name = trim((string)($_POST['name'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$business = trim((string)($_POST['business'] ?? ''));
$desc = trim((string)($_POST['description'] ?? ''));
$consent = isset($_POST['marketing_consent']) ? 1 : 0;

if ($name === '' || mb_strlen($name) > 120) out(false, 'Please enter your name.', 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) out(false, 'Please enter a valid email address.', 422);
if ($business === '' || mb_strlen($business) > 160) out(false, 'Please enter your business name.', 422);
if ($desc === '' || mb_strlen($desc) > 500) out(false, 'Please add a short description (500 characters max).', 422);

$c = cfg();
$ipHash = hash('sha256', $c['ip_salt'] . ($_SERVER['REMOTE_ADDR'] ?? ''));
$pdo = db();

$rl = $pdo->prepare('SELECT COUNT(*) FROM signups WHERE ip_hash = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
$rl->execute([$ipHash]);
if ((int)$rl->fetchColumn() >= 5) out(false, 'Too many attempts. Please try again later.', 429);

$existing = $pdo->prepare('SELECT token, confirmed_at FROM signups WHERE email = ?');
$existing->execute([$email]);
$row = $existing->fetch();

if ($row) {
    // Same response either way, so the form can't be used to check who has signed up.
    if ($row['confirmed_at'] === null) $token = $row['token'];
    else out(true, 'Thanks! Please check your email to confirm.');
} else {
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO signups (name, email, business, description, marketing_consent, consent_text, ip_hash, token)
                   VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$name, $email, $business, $desc, $consent, $consent ? $c['consent_text'] : '', $ipHash, $token]);
}

$link = rtrim($c['site_url'], '/') . '/confirm.php?t=' . $token;
$safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeBiz = htmlspecialchars($business, ENT_QUOTES, 'UTF-8');

$html = <<<HTML
<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Confirm your listing</title>
</head>
<body style="margin:0;padding:0;background-color:#EFF3E7;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#EFF3E7;">
<tr><td align="center" style="padding:32px 16px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background-color:#FBF8F1;border:1px solid #E4DCC8;border-radius:16px;overflow:hidden;">
<tr><td style="background-color:#2F4D3A;height:6px;line-height:6px;font-size:1px;">&nbsp;</td></tr>
<tr><td align="center" style="padding:36px 40px 8px;">
<img src="cid:logo" width="56" height="56" alt="Coton in the Elms Business Register" style="display:block;border-radius:50%;margin:0 auto 16px;">
<h1 style="margin:0;font-family:Georgia,'Times New Roman',serif;font-size:22px;line-height:1.3;color:#2F4D3A;font-weight:700;">Coton in the Elms Business Register</h1>
</td></tr>
<tr><td style="padding:8px 40px 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#26291F;">
<p style="margin:0 0 16px;">Hi {$safeName},</p>
<p style="margin:0 0 16px;">Thanks for registering <strong>{$safeBiz}</strong> with the Coton in the Elms business register. Please confirm your email address to complete your listing:</p>
</td></tr>
<tr><td align="center" style="padding:8px 40px 24px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0">
<tr><td style="border-radius:8px;background-color:#2F4D3A;">
<a href="{$link}" style="display:inline-block;padding:13px 30px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:8px;">Confirm my email</a>
</td></tr>
</table>
</td></tr>
<tr><td style="padding:0 40px 32px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.6;color:#62644F;">
<p style="margin:0 0 8px;">Or paste this link into your browser:<br><a href="{$link}" style="color:#5E7C86;word-break:break-all;">{$link}</a></p>
<p style="margin:0;">If you didn't request this, you can ignore this email. Nothing further will happen.</p>
</td></tr>
<tr><td style="border-top:1px solid #E4DCC8;font-size:1px;line-height:1px;">&nbsp;</td></tr>
<tr><td align="center" style="padding:20px 40px 28px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#62644F;">
<p style="margin:0 0 4px;">This is an automated message from the Coton in the Elms Business Register.</p>
<p style="margin:0;">Site by <a href="https://bssweb.co.uk" style="color:#8A6A46;text-decoration:none;font-weight:bold;">Brooks Software Solutions</a></p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;

try {
    send_mail($email, $c['confirm_email_subject'], $html, ['logo' => __DIR__ . '/apple-touch-icon.png']);
} catch (Throwable $e) {
    error_log('Mail failed: ' . $e->getMessage());
}

out(true, 'Thanks! Please check your email to confirm.');
