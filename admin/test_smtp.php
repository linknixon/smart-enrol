<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/auth.php';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../form-one/tcpdf/tcpdf.php';
require_once __DIR__ . '/../form-one/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized session. Please log in first.']);
    exit;
}

$currentRole = $_SESSION['admin_role'] ?? '';
if (!hasPermission($pdo, $currentRole, 'can_manage_settings')) {
    echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Administrators can test SMTP configurations.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. POST required.']);
    exit;
}

$host       = trim($_POST['smtp_host'] ?? getSetting($pdo, 'smtp_host', 'mail-gw.ncedges.com'));
$port       = (int)($_POST['smtp_port'] ?? getSetting($pdo, 'smtp_port', 587));
$encryption = trim(strtolower($_POST['smtp_encryption'] ?? getSetting($pdo, 'smtp_encryption', 'tls')));
$username   = trim($_POST['smtp_username'] ?? getSetting($pdo, 'smtp_username', ''));
$password   = trim($_POST['smtp_password'] ?? getSetting($pdo, 'smtp_password', ''));
$fromEmail  = trim($_POST['smtp_from_email'] ?? getSetting($pdo, 'smtp_from_email', ''));
$fromName   = trim($_POST['smtp_from_name'] ?? getSetting($pdo, 'smtp_from_name', 'Admissions Office'));
$testTo     = trim($_POST['test_recipient'] ?? '');

if (empty($testTo) || !filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid test recipient email address.']);
    exit;
}

if (empty($host)) {
    echo json_encode(['success' => false, 'message' => 'SMTP Host server address is required.']);
    exit;
}

if (empty($fromEmail)) {
    $fromEmail = !empty($username) && filter_var($username, FILTER_VALIDATE_EMAIL) ? $username : 'admissions@school.sc.ug';
}

$schoolName   = getSetting($pdo, 'school_name', 'Online Admissions System');
$primaryColor = getPrimaryColor($pdo);
$accentColor  = getAccentColor($pdo);

$mail = new PHPMailer(true);

try {
    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->isSMTP();
    $mail->Host       = $host;
    $mail->SMTPAuth   = !empty($username);
    $mail->Username   = $username;
    $mail->Password   = $password;
    
    if ($encryption === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($encryption === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;
    }

    $mail->Port       = $port;
    $mail->Timeout    = 15; // 15 second timeout

    // Optional self-signed certificate support for local testing/cPanel servers
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($testTo);
    $mail->isHTML(true);
    $mail->Subject = "SMTP Test Verification - " . $schoolName;

    // Attach logo if available
    $logoPath = getSchoolLogoFilePath($pdo, realpath(__DIR__ . '/..'));
    $hasLogo = false;
    if ($logoPath && file_exists($logoPath)) {
        try {
            $mail->addEmbeddedImage($logoPath, 'school_logo', basename($logoPath));
            $hasLogo = true;
        } catch (Exception $e) {}
    }
    $logoHtml = $hasLogo ? "<table role='presentation' border='0' cellpadding='0' cellspacing='0' align='center' style='margin: 0 auto 12px auto; text-align: center; border-collapse: collapse;'><tr><td align='center' style='text-align: center; background: transparent; background-color: transparent;'><img src='cid:school_logo' alt='Logo' style='display: block; margin: 0 auto; max-height: 70px; max-width: 150px; height: auto; width: auto; background-color: transparent; background: transparent; border: 0; outline: none; object-fit: contain;'></td></tr></table>" : "";

    $timestamp = date('d-M-Y H:i:s T');
    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <link rel='preconnect' href='https://fonts.googleapis.com'>
    <link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
    <link href='https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap' rel='stylesheet'>
    <style>
    body, div, p, span, h1, h2, h3, h4, th, td, a, code {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
    }
    code { font-family: monospace !important; }
    </style>
    </head>
    <body style='margin: 0; padding: 0; background-color: #f8fafc;'>
    <div style=\"font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 30px auto; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; background: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.06);\">
        <div style='background: {$primaryColor}; color: #ffffff; padding: 25px; text-align: center; border-bottom: 3px solid {$accentColor};'>
            {$logoHtml}
            <h2 style='margin: 0; font-size: 18px; text-transform: uppercase; letter-spacing: 0.5px;'>{$schoolName}</h2>
            <p style='margin: 6px 0 0; font-size: 11px; color: {$accentColor}; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px;'>SMTP Email Gateway Test Successful</p>
        </div>
        <div style='padding: 28px; color: #1e293b; font-size: 14px; line-height: 1.6;'>
            <p>Hello Administrator,</p>
            <p>This automated verification confirms that your SMTP mail gateway for <strong>{$schoolName}</strong> is properly configured, authenticated, and operational.</p>
            <div style='background: #f8fafc; border: 1px solid #cbd5e1; border-left: 4px solid {$accentColor}; border-radius: 8px; padding: 16px; font-size: 12.5px; margin: 18px 0;'>
                <strong style='color: {$primaryColor};'>Connection Diagnostics:</strong><br>
                • SMTP Host: <code>{$host}</code><br>
                • Port: <code>{$port}</code><br>
                • Encryption: <code>" . strtoupper($encryption ?: 'None') . "</code><br>
                • Sender: <code>{$fromEmail} ({$fromName})</code><br>
                • Test Recipient: <code>{$testTo}</code><br>
                • System Timestamp: <code>{$timestamp}</code>
            </div>
            <p style='color: #64748b; font-size: 12px;'>All official admissions correspondence (application confirmations, admission offer letters, and status updates) will be dispatched through this gateway.</p>
        </div>
        <div style='background: #f8fafc; padding: 14px; text-align: center; color: #94a3b8; font-size: 11px; border-top: 1px solid #e2e8f0;'>
            Sent by Admissions Management System • {$schoolName}
        </div>
    </div>
    </body>
    </html>";

    $mail->send();

    logActivity($pdo, $_SESSION['admin_username'], "SMTP Test", "Successfully verified SMTP connection to {$host}:{$port} and dispatched test email to {$testTo}");

    echo json_encode([
        'success' => true,
        'message' => "Success! SMTP connection established and test verification email successfully delivered to {$testTo}."
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => "SMTP Connection Failed: " . $mail->ErrorInfo
    ]);
}
