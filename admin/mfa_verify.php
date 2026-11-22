<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../form-one/includes/mailer.php';

$school_name        = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name  = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$logo_source        = getSchoolLogoSrc($pdo, '../');
$primary_color      = getPrimaryColor($pdo);

$token = trim($_GET['token'] ?? $_POST['token'] ?? $_SESSION['mfa_temp_token'] ?? '');
if (empty($token)) {
    header("Location: login.php");
    exit;
}

// Find user with this temp token
$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE mfa_temp_token = ?");
$stmt->execute([$token]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    header("Location: login.php?error=invalid_token");
    exit;
}

$error = '';
$success = '';

// Handle Resend Request
if (isset($_GET['resend']) && $_GET['resend'] == '1') {
    $newCode = sprintf('%06d', random_int(100000, 999999));
    $newExpiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    $pdo->prepare("UPDATE admin_users SET mfa_code = ?, mfa_expires_at = ? WHERE id = ?")
        ->execute([$newCode, $newExpiry, $admin['id']]);
        
    $maskedEmail = preg_replace('/(?<=.{3}).(?=.*@)/', '*', $admin['email'] ?? '');
    
    $subject = "Your New Staff Login Code: {$newCode} - {$school_short_name}";
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 550px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden;'>
        <div style='background-color: {$primary_color}; padding: 24px; text-align: center; color: #ffffff;'>
            <h2 style='margin: 0; font-size: 18px; font-weight: bold;'>Staff Security Verification</h2>
            <p style='margin: 4px 0 0; font-size: 12px; opacity: 0.9;'>{$school_name}</p>
        </div>
        <div style='padding: 28px; color: #334155; font-size: 14px; line-height: 1.6;'>
            <p>Hello <strong>" . htmlspecialchars($admin['firstname'] ?: $admin['username']) . "</strong>,</p>
            <p>Your new one-time security authentication passcode for staff portal access is:</p>
            <div style='background-color: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 16px; text-align: center; margin: 20px 0;'>
                <span style='font-family: monospace; font-size: 32px; font-weight: 800; letter-spacing: 6px; color: {$primary_color};'>{$newCode}</span>
                <p style='margin: 6px 0 0; font-size: 11px; color: #64748b;'>Valid for 10 minutes. Never share this code with anyone.</p>
            </div>
            <p style='font-size: 12px; color: #64748b; margin-top: 24px;'>If you did not initiate this login request, please contact system administration immediately.</p>
        </div>
    </div>";
    
    @sendEmail($admin['email'], $subject, $body);
    $success = "A fresh 6-digit verification code has been dispatched to your email (" . htmlspecialchars($maskedEmail) . ").";
}

// Handle OTP Verification Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    
    // Check if expired
    if (empty($admin['mfa_expires_at']) || strtotime($admin['mfa_expires_at']) < time()) {
        $error = "This verification code has expired. Please click 'Resend Code' to receive a new one.";
    } elseif (empty($admin['mfa_code']) || $code !== $admin['mfa_code']) {
        // Increment failed attempts
        $attempts = ($admin['failed_attempts'] ?? 0) + 1;
        $pdo->prepare("UPDATE admin_users SET failed_attempts = ? WHERE id = ?")->execute([$attempts, $admin['id']]);
        logActivity($pdo, $admin['username'], "MFA Failed", "Invalid OTP entered (Attempt {$attempts})");
        $error = "Invalid verification code. Please check your email inbox and enter the exact 6-digit passcode.";
    } else {
        // Successful verification!
        $pdo->prepare("
            UPDATE admin_users 
            SET mfa_code = NULL, mfa_expires_at = NULL, mfa_temp_token = NULL, failed_attempts = 0, lock_until = NULL 
            WHERE id = ?
        ")->execute([$admin['id']]);
        
        $userRoles = [$admin['role']];
        if (!empty($admin['roles_json'])) {
            $decoded = json_decode($admin['roles_json'], true);
            if (is_array($decoded)) {
                $userRoles = array_unique(array_merge($userRoles, $decoded));
            }
        }
        
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username']  = $admin['username'];
        $_SESSION['admin_role']      = $admin['role'];
        $_SESSION['admin_roles']     = $userRoles;
        $_SESSION['admin_firstname'] = !empty($admin['firstname']) ? $admin['firstname'] : $admin['username'];
        $_SESSION['last_activity']   = time();
        
        unset($_SESSION['mfa_temp_token']);
        
        logActivity($pdo, $admin['username'], "MFA Login", "Staff 2FA successfully verified");
        
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
<title>Two-Factor Authentication | <?= htmlspecialchars($school_short_name) ?></title>
<link rel="icon" type="image/png" href="<?= !empty($logo_source) ? htmlspecialchars($logo_source) : '../images/default_logo.png' ?>">
<!-- Tailwind CDN -->
<script src="https://cdn.tailwindcss.com"></script>
<!-- Font Awesome & Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<?= renderThemeConfig($pdo) ?>
</head>
<body class="bg-slate-900 font-sans text-slate-800 antialiased min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden relative z-10 animate-in fade-in zoom-in-95 duration-200">
        
        <!-- Header -->
        <div class="p-6 text-center text-white" style="background-color: <?= $primary_color ?>;">
            <div class="w-16 h-16 bg-white rounded-2xl p-2 mx-auto mb-3 shadow-lg flex items-center justify-center">
                <img src="<?= htmlspecialchars($logo_source) ?>" alt="Logo" class="max-h-full max-w-full object-contain">
            </div>
            <h1 class="text-lg font-black tracking-tight leading-tight uppercase">Staff Multi-Factor Verification</h1>
            <p class="text-xs text-white/80 mt-1"><?= htmlspecialchars($school_short_name) ?> · Security Checkpoint</p>
        </div>

        <div class="p-6 sm:p-8 space-y-5">

            <div class="text-center space-y-1">
                <div class="w-10 h-10 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center mx-auto text-lg mb-2">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h2 class="text-base font-bold text-slate-900">Enter Security Passcode</h2>
                <p class="text-xs text-slate-500 leading-relaxed">
                    A 6-digit verification code was sent to your registered email <strong class="text-slate-800"><?= htmlspecialchars($maskedEmail) ?></strong>.
                </p>
            </div>

            <?php if ($error): ?>
                <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm shrink-0"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-sm shrink-0"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <!-- Verification Form -->
            <form method="POST" class="space-y-4">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider text-center mb-2">6-Digit Passcode</label>
                    <input type="text" name="code" id="mfaCode" maxlength="6" autofocus required
                        placeholder="••••••"
                        class="w-full text-center text-2xl font-mono font-black tracking-[0.5em] py-3.5 px-4 border-2 border-slate-200 rounded-2xl focus:outline-none focus:border-brand-700 focus:ring-4 focus:ring-brand-700/10 transition uppercase">
                </div>

                <button type="submit" class="w-full py-3.5 px-4 bg-brand-700 hover:bg-brand-800 text-white font-bold rounded-2xl shadow-lg shadow-brand-700/20 text-xs sm:text-sm transition duration-150 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-lock-open text-xs"></i>
                    <span>Verify & Continue to Dashboard</span>
                </button>
            </form>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                <a href="mfa_verify.php?token=<?= urlencode($token) ?>&resend=1" class="text-brand-700 hover:underline font-bold flex items-center gap-1.5">
                    <i class="fa-solid fa-rotate text-[11px]"></i> Resend Passcode
                </a>
                <a href="login.php" class="text-slate-400 hover:text-slate-600">
                    Cancel & Sign Out
                </a>
            </div>

        </div>

        <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 text-center text-[11px] text-slate-400">
            Official Admissions Portal Security · <?= date('Y') ?>
        </div>

    </div>

</body>
</html>
