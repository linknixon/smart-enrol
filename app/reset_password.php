<?php
require_once __DIR__ . '/../config/db.php';

$school_name        = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name  = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$logo_source        = getSchoolLogoSrc($pdo, '../');
$primary_color      = getPrimaryColor($pdo);

$error = '';
$success = '';

$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$otp   = trim($_GET['otp'] ?? ($_POST['otp'] ?? ''));

$user = null;

if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE reset_token = ? AND reset_token IS NOT NULL LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (!empty($otp)) {
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE mfa_code = ? AND mfa_code IS NOT NULL LIMIT 1");
    $stmt->execute([$otp]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Check expiration if user found
$isExpired = false;
if ($user) {
    if (!empty($user['reset_expires_at']) && strtotime($user['reset_expires_at']) < time()) {
        $isExpired = true;
        $user = null;
        $error = "This password reset link or code has expired. Please request a new one.";
    }
} elseif (!empty($token) || !empty($otp)) {
    $error = "Invalid or expired password reset link. Please request a new one.";
}

// Handle password reset submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user && !$isExpired) {
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match. Please verify and try again.";
    } else {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("
            UPDATE admin_users 
            SET password = ?, 
                reset_token = NULL, 
                reset_expires_at = NULL, 
                mfa_code = NULL, 
                must_change_password = 0, 
                failed_attempts = 0, 
                lock_until = NULL 
            WHERE id = ?
        ");
        $updateStmt->execute([$hashed, $user['id']]);

        logActivity($pdo, $user['username'], "Password Reset Completed", "User successfully reset their password via self-service recovery");

        header("Location: login.php?changed=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password | <?= htmlspecialchars($school_short_name) ?> Admin Portal</title>
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
            <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mt-1">Set New Staff Password</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-800 p-3.5 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-500 text-base shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($user && !$isExpired): ?>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-600 flex items-center gap-2">
                <i class="fa-solid fa-user-check text-brand-700 text-sm"></i>
                <span>Resetting password for: <strong class="text-slate-900"><?= htmlspecialchars($user['username']) ?></strong></span>
            </div>

            <form method="POST" autocomplete="off" class="space-y-4">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="otp" value="<?= htmlspecialchars($otp) ?>">

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">New Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input 
                            type="password" 
                            name="new_password" 
                            id="newPassword"
                            class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 focus:bg-white focus:ring-4 focus:ring-brand-700/10 font-medium transition" 
                            placeholder="At least 8 characters" 
                            required
                            minlength="8"
                            autofocus
                        >
                        <button type="button" onclick="togglePassVisibility('newPassword', this)" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer" aria-label="Toggle password visibility">
                            <i class="fa-solid fa-eye text-sm"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Confirm New Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <i class="fa-solid fa-lock-open"></i>
                        </span>
                        <input 
                            type="password" 
                            name="confirm_password" 
                            id="confirmPassword"
                            class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 focus:bg-white focus:ring-4 focus:ring-brand-700/10 font-medium transition" 
                            placeholder="Re-enter your new password" 
                            required
                            minlength="8"
                        >
                        <button type="button" onclick="togglePassVisibility('confirmPassword', this)" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer" aria-label="Toggle password visibility">
                            <i class="fa-solid fa-eye text-sm"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-brand-700 hover:bg-brand-800 text-white font-bold text-sm uppercase tracking-wider rounded-xl shadow-lg shadow-brand-700/25 transition duration-150 flex items-center justify-center gap-2 mt-2">
                    <i class="fa-solid fa-check-circle"></i>
                    <span>Save New Password</span>
                </button>
            </form>
        <?php else: ?>
            <div class="space-y-4">
                <form method="GET" class="space-y-3">
                    <label class="block text-xs font-bold text-slate-600 uppercase">Have a 6-digit confirmation code?</label>
                    <div class="flex gap-2">
                        <input type="text" name="otp" maxlength="6" pattern="[0-9]{6}" required placeholder="000000" class="flex-1 px-4 py-2 text-center font-mono text-lg font-bold border border-slate-200 rounded-xl">
                        <button type="submit" class="px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white font-bold text-xs rounded-xl transition">
                            Verify Code
                        </button>
                    </div>
                </form>
                <div class="pt-2 text-center">
                    <a href="forgot_password.php" class="inline-block px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition">
                        <i class="fa-solid fa-rotate-right mr-1"></i> Request a New Reset Link
                    </a>
                </div>
            </div>
        <?php endif; ?>

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

<script>
function togglePassVisibility(id, btn) {
    const input = document.getElementById(id);
    if (!input) return;
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    } else {
        input.type = 'password';
        if (icon) {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
}

</script>

</body>
</html>
