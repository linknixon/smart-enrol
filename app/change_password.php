<?php
require_once __DIR__ . '/auth.php';

$school_short_name  = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$logo_source        = getSchoolLogoSrc($pdo, '../');
$school_website_url = getSchoolWebsiteUrl($pdo);
$adminFirstName     = getAdminDisplayName($pdo, $_SESSION['admin_username'] ?? '');
$currentRole        = $_SESSION['admin_role'] ?? '';

$msg = '';
$error = '';

// Check if forced password expiry
$stmt = $pdo->prepare("SELECT must_change_password FROM admin_users WHERE username = ?");
$stmt->execute([$_SESSION['admin_username']]);
$mustChange = $stmt->fetchColumn();

$isExpired = isset($_GET['expired']) || ($mustChange == 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new !== $confirm) {
        $error = "New passwords do not match. Please verify and try again.";
    } else {
        $pwCheck = validatePasswordStrength($new);
        if ($pwCheck !== true) {
            $error = $pwCheck;
        } else {
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
            $stmt->execute([$_SESSION['admin_username']]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin || !password_verify($current, $admin['password'])) {
                $error = "Current password is incorrect.";
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);

                $pdo->prepare("
                    UPDATE admin_users 
                    SET password = ?, failed_attempts = 0, lock_until = NULL, must_change_password = 0
                    WHERE id = ?
                ")->execute([$hash, $admin['id']]);

                logActivity($pdo, $_SESSION['admin_username'], "Change Password", "Changed account password");

                $msg = "Password updated successfully! Your account is now secured.";
                $mustChange = 0;
                $isExpired = false;
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
<title>Change Password | <?= htmlspecialchars($school_short_name) ?></title>
<script src="https://cdn.tailwindcss.com" data-cfasync="false"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<?= renderThemeConfig($pdo) ?>
</head>
<body class="bg-slate-100 font-sans text-slate-800 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-2xl border border-slate-200 shadow-xl overflow-hidden">
        <!-- Brand Header -->
        <div class="bg-slate-900 px-6 py-6 text-center text-white relative">
            <img src="<?= htmlspecialchars($logo_source) ?>" alt="Logo" class="w-12 h-12 bg-white rounded-full p-1 border-2 border-gold-500 object-contain mx-auto mb-2">
            <h2 class="text-base font-bold tracking-tight uppercase"><?= htmlspecialchars($school_short_name) ?></h2>
            <p class="text-xs text-slate-400">Staff Account Security</p>
        </div>

        <div class="p-6 space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">Update Password</span>
                <span class="text-xs text-slate-500 font-semibold">Welcome, <?= htmlspecialchars($adminFirstName) ?></span>
            </div>

            <?php if ($isExpired): ?>
                <div class="bg-amber-50 border border-amber-200 text-amber-900 p-3.5 rounded-xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-shield-virus text-amber-600 text-base"></i>
                    <span>Password renewal required. Please set a strong new password before proceeding.</span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 p-3.5 rounded-xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-rose-600 text-base"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($msg): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3.5 rounded-xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                    <span><?= htmlspecialchars($msg) ?></span>
                </div>
                <div class="pt-2 text-center">
                    <a href="dashboard.php" class="inline-flex items-center justify-center gap-2 w-full py-2.5 bg-brand-700 hover:bg-brand-800 text-white rounded-xl text-xs font-bold uppercase tracking-wider transition">
                        <i class="fa-solid fa-arrow-right"></i> Continue to Dashboard
                    </a>
                </div>
            <?php else: ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Current Password *</label>
                    <div class="relative">
                        <input type="password" id="currentPassword" name="current_password" required class="w-full px-3 py-2 pr-10 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-brand-700">
                        <button type="button" onclick="togglePasswordVisibility('currentPassword', this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer">
                            <i class="fa-solid fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">New Password *</label>
                    <div class="relative">
                        <input type="password" id="newPassword" name="new_password" required oninput="checkPasswordRule(this.value)" class="w-full px-3 py-2 pr-10 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-brand-700">
                        <button type="button" onclick="togglePasswordVisibility('newPassword', this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer">
                            <i class="fa-solid fa-eye text-xs"></i>
                        </button>
                    </div>
                    
                    <!-- Password Strength Checklist -->
                    <div class="mt-2 p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-[11px] space-y-1 text-slate-500">
                        <div class="font-bold text-slate-700 uppercase text-[10px] tracking-wider mb-1">Password Requirements:</div>
                        <div id="rule-length" class="flex items-center gap-1.5"><i class="fa-solid fa-circle text-[8px] text-slate-300"></i> At least 8 characters</div>
                        <div id="rule-upper" class="flex items-center gap-1.5"><i class="fa-solid fa-circle text-[8px] text-slate-300"></i> At least 1 uppercase letter (A-Z)</div>
                        <div id="rule-lower" class="flex items-center gap-1.5"><i class="fa-solid fa-circle text-[8px] text-slate-300"></i> At least 1 lowercase letter (a-z)</div>
                        <div id="rule-number" class="flex items-center gap-1.5"><i class="fa-solid fa-circle text-[8px] text-slate-300"></i> At least 1 number (0-9)</div>
                        <div id="rule-symbol" class="flex items-center gap-1.5"><i class="fa-solid fa-circle text-[8px] text-slate-300"></i> At least 1 special character (!@#$%^&*)</div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Confirm New Password *</label>
                    <div class="relative">
                        <input type="password" id="confirmPassword" name="confirm_password" required class="w-full px-3 py-2 pr-10 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-brand-700">
                        <button type="button" onclick="togglePasswordVisibility('confirmPassword', this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer">
                            <i class="fa-solid fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <a href="dashboard.php" class="text-xs font-semibold text-slate-500 hover:text-slate-800 transition">Cancel</a>
                    <button type="submit" class="px-5 py-2.5 bg-brand-700 hover:bg-brand-800 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm transition">
                        Update Password
                    </button>
                </div>
            </form>
            <?php endif; ?>

            <!-- MFA Management Card -->
            <div class="pt-4 border-t border-slate-200 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-shield-halved text-brand-700"></i> Two-Factor Auth (MFA)
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800 border border-emerald-200">
                        Active & Protected
                    </span>
                </div>
                <p class="text-[11px] text-slate-500 leading-relaxed">
                    MFA protection is actively enforced by institutional security policy and cannot be turned off. To update or re-verify your device contact, click below:
                </p>
                <a href="mfa_setup.php?resend=1" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold border border-slate-200 transition">
                    <i class="fa-solid fa-mobile-screen-button text-brand-700"></i> Update / Re-verify MFA Device
                </a>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <a href="<?= htmlspecialchars($school_website_url) ?>" target="_blank" class="hover:text-brand-700 flex items-center gap-1">
                    <i class="fa-solid fa-globe"></i> Main Website
                </a>
                <a href="logout.php" class="text-rose-600 hover:text-rose-700 font-semibold flex items-center gap-1">
                    <i class="fa-solid fa-right-from-bracket"></i> Sign Out
                </a>
            </div>
        </div>
    </div>

    <script>
    function togglePasswordVisibility(id, btn) {
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

    function checkPasswordRule(pw) {
        function update(elId, pass) {
            const el = document.getElementById(elId);
            if (!el) return;
            if (pass) {
                el.classList.remove('text-slate-500');
                el.classList.add('text-emerald-600', 'font-semibold');
                el.innerHTML = '<i class="fa-solid fa-check text-[10px] text-emerald-600"></i> ' + el.innerText.replace(/^[•\s\u2713]+/, '');
            } else {
                el.classList.remove('text-emerald-600', 'font-semibold');
                el.classList.add('text-slate-500');
                el.innerHTML = '<i class="fa-solid fa-circle text-[8px] text-slate-300"></i> ' + el.innerText.replace(/^[•\s\u2713]+/, '');
            }
        }
        update('rule-length', pw.length >= 8);
        update('rule-upper', /[A-Z]/.test(pw));
        update('rule-lower', /[a-z]/.test(pw));
        update('rule-number', /[0-9]/.test(pw));
        update('rule-symbol', /[\W_]/.test(pw));
    }
    </script>
</body>
</html>
