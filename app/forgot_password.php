<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../form-one/includes/mailer.php';

$school_name        = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name  = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_motto       = getSetting($pdo, 'school_motto', 'Education for Service');
$logo_source        = getSchoolLogoSrc($pdo, '../');
$primary_color      = getPrimaryColor($pdo);
$school_website_url = getSchoolWebsiteUrl($pdo);

$error = '';
$success = '';
$devResetLink = '';
$devOtp = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $turnstileToken = $_POST['cf-turnstile-response'] ?? '';
    if (!verifyTurnstileToken($pdo, $turnstileToken)) {
        $error = "Security Verification Failed: Please complete the Cloudflare anti-bot check.";
    } else {
        $identifier = trim($_POST['identifier'] ?? '');

        if (empty($identifier)) {
            $error = "Please enter your registered staff username or email address.";
        } else {
            // Find user by username or email
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Generate secure random reset token and OTP
                $token  = bin2hex(random_bytes(32));
                $otp    = sprintf('%06d', random_int(100000, 999999));
                $expiry = date('Y-m-d H:i:s', strtotime('+30 minutes'));

                // Save to database
                $updateStmt = $pdo->prepare("UPDATE admin_users SET reset_token = ?, reset_expires_at = ?, mfa_code = ? WHERE id = ?");
                $updateStmt->execute([$token, $expiry, $otp, $user['id']]);

                // Determine base URL for reset link
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $scriptDir = dirname($_SERVER['PHP_SELF']);
                $resetUrl = $protocol . $host . rtrim($scriptDir, '/') . '/reset_password.php?token=' . urlencode($token);

                $subject = "Staff Password Reset Request - {$school_short_name}";
                $body = "
                <div style='font-family: Arial, sans-serif; max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden;'>
                    <div style='background-color: {$primary_color}; padding: 24px; text-align: center; color: #ffffff;'>
                        <h2 style='margin: 0; font-size: 18px; font-weight: bold;'>Staff Account Password Reset</h2>
                        <p style='margin: 4px 0 0; font-size: 12px; opacity: 0.9;'>{$school_name}</p>
                    </div>
                    <div style='padding: 28px; color: #334155; font-size: 14px; line-height: 1.6;'>
                        <p>Hello <strong>" . htmlspecialchars($user['firstname'] ?: $user['username']) . "</strong>,</p>
                        <p>A password reset request was received for your staff account (<code>" . htmlspecialchars($user['username']) . "</code>). Click the button below to choose a new password:</p>
                        
                        <div style='text-align: center; margin: 28px 0;'>
                            <a href='{$resetUrl}' style='display: inline-block; background-color: {$primary_color}; color: #ffffff; padding: 12px 28px; font-size: 14px; font-weight: bold; text-decoration: none; border-radius: 8px;'>Reset My Password</a>
                        </div>

                        <p style='font-size: 12px; color: #64748b;'>Alternatively, you may enter this 6-digit confirmation code on the reset page: <strong style='color: {$primary_color}; font-size: 16px;'>{$otp}</strong></p>
                        <p style='font-size: 11px; color: #94a3b8; margin-top: 24px;'>This link is valid for 30 minutes. If you did not make this request, you can safely disregard this message.</p>
                    </div>
                </div>";

                $mailSent = @sendEmail($user['email'], $subject, $body);
                logActivity($pdo, $user['username'], "Password Reset Requested", "Reset link dispatched to " . $user['email']);

                $maskedEmail = preg_replace('/(?<=.{3}).(?=.*@)/', '*', $user['email'] ?? '');
                $success = "If a staff account matches that username or email, reset instructions have been dispatched to " . (!empty($maskedEmail) ? "<b>" . htmlspecialchars($maskedEmail) . "</b>" : "your registered email") . ". Please check your inbox and spam folder.";

                // Development / localhost helper (Strictly for local environment, NEVER on live domains)
                $isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']) || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
                if ($isLocal) {
                    $devResetLink = $resetUrl;
                    $devOtp = $otp;
                }
            } else {
                // Generic response to prevent account enumeration
                $success = "If a staff account matches that username or email, reset instructions have been dispatched. Please check your inbox and spam folder.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password | <?= htmlspecialchars($school_short_name) ?> Admin Portal</title>
<link rel="icon" type="image/png" href="<?= !empty($logo_source) ? htmlspecialchars($logo_source) : '../images/default_logo.png' ?>">
<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<?= renderThemeConfig($pdo) ?>
<body class="font-sans text-slate-800 min-h-screen flex items-center justify-center p-4 bg-slate-900">

<div class="w-full max-w-md my-8">
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-2xl p-6 sm:p-8 space-y-6">
        
        <!-- Header & Logo -->
        <div class="text-center">
            <div class="inline-flex p-2 rounded-full bg-white shadow-md border-2 border-gold-500 mb-3">
                <img src="<?= htmlspecialchars($logo_source) ?>" alt="School Logo" class="w-16 h-16 object-contain">
            </div>
            <h2 class="text-xl sm:text-2xl font-extrabold text-brand-700 tracking-tight uppercase leading-tight"><?= htmlspecialchars($school_short_name) ?></h2>
            <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mt-1">Staff Password Recovery</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-800 p-3.5 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-500 text-base shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 p-3.5 rounded-xl text-xs sm:text-sm font-semibold flex items-start gap-2">
                <i class="fa-solid fa-circle-check text-emerald-500 text-base shrink-0 mt-0.5"></i>
                <div><?= $success ?></div>
            </div>

            <?php if (!empty($devResetLink)): ?>
            <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl space-y-1.5 text-xs text-amber-900">
                <div class="font-bold flex items-center gap-1.5 text-amber-800">
                    <i class="fa-solid fa-key"></i> Direct Recovery Access:
                </div>
                <p class="text-[11px] text-amber-700">You can reset immediately using the link below:</p>
                <div class="pt-1">
                    <a href="<?= htmlspecialchars($devResetLink) ?>" class="inline-block px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg text-xs transition">
                        Proceed to Reset Password ➔
                    </a>
                </div>
                <?php if (!empty($devOtp)): ?>
                <div class="text-[11px] text-amber-800 pt-1">
                    Or OTP Code: <span class="font-mono font-bold"><?= htmlspecialchars($devOtp) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" autocomplete="off" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Staff Username or Email</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <i class="fa-solid fa-envelope"></i>
                    </span>
                    <input 
                        type="text" 
                        name="identifier" 
                        class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 focus:bg-white focus:ring-4 focus:ring-brand-700/10 font-medium transition" 
                        placeholder="e.g. admin or staff@mutolere.sc.ug" 
                        required
                        autofocus
                    >
                </div>
                <p class="text-[11px] text-slate-400 mt-1.5">Enter the username or email linked to your staff profile.</p>
            </div>

            <?= renderTurnstileWidget($pdo) ?>

            <button type="submit" class="w-full py-3 bg-brand-700 hover:bg-brand-800 text-white font-bold text-sm uppercase tracking-wider rounded-xl shadow-lg shadow-brand-700/25 transition duration-150 flex items-center justify-center gap-2 mt-2">
                <span>Send Reset Link</span>
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </form>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs">
            <a href="login.php" class="text-brand-700 hover:text-brand-800 font-bold inline-flex items-center gap-1.5 transition">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Login</span>
            </a>
            <a href="../index.php" class="text-slate-400 hover:text-slate-600 transition">
                Public Portal
            </a>
        </div>

    </div>
</div>

</body>
</html>
