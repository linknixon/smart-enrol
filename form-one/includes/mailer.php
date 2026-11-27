<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

if (!function_exists('sendEmail')) {
    function sendEmail($to, $subject, $body, $attachments = []) {
        global $pdo;

        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $enabled = $pdo ? getSetting($pdo, 'smtp_enabled', '1') : '1';
        if ($enabled === '0' || $enabled === 'false') {
            // SMTP disabled
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();

            $host       = $pdo ? getSetting($pdo, 'smtp_host', 'mail-gw.ncedges.com') : 'mail-gw.ncedges.com';
            $port       = (int)($pdo ? getSetting($pdo, 'smtp_port', 587) : 587);
            $encryption = strtolower($pdo ? getSetting($pdo, 'smtp_encryption', 'tls') : 'tls');
            $username   = $pdo ? getSetting($pdo, 'smtp_username', 'admissions-mutoleress@ncedges.com') : 'admissions-mutoleress@ncedges.com';
            $password   = $pdo ? getSetting($pdo, 'smtp_password', 'Uganda@23') : 'Uganda@23';
            
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
            $mail->Timeout    = 15;

            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Dynamic sender name from school settings
            $fromName = 'Admissions Office';
            if ($pdo) {
                $configuredFromName = getSetting($pdo, 'smtp_from_name', '');
                if (!empty($configuredFromName)) {
                    $fromName = $configuredFromName;
                } else {
                    $sName = getSetting($pdo, 'school_short_name', getSetting($pdo, 'school_name', ''));
                    if (!empty($sName)) {
                        $fromName = $sName . ' Admissions';
                    }
                }
            }

            $fromEmail = $pdo ? getSetting($pdo, 'smtp_from_email', $username) : $username;
            if (empty($fromEmail)) {
                $fromEmail = $username;
            }

            $mail->setFrom($fromEmail, $fromName);

            // Add reply-to if school email is configured
            if ($pdo) {
                $schoolEmail = getSetting($pdo, 'school_email', '');
                if (!empty($schoolEmail) && filter_var($schoolEmail, FILTER_VALIDATE_EMAIL)) {
                    $mail->addReplyTo($schoolEmail, $fromName);
                }
            }

            $mail->addAddress($to);

            $cleanSubject = str_replace(["\xe2\x80\x93", "–", "â€“"], "-", $subject);
            $cleanBody    = str_replace(["\xe2\x80\x93", "–", "â€“"], "-", $body);

            // Render official school logo directly on the template via public absolute URL
            // (Avoids attaching the logo as a downloadable MIME file attachment in Gmail, Apple Mail, Outlook, etc.)
            if ($pdo && strpos($cleanBody, 'cid:school_logo') !== false) {
                $webLogo = getSchoolLogoSrc($pdo);
                if (is_string($webLogo) && strpos($webLogo, 'http') === false) {
                    $webLogo = getBaseUrl($pdo) . '/' . ltrim($webLogo, '/');
                }
                $cleanBody = str_replace('cid:school_logo', (string)$webLogo, $cleanBody);
            }

            $mail->CharSet  = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->isHTML(true);
            $mail->Subject  = $cleanSubject;
            $mail->Body     = $cleanBody;

            // Attach files or in-memory documents if provided
            if (!empty($attachments) && is_array($attachments)) {
                foreach ($attachments as $att) {
                    if (is_string($att) && file_exists($att)) {
                        $mail->addAttachment($att);
                    } elseif (is_array($att)) {
                        if (!empty($att['data'])) {
                            $attName = $att['name'] ?? 'Admission_Offer.pdf';
                            $attEncoding = $att['encoding'] ?? 'base64';
                            $attMime = $att['mime'] ?? 'application/pdf';
                            $mail->addStringAttachment($att['data'], $attName, $attEncoding, $attMime);
                        } elseif (!empty($att['path']) && file_exists($att['path'])) {
                            $attName = $att['name'] ?? basename($att['path']);
                            $mail->addAttachment($att['path'], $attName);
                        }
                    }
                }
            }

            $mail->send();
            return true;
        } catch (\Throwable $e) {
            error_log("Admissions Mailer Error: " . $e->getMessage());
            return false;
        }
    }
}
