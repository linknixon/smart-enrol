<?php
require 'auth.php';
require '../config/db.php';

$msg = '';
$error = '';

// Check if forced password expiry
$stmt = $pdo->prepare("SELECT must_change_password FROM admin_users WHERE username = ?");
$stmt->execute([$_SESSION['admin_username']]);
$mustChange = $stmt->fetchColumn();

$isExpired = isset($_GET['expired']) || ($mustChange == 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if ($new !== $confirm) {
        $error = "New passwords do not match.";
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

            // Log activity
            logActivity($pdo, $_SESSION['admin_username'], "Change Password", "Changed account password");

            $msg = "Password changed successfully. You can now access the dashboard.";
            $mustChange = 0;
            $isExpired = false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password | St. Paul's Mutolere SS</title>
<!-- Tailwind CDN -->
<script src="https://cdn.tailwindcss.com"></script>
<!-- Font Awesome & Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script>
tailwind.config = {
    theme: {
        extend: {
            fontFamily: {
                sans: ['Outfit', 'sans-serif'],
            },
            colors: {
                brand: {
                    700: '#0c4a24',
                    800: '#062f14',
                },
                gold: {
                    500: '#b58d2f',
                }
            }
        }
    }
}
</script>
</head>
<body class="bg-slate-100 font-sans text-slate-800 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white border border-slate-200 rounded-2xl shadow-xl p-8 space-y-6">
        
        <!-- Header -->
        <div class="text-center">
            <img src="https://www.ncedges.com/wp-content/uploads/2026/02/mutosp-logo.png" alt="Logo" class="w-16 h-16 mx-auto mb-4 bg-white rounded-full p-1 border border-slate-200">
            <h3 class="text-xl font-bold text-slate-800 tracking-tight">Security Control Panel</h3>
            <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider mt-1">Change Password</p>
        </div>

        <!-- Expiry Warning -->
        <?php if ($isExpired): ?>
        <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-800 p-4 rounded-xl flex gap-3 shadow-sm">
            <i class="fa-solid fa-triangle-exclamation text-xl text-rose-500 mt-0.5"></i>
            <div>
                <span class="block text-sm font-bold">Password Change Required</span>
                <span class="block text-xs mt-0.5 leading-relaxed">Your password has expired or was reset by the administrator. You must set a new password to proceed.</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Messages -->
        <?php if ($error): ?>
        <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-800 p-4 rounded-xl flex items-center gap-3 shadow-sm">
            <i class="fa-solid fa-circle-exclamation text-xl text-rose-500"></i>
            <span class="text-xs font-semibold"><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($msg): ?>
        <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 p-4 rounded-xl flex items-center gap-3 shadow-sm">
            <i class="fa-solid fa-circle-check text-xl text-emerald-500"></i>
            <span class="text-xs font-semibold"><?= htmlspecialchars($msg) ?></span>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" autocomplete="off" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Current Password</label>
                <input type="password" name="current_password" required class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 placeholder:text-slate-300" placeholder="••••••••">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">New Password</label>
                <input type="password" name="new_password" required class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 placeholder:text-slate-300" placeholder="••••••••">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Confirm New Password</label>
                <input type="password" name="confirm_password" required class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 placeholder:text-slate-300" placeholder="••••••••">
            </div>
            
            <button type="submit" class="w-full py-3 bg-brand-700 hover:bg-brand-800 text-white rounded-lg text-sm font-bold shadow-md transition duration-150 flex justify-center items-center gap-1.5 mt-2">
                <i class="fa-solid fa-shield-halved"></i> Update Password
            </button>
        </form>

        <!-- Back Button Option -->
        <div class="text-center pt-2 border-t border-slate-100 flex justify-center">
            <?php if ($isExpired): ?>
                <a href="logout.php" class="inline-flex items-center gap-1 text-xs font-bold text-rose-600 hover:underline">
                    <i class="fa-solid fa-right-from-bracket"></i> Cancel & Logout
                </a>
            <?php else: ?>
                <a href="dashboard.php" class="inline-flex items-center gap-1 text-xs font-bold text-brand-700 hover:underline">
                    <i class="fa-solid fa-arrow-left-long"></i> Back to Dashboard
                </a>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>
