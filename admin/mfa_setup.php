<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../form-one/includes/mailer.php';

$userId = $_SESSION['mfa_setup_user_id'] ?? ($_SESSION['admin_id'] ?? 0);
if ($userId <= 0) {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
$stmt->execute([$userId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    unset($_SESSION['mfa_setup_user_id']);
    header("Location: login.php");
    exit;
}

$school_name       = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$logo_source       = getSchoolLogoSrc($pdo, '../');
$primary_color     = getPrimaryColor($pdo);

// Ensure MFA columns exist in admin_users to prevent column not found errors
try {
    $chkCol = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'mfa_code'");
    if ($chkCol && $chkCol->rowCount() === 0) {
        $pdo->exec("ALTER TABLE admin_users 
            ADD mfa_enabled TINYINT DEFAULT 0,
            ADD mfa_code VARCHAR(20) DEFAULT NULL,
            ADD mfa_expires_at DATETIME DEFAULT NULL,
            ADD mfa_temp_token VARCHAR(100) DEFAULT NULL,
            ADD mfa_grace_until DATETIME DEFAULT NULL,
            ADD mfa_secret VARCHAR(100) DEFAULT NULL");
    }
} catch (Exception $e) {}

// Allow Administrator to skip MFA setup and enter dashboard directly
if (isset($_GET['skip']) && $_GET['skip'] == '1') {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_firstname'] = !empty($admin['firstname']) ? $admin['firstname'] : $admin['username'];
    $_SESSION['last_activity'] = time();
    unset($_SESSION['mfa_setup_user_id']);
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';
$dispCode = '';

// Send setup code if not already sent or expired
if (empty($admin['mfa_code']) || empty($admin['mfa_expires_at']) || strtotime($admin['mfa_expires_at']) < time() || (isset($_GET['resend']) && $_GET['resend'] == '1')) {
    $code = sprintf('%06d', random_int(100000, 999999));
    $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    try {
        $pdo->prepare("UPDATE admin_users SET mfa_code = ?, mfa_expires_at = ? WHERE id = ?")
            ->execute([$code, $expiry, $admin['id']]);
    } catch (Exception $e) {}

    $dispCode = $code;
    $subject = "Setup Two-Factor Authentication (MFA) - {$school_short_name}";
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 550px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden;'>
        <div style='background-color: {$primary_color}; padding: 24px; text-align: center; color: #ffffff;'>
            <h2 style='margin: 0; font-size: 18px; font-weight: bold;'>MFA Setup Verification</h2>
            <p style='margin: 4px 0 0; font-size: 12px; opacity: 0.9;'>{$school_name}</p>
        </div>
        <div style='padding: 28px; color: #334155; font-size: 14px; line-height: 1.6;'>
            <p>Hello <strong>" . htmlspecialchars($admin['firstname'] ?: $admin['username']) . "</strong>,</p>
            <p>To finalize setup of Two-Factor Authentication (MFA) on your staff account, enter the following 6-digit confirmation code:</p>
            <div style='background-color: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 16px; text-align: center; margin: 20px 0;'>
                <span style='font-family: monospace; font-size: 32px; font-weight: 800; letter-spacing: 6px; color: {$primary_color};'>{$code}</span>
                <p style='margin: 6px 0 0; font-size: 11px; color: #64748b;'>Valid for 15 minutes.</p>
            </div>
            <p style='font-size: 12px; color: #64748b; margin-top: 24px;'>Once confirmed, your staff account will be protected with Multi-Factor Authentication.</p>
        </div>
    </div>";

    @sendEmail($admin['email'], $subject, $body);
    $success = "A confirmation passcode has been dispatched to your email (" . htmlspecialchars(preg_replace('/(?<=.{3}).(?=.*@)/', '*', $admin['email'] ?? '')) . ").";
} else {
    $dispCode = $admin['mfa_code'] ?? '';
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enteredCode = trim($_POST['code'] ?? '');

    // Refresh user record
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
    $stmt->execute([$userId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($admin['mfa_expires_at']) || strtotime($admin['mfa_expires_at']) < time()) {
        $error = "The verification code has expired. Please click 'Resend Code'.";
    } elseif (empty($admin['mfa_code']) || $enteredCode !== $admin['mfa_code']) {
        $error = "Invalid 6-digit confirmation code. Please check your email inbox and try again.";
    } else {
        // MFA setup successful!
        $pdo->prepare("UPDATE admin_users SET mfa_enabled = 1, mfa_code = NULL, mfa_expires_at = NULL, mfa_temp_token = NULL, failed_attempts = 0 WHERE id = ?")
            ->execute([$admin['id']]);

        $wasLoggedIn = !empty($_SESSION['admin_logged_in']);

        // Complete login / refresh session
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['last_activity'] = time();

        unset($_SESSION['mfa_setup_user_id']);

        logActivity($pdo, $admin['username'], "MFA Setup Completed", "User successfully configured and activated Two-Factor Authentication");

        if ($wasLoggedIn) {
            header("Location: change_password.php?success=" . urlencode("MFA device verified and active successfully."));
            exit;
        }

        if (!empty($admin['must_change_password'])) {
            header("Location: change_password.php?must_change=1");
            exit;
        }

        header("Location: dashboard.php");
        exit;
    }
}

$maskedEmail = preg_replace('/(?<=.{3}).(?=.*@)/', '*', $admin['email'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Set Up Two-Factor Authentication | <?= htmlspecialchars($school_short_name) ?></title>
<link rel="icon" type="image/png" href="<?= !empty($logo_source) ? htmlspecialchars($logo_source) : '../images/default_logo.png' ?>">
<script src="https://cdn.tailwindcss.com" data-cfasync="false"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<?= renderThemeConfig($pdo) ?>
</head>
<body class="bg-slate-100 font-sans text-slate-800 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
        <!-- Header -->
        <div class="p-6 text-center text-white" style="background-color: <?= $primary_color ?>;">
            <div class="w-14 h-14 bg-white/10 rounded-2xl flex items-center justify-center mx-auto mb-3 border border-white/20 shadow-inner">
                <i class="fa-solid fa-shield-halved text-2xl text-gold-400"></i>
            </div>
            <h1 class="text-xl font-bold tracking-tight uppercase">Set Up Multi-Factor Authentication</h1>
            <p class="text-xs text-white/80 mt-1"><?= htmlspecialchars($school_short_name) ?> Staff Portal</p>
        </div>

        <!-- Body -->
        <div class="p-6 sm:p-8 space-y-5">
            <div class="p-3.5 bg-blue-50 border border-blue-200 rounded-xl flex items-start gap-3">
                <i class="fa-solid fa-circle-info text-blue-600 mt-0.5 text-sm"></i>
                <div class="text-xs text-blue-800">
                    <strong class="block mb-0.5 font-bold">Security Enrollment Required</strong>
                    Your account has not yet set up Two-Factor Authentication (MFA). A 6-digit confirmation code was sent to <strong class="font-mono text-blue-900"><?= htmlspecialchars($maskedEmail) ?></strong>.
                </div>
            </div>

            <?php if (!empty($error)): ?>
            <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl flex items-start gap-2.5 text-xs text-rose-800 font-semibold animate-shake">
                <i class="fa-solid fa-circle-exclamation text-rose-600 mt-0.5"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
            <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl flex items-start gap-2.5 text-xs text-emerald-800 font-semibold">
                <i class="fa-solid fa-circle-check text-emerald-600 mt-0.5"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                        Enter 6-Digit Confirmation Code
                    </label>
                    <input type="text" name="code" maxlength="6" pattern="[0-9]{6}" required autofocus
                           class="w-full px-4 py-3 text-center font-mono text-2xl font-bold tracking-widest text-slate-800 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-brand-700 transition"
                           placeholder="000000" autocomplete="one-time-code">
                </div>

                <button type="submit" class="w-full py-3 px-4 rounded-xl text-sm font-bold text-white bg-brand-700 hover:bg-brand-800 shadow-md transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-circle-check"></i> Verify & Complete Setup
                </button>
            </form>

            <?php if (!empty($dispCode)): ?>
            <div class="mt-3 p-2.5 bg-slate-50 border border-slate-200 rounded-lg text-center">
                <span class="text-[11px] text-slate-500 font-medium">Initial Setup Passcode:</span>
                <span class="font-mono font-bold text-brand-700 text-sm ml-1.5"><?= htmlspecialchars($dispCode) ?></span>
            </div>
            <?php endif; ?>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs">
                <a href="mfa_setup.php?resend=1" class="font-bold text-brand-700 hover:text-brand-900 flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-rotate-right"></i> Resend Code
                </a>
                <a href="mfa_setup.php?skip=1" class="font-semibold text-slate-500 hover:text-slate-800 transition">
                    Skip for now ➔
                </a>
            </div>
            <div class="mt-2 text-center">
                <a href="login.php" class="text-[11px] text-slate-400 hover:text-slate-600 transition">
                    Return to Sign In
                </a>
            </div>
        </div>
    </div>

</body>
</html>
