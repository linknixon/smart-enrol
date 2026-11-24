<?php
require_once __DIR__ . '/auth.php';

$school_short_name  = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$logo_source        = getSchoolLogoSrc($pdo, '../');
$school_website_url = getSchoolWebsiteUrl($pdo);
$adminFirstName     = getAdminDisplayName($pdo, $_SESSION['admin_username'] ?? '');

$currentRole = $_SESSION['admin_role'] ?? '';
if (!hasPermission($pdo, $currentRole, 'can_manage_users')) {
    header("Location: dashboard.php?error=unauthorized");
    exit;
}

$isSuperAdmin = (($currentRole ?? '') === 'super_admin');

$error = '';
$success = '';

// Handle Grant 3-Day MFA Grace Period (Super Admin only)
if (isset($_GET['grant_grace']) && is_numeric($_GET['grant_grace'])) {
    if (!$isSuperAdmin) {
        $error = "Unauthorized to grant MFA grace periods.";
    } else {
        $targetId = (int)$_GET['grant_grace'];
        $graceUntil = date('Y-m-d H:i:s', strtotime('+3 days'));
        $stmt = $pdo->prepare("UPDATE admin_users SET mfa_grace_until = ? WHERE id = ?");
        $stmt->execute([$graceUntil, $targetId]);

        $uNameStmt = $pdo->prepare("SELECT username FROM admin_users WHERE id = ?");
        $uNameStmt->execute([$targetId]);
        $targetUsername = $uNameStmt->fetchColumn();

        logActivity($pdo, $_SESSION['admin_username'], "Grant MFA Grace", "Granted 3-day login grace period without MFA to user '{$targetUsername}' until {$graceUntil}");
        header("Location: users.php?success=" . urlencode("3-Day MFA login bypass grace period granted to '{$targetUsername}' (valid until " . date('d/m/Y H:i', strtotime($graceUntil)) . ")."));
        exit;
    }
}

// Handle Revoke MFA Grace Period
if (isset($_GET['revoke_grace']) && is_numeric($_GET['revoke_grace'])) {
    if (!$isSuperAdmin) {
        $error = "Unauthorized to modify MFA settings.";
    } else {
        $targetId = (int)$_GET['revoke_grace'];
        $stmt = $pdo->prepare("UPDATE admin_users SET mfa_grace_until = NULL WHERE id = ?");
        $stmt->execute([$targetId]);
        header("Location: users.php?success=" . urlencode("MFA grace period revoked."));
        exit;
    }
}

// Edit Mode Loading
$editUser = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    if (!hasPermission($pdo, $currentRole, 'can_manage_users')) {
        $error = "You do not have permission to edit users.";
    } else {
        $editId = (int)$_GET['edit'];
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
        $stmt->execute([$editId]);
        $editUser = $stmt->fetch(PDO::FETCH_ASSOC);

        // Hide super admin from non-super admin
        if ($editUser && $editUser['role'] === 'super_admin' && !$isSuperAdmin) {
            $editUser = null;
            $error = "User not found.";
        }
    }
}

// Handle Delete User
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (!hasPermission($pdo, $currentRole, 'can_manage_users')) {
        $error = "You do not have permission to delete users.";
    } else {
        $deleteId = (int)$_GET['delete'];
        
        $userQuery = $pdo->prepare("SELECT username, role FROM admin_users WHERE id = ?");
        $userQuery->execute([$deleteId]);
        $delUser = $userQuery->fetch(PDO::FETCH_ASSOC);

        if (!$delUser) {
            $error = "User not found.";
        } elseif ($delUser['role'] === 'super_admin') {
            $error = "Security Policy: Super Administrator accounts are permanently protected and cannot be deleted by anyone.";
        } elseif ($delUser['username'] === $_SESSION['admin_username']) {
            $error = "You cannot delete your own account.";
        } else {
            $delUsername = $delUser['username'];
            $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
            $stmt->execute([$deleteId]);

            logActivity($pdo, $_SESSION['admin_username'], "Delete User", "Deleted user account: '$delUsername'");
            $success = "User account '$delUsername' deleted successfully.";
        }
    }
}

// Handle Add or Update User Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname'] ?? '');
    $email     = trim($_POST['email'] ?? '');

    // Multi-role handling
    $selectedRoles = $_POST['roles'] ?? [];
    if (!is_array($selectedRoles)) {
        $selectedRoles = !empty($_POST['role']) ? [trim($_POST['role'])] : ['admin'];
    }
    $selectedRoles = array_values(array_unique(array_filter(array_map('trim', $selectedRoles))));
    
    // Strict Policy: Only super_admin can assign or manage super_admin roles
    if (!$isSuperAdmin) {
        $selectedRoles = array_values(array_filter($selectedRoles, function($rk) {
            return $rk !== 'super_admin';
        }));
    }
    if (empty($selectedRoles)) {
        $selectedRoles = ['admin'];
    }
    $primaryRole = $selectedRoles[0];
    $rolesJson   = json_encode($selectedRoles);

    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $forceExpiry = isset($_POST['force_expiry']) ? 1 : 0;

        if (empty($firstname) || empty($lastname) || empty($email) || empty($username) || empty($password)) {
            $error = "All fields are required to register a new user.";
        } else {
            // Password strength validation
            $pwCheck = validatePasswordStrength($password);
            if ($pwCheck !== true) {
                $error = $pwCheck;
            } else {
                // Check duplicate username
                $check = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username = ?");
                $check->execute([$username]);
                if ($check->fetchColumn() > 0) {
                    $error = "Username '$username' is already registered. Please choose another username.";
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO admin_users (firstname, lastname, email, username, password, role, roles_json, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$firstname, $lastname, $email, $username, $hashed, $primaryRole, $rolesJson, $forceExpiry]);

                    logActivity($pdo, $_SESSION['admin_username'], "Add User", "Created staff account: '$username' ($firstname $lastname) with roles: " . implode(', ', $selectedRoles));
                    $success = "User account for '$username' created successfully.";
                }
            }
        }
    } elseif ($action === 'update') {
        $userId   = (int)($_POST['user_id'] ?? 0);
        $password = $_POST['password'] ?? '';
        $forceExpiry = isset($_POST['force_expiry']) ? 1 : 0;

        $unameQuery = $pdo->prepare("SELECT username, role FROM admin_users WHERE id = ?");
        $unameQuery->execute([$userId]);
        $targetUser = $unameQuery->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            $error = "Target user account not found.";
        } elseif ($targetUser['role'] === 'super_admin' && !$isSuperAdmin) {
            $error = "Security Policy: Only a Super Administrator can modify Super Administrator accounts.";
        } elseif (empty($firstname) || empty($lastname) || empty($email)) {
            $error = "First name, last name, and email fields are required.";
        } else {
            $tgtUsername = $targetUser['username'];

            if (!empty($password)) {
                $pwCheck = validatePasswordStrength($password);
                if ($pwCheck !== true) {
                    $error = $pwCheck;
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE admin_users SET firstname = ?, lastname = ?, email = ?, password = ?, role = ?, roles_json = ?, must_change_password = 1 WHERE id = ?");
                    $stmt->execute([$firstname, $lastname, $email, $hashed, $primaryRole, $rolesJson, $userId]);
                    
                    if ($tgtUsername === $_SESSION['admin_username']) {
                        $_SESSION['admin_roles'] = $selectedRoles;
                        $_SESSION['admin_role']  = $primaryRole;
                    }

                    logActivity($pdo, $_SESSION['admin_username'], "Reset Password", "Reset password and forced change for user: '$tgtUsername'");
                    $success = "User '$tgtUsername' updated with a new password.";
                    header("Location: users.php?success=" . urlencode($success));
                    exit;
                }
            } else {
                $stmt = $pdo->prepare("UPDATE admin_users SET firstname = ?, lastname = ?, email = ?, role = ?, roles_json = ?, must_change_password = ? WHERE id = ?");
                $stmt->execute([$firstname, $lastname, $email, $primaryRole, $rolesJson, $forceExpiry, $userId]);

                if ($tgtUsername === $_SESSION['admin_username']) {
                    $_SESSION['admin_roles'] = $selectedRoles;
                    $_SESSION['admin_role']  = $primaryRole;
                }

                logActivity($pdo, $_SESSION['admin_username'], "Edit User", "Updated account details and roles for user: '$tgtUsername'");
                $success = "User account details updated successfully.";
                header("Location: users.php?success=" . urlencode($success));
                exit;
            }
        }
    }
}

if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Fetch users (Hide super_admin if logged in user is not super_admin)
if ($isSuperAdmin) {
    $stmt = $pdo->query("SELECT * FROM admin_users ORDER BY username ASC");
} else {
    $stmt = $pdo->query("SELECT * FROM admin_users WHERE role != 'super_admin' ORDER BY username ASC");
}
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all available roles (Hide super_admin role if not super_admin)
$availableRoles = getAllRoles($pdo);
if (!$isSuperAdmin) {
    $availableRoles = array_filter($availableRoles, function($r) {
        return ($r['role_key'] ?? '') !== 'super_admin';
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff User Management | <?= htmlspecialchars($school_short_name) ?></title>
<script src="https://cdn.tailwindcss.com" data-cfasync="false"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<?= renderThemeConfig($pdo) ?>
</head>
<body class="bg-slate-100 font-sans text-slate-800 min-h-screen flex flex-col md:flex-row overflow-x-hidden">

    <!-- Mobile Drawer Overlay & Sidebar -->
    <div id="mobileSidebarBackdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden md:hidden" onclick="toggleMobileSidebar()"></div>
    <aside id="mobileSidebar" class="fixed top-0 bottom-0 left-0 w-72 bg-slate-900 text-slate-100 flex flex-col z-50 transform -translate-x-full transition-transform duration-200 ease-in-out md:hidden shadow-2xl">
        <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="<?= htmlspecialchars($logo_source) ?>" alt="Logo" class="w-9 h-9 bg-white rounded-full p-1 border-2 border-gold-500 object-contain">
                <div class="min-w-0">
                    <span class="block text-xs font-bold text-white tracking-tight uppercase truncate"><?= htmlspecialchars($school_short_name) ?></span>
                    <span class="block text-[9px] font-semibold text-gold-500 uppercase tracking-widest">Staff Portal</span>
                </div>
            </div>
            <button type="button" onclick="toggleMobileSidebar()" class="text-slate-400 hover:text-white p-1" aria-label="Close menu">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <nav class="flex-1 px-4 py-4 space-y-1.5 overflow-y-auto">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-graduation-cap text-lg"></i> Admitted Register
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-file-invoice text-lg text-gold-500"></i> Custom Reports
            </a>
            <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition">
                <i class="fa-solid fa-users text-lg"></i> Staff Users
            </a>
            <a href="roles.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-shield-halved text-lg"></i> Roles & Access
            </a>
            <?php if (hasPermission($pdo, $currentRole, 'can_view_logs')): ?>
            <a href="logs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-clock-rotate-left text-lg"></i> Activity Logs
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_settings') || hasPermission($pdo, $currentRole, 'can_manage_admission_policies')): ?>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-sliders text-lg"></i> <?= hasPermission($pdo, $currentRole, 'can_manage_settings') ? 'System Settings' : 'Policies & Fees' ?>
            </a>
            <?php endif; ?>
            <div class="pt-4 border-t border-slate-800 mt-4">
                <a href="<?= htmlspecialchars($school_website_url) ?>" target="_blank" class="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition">
                    <i class="fa-solid fa-globe text-gold-500"></i> Main School Website <i class="fa-solid fa-arrow-up-right-from-square text-[9px] ml-auto"></i>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Desktop Sidebar -->
    <aside class="hidden md:flex w-64 bg-slate-900 text-slate-100 flex-col shrink-0 min-h-screen">
        <div class="px-6 py-5 border-b border-slate-800 flex items-center gap-3">
            <img src="<?= htmlspecialchars($logo_source) ?>" alt="Logo" class="w-10 h-10 bg-white rounded-full p-1 border-2 border-gold-500 object-contain">
            <div>
                <span class="block text-sm font-bold text-white tracking-tight uppercase"><?= htmlspecialchars($school_short_name) ?></span>
                <span class="block text-xs font-semibold text-gold-500 uppercase tracking-widest text-[9px]">Staff Portal</span>
            </div>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1.5">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-graduation-cap text-lg"></i> Admitted Register
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-file-invoice text-lg text-gold-500"></i> Custom Reports
            </a>
            <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition">
                <i class="fa-solid fa-users text-lg"></i> Staff Users
            </a>
            <a href="roles.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-shield-halved text-lg"></i> Roles & Access
            </a>
            <a href="logs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-clock-rotate-left text-lg"></i> Activity Logs
            </a>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_settings') || hasPermission($pdo, $currentRole, 'can_manage_admission_policies')): ?>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-sliders text-lg"></i> <?= hasPermission($pdo, $currentRole, 'can_manage_settings') ? 'System Settings' : 'Policies & Fees' ?>
            </a>
            <?php endif; ?>

            <div class="pt-4 border-t border-slate-800 mt-4">
                <a href="<?= htmlspecialchars($school_website_url) ?>" target="_blank" class="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition">
                    <i class="fa-solid fa-globe text-gold-500"></i> Main School Website <i class="fa-solid fa-arrow-up-right-from-square text-[9px] ml-auto"></i>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 bg-slate-50 min-h-screen">
        <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-4 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
            <div class="flex items-center gap-3">
                <button type="button" onclick="toggleMobileSidebar()" class="md:hidden p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-xl focus:outline-none" aria-label="Toggle Navigation">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <h1 class="text-base sm:text-xl font-bold text-slate-900 tracking-tight">Staff Account Management</h1>
                    <p class="text-xs text-slate-500 mt-0.5 hidden sm:block">Create and manage administrator accounts and assign system roles.</p>
                </div>
            </div>

            <!-- Profile & Action Buttons -->
            <div class="flex items-center gap-3">
                <a href="roles.php" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition flex items-center gap-2 border border-slate-200">
                    <i class="fa-solid fa-shield-halved text-brand-700"></i> Manage Roles & Permissions
                </a>

                <div class="relative">
                    <button id="profileDropdownBtn" onclick="toggleDropdown()" class="flex items-center gap-2.5 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl hover:bg-slate-100 transition focus:outline-none">
                        <div class="w-8 h-8 rounded-full bg-brand-700 text-white flex items-center justify-center font-bold text-xs uppercase shadow-sm">
                            <?= strtoupper(substr($adminFirstName, 0, 1)) ?>
                        </div>
                        <div class="text-left hidden sm:block">
                            <span class="block text-xs font-bold text-slate-800">Welcome, <?= htmlspecialchars($adminFirstName) ?></span>
                            <span class="block text-[10px] text-slate-400 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $currentRole)) ?></span>
                        </div>
                        <i class="fa-solid fa-chevron-down text-slate-400 text-xs ml-1"></i>
                    </button>

                    <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-slate-200 rounded-xl shadow-lg py-1 z-50">
                        <a href="change_password.php" class="block px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2">
                            <i class="fa-solid fa-key text-slate-400"></i> Change Password
                        </a>
                        <div class="border-t border-slate-100 my-1"></div>
                        <a href="logout.php" class="block px-4 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 flex items-center gap-2">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="p-6 max-w-7xl w-full mx-auto space-y-6">

            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold rounded-xl flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-triangle-exclamation text-rose-600 text-sm"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold rounded-xl flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Form Section (Add / Edit User) -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col h-fit">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <h3 class="font-bold text-slate-800 text-xs uppercase tracking-wider">
                            <?= $editUser ? 'Edit User Credentials' : 'Create Staff Account' ?>
                        </h3>
                        <?php if ($editUser): ?>
                            <a href="users.php" class="text-xs text-slate-400 hover:text-slate-600">Cancel</a>
                        <?php endif; ?>
                    </div>

                    <form method="POST" autocomplete="off" class="space-y-4">
                        <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'add' ?>">
                        <?php if ($editUser): ?>
                            <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
                        <?php endif; ?>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">First Name *</label>
                            <input type="text" name="firstname" value="<?= $editUser ? htmlspecialchars($editUser['firstname']) : '' ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-brand-700" required>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Last Name / Surname *</label>
                            <input type="text" name="lastname" value="<?= $editUser ? htmlspecialchars($editUser['lastname']) : '' ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-brand-700" required>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Email Address *</label>
                            <input type="email" name="email" value="<?= $editUser ? htmlspecialchars($editUser['email']) : '' ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-brand-700" required>
                        </div>

                        <?php if (!$editUser): ?>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Username *</label>
                            <input type="text" name="username" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-mono focus:outline-none focus:border-brand-700" required>
                        </div>
                        <?php endif; ?>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                <?= $editUser ? 'Reset Password (Leave blank to keep current)' : 'Password *' ?>
                            </label>
                            <div class="relative">
                                <input type="password" id="staffPassword" name="password" oninput="checkPasswordRule(this.value)" class="w-full px-3 py-2 pr-10 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-brand-700" <?= $editUser ? '' : 'required' ?>>
                                <button type="button" onclick="togglePasswordVisibility('staffPassword', this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer" aria-label="Toggle password visibility">
                                    <i class="fa-solid fa-eye text-xs"></i>
                                </button>
                            </div>
                            
                            <!-- Password Strength Guidance -->
                            <div class="mt-2 p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-[11px] space-y-1 text-slate-500">
                                <div class="font-bold text-slate-700 uppercase text-[10px] tracking-wider mb-1">Strong Password Requirements:</div>
                                <div id="rule-length" class="flex items-center gap-1.5"><i class="fa-solid fa-circle text-[8px] text-slate-300"></i> At least 8 characters</div>
                                <div id="rule-upper" class="flex items-center gap-1.5"><i class="fa-solid fa-circle text-[8px] text-slate-300"></i> At least 1 uppercase letter (A-Z)</div>
                                <div id="rule-lower" class="flex items-center gap-1.5"><i class="fa-solid fa-circle text-[8px] text-slate-300"></i> At least 1 lowercase letter (a-z)</div>
                                <div id="rule-number" class="flex items-center gap-1.5"><i class="fa-solid fa-circle text-[8px] text-slate-300"></i> At least 1 number (0-9)</div>
                                <div id="rule-symbol" class="flex items-center gap-1.5"><i class="fa-solid fa-circle text-[8px] text-slate-300"></i> At least 1 special character (!@#$%^&*)</div>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">System Roles *</label>
                                <a href="roles.php" class="text-[11px] font-semibold text-brand-700 hover:underline"><i class="fa-solid fa-shield-halved text-[10px]"></i> Manage Roles</a>
                            </div>
                            <p class="text-[11px] text-slate-500 mb-2">Check one or more roles to combine permissions for this user:</p>
                            <div class="space-y-1.5 p-3 bg-slate-50 border border-slate-200 rounded-xl">
                                <?php 
                                $userCurrentRoles = [];
                                if ($editUser) {
                                    if (!empty($editUser['roles_json'])) {
                                        $dec = json_decode($editUser['roles_json'], true);
                                        if (is_array($dec)) $userCurrentRoles = $dec;
                                    }
                                    if (!empty($editUser['role']) && !in_array($editUser['role'], $userCurrentRoles)) {
                                        $userCurrentRoles[] = $editUser['role'];
                                    }
                                }
                                if (empty($userCurrentRoles)) {
                                    $userCurrentRoles = ['admin'];
                                }

                                foreach ($availableRoles as $r): 
                                    $rKey = $r['role_key'];
                                    $shortLabel = htmlspecialchars($r['role_name']);
                                    $isChecked = in_array($rKey, $userCurrentRoles);
                                ?>
                                <label class="flex items-center gap-2.5 p-1 rounded-lg hover:bg-white cursor-pointer transition">
                                    <input type="checkbox" name="roles[]" value="<?= htmlspecialchars($rKey) ?>" class="rounded border-slate-300 text-brand-700 focus:ring-brand-700 h-4 w-4" <?= $isChecked ? 'checked' : '' ?>>
                                    <span class="text-xs font-bold text-slate-700"><?= $shortLabel ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 py-1">
                            <input type="checkbox" id="forceExpiry" name="force_expiry" class="rounded border-slate-300 text-brand-700 focus:ring-brand-700 h-4 w-4" <?= ($editUser && $editUser['must_change_password'] == 1) ? 'checked' : '' ?>>
                            <label for="forceExpiry" class="text-xs text-slate-600 font-medium cursor-pointer">Force password change on next login</label>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <button type="submit" class="flex-1 py-2.5 bg-brand-700 hover:bg-brand-800 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm transition">
                                <?= $editUser ? 'Save Updates' : 'Add Staff Account' ?>
                            </button>
                            <?php if ($editUser): ?>
                                <a href="users.php" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold uppercase tracking-wider text-center transition">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Table List Section -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col lg:col-span-2">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <h3 class="font-bold text-slate-800 text-xs uppercase tracking-wider">Active Staff Accounts</h3>
                        <span class="text-xs text-slate-500 font-semibold"><?= count($users) ?> registered</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-100/70 border-b border-slate-200 text-slate-500 font-bold text-xs uppercase tracking-wider">
                                    <th class="px-4 py-3">Full Name</th>
                                    <th class="px-4 py-3 font-mono text-center">Username</th>
                                    <th class="px-4 py-3">Email Address</th>
                                    <th class="px-4 py-3 text-center">Assigned Role</th>
                                    <th class="px-4 py-3 text-center">MFA Status</th>
                                    <th class="px-4 py-3 text-center">Status</th>
                                    <th class="px-4 py-3 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                <?php foreach ($users as $u): ?>
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="px-4 py-3 font-semibold text-slate-800">
                                        <?= htmlspecialchars(($u['firstname'] ?? '') . ' ' . ($u['lastname'] ?? '')) ?>
                                    </td>
                                    <td class="px-4 py-3 text-center font-mono font-bold text-slate-600">
                                        <?= htmlspecialchars($u['username']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">
                                        <?= htmlspecialchars($u['email']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <?php 
                                        $uRoles = [];
                                        if (!empty($u['roles_json'])) {
                                            $dec = json_decode($u['roles_json'], true);
                                            if (is_array($dec)) $uRoles = $dec;
                                        }
                                        if (!empty($u['role']) && !in_array($u['role'], $uRoles)) {
                                            $uRoles[] = $u['role'];
                                        }
                                        if (empty($uRoles)) $uRoles = [$u['role'] ?? 'admin'];
                                        ?>
                                        <div class="flex flex-wrap gap-1 justify-center">
                                            <?php foreach ($uRoles as $ur): 
                                                $lbl = ($ur === 'secretary' || $ur === 'admissions_registry') ? 'Admissions Registry' : ucwords(str_replace('_', ' ', $ur));
                                            ?>
                                                <span class="px-2 py-0.5 rounded text-[10.5px] font-bold bg-slate-100 text-slate-700 border border-slate-200/80 shadow-2xs">
                                                    <?= htmlspecialchars($lbl) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <?php 
                                        $hasGrace = (!empty($u['mfa_grace_until']) && strtotime($u['mfa_grace_until']) > time());
                                        if ($hasGrace): 
                                            $daysLeft = max(1, (int)ceil((strtotime($u['mfa_grace_until']) - time()) / 86400));
                                        ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300" title="Grace bypass active until <?= htmlspecialchars($u['mfa_grace_until']) ?>">
                                                <i class="fa-solid fa-hourglass-half text-[9px] mr-1"></i> Grace (<?= $daysLeft ?>d)
                                            </span>
                                            <?php if ($isSuperAdmin): ?>
                                                <a href="users.php?revoke_grace=<?= $u['id'] ?>" class="block text-[9px] text-rose-600 hover:underline mt-0.5 font-semibold">Revoke Grace</a>
                                            <?php endif; ?>
                                        <?php elseif (!empty($u['mfa_enabled'])): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <i class="fa-solid fa-shield-check text-[9px] mr-1"></i> Enrolled
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200">
                                                Pending Setup
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <?php if($u['must_change_password'] == 1): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Force Reset</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <div class="inline-flex items-center justify-center gap-2 whitespace-nowrap flex-nowrap shrink-0">
                                            <a href="users.php?edit=<?= $u['id'] ?>" class="text-brand-700 hover:text-brand-900 font-bold inline-flex items-center gap-1 whitespace-nowrap shrink-0">
                                                <i class="fa-solid fa-pen text-[10px]"></i> Edit
                                            </a>
                                            <?php if ($isSuperAdmin && !$hasGrace): ?>
                                                <a href="users.php?grant_grace=<?= $u['id'] ?>" class="text-amber-600 hover:text-amber-800 font-bold inline-flex items-center gap-1 whitespace-nowrap shrink-0" title="Grant 3-day login grace without MFA">
                                                    <i class="fa-solid fa-clock-rotate-left text-[10px]"></i> Grace
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($u['username'] !== $_SESSION['admin_username'] && $u['role'] !== 'super_admin'): ?>
                                                <a href="users.php?delete=<?= $u['id'] ?>" onclick="return confirm('Are you sure you want to permanently delete user account <?= htmlspecialchars($u['username']) ?>?')" class="text-rose-600 hover:text-rose-800 font-bold inline-flex items-center gap-1 whitespace-nowrap shrink-0">
                                                    <i class="fa-solid fa-trash text-[10px]"></i> Delete
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </main>

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

    function toggleMobileSidebar() {
        const sidebar = document.getElementById('mobileSidebar');
        const backdrop = document.getElementById('mobileSidebarBackdrop');
        if (!sidebar || !backdrop) return;
        const isClosed = sidebar.classList.contains('-translate-x-full');
        if (isClosed) {
            sidebar.classList.remove('-translate-x-full');
            backdrop.classList.remove('hidden');
        } else {
            sidebar.classList.add('-translate-x-full');
            backdrop.classList.add('hidden');
        }
    }
    function toggleDropdown() {
        const drop = document.getElementById('profileDropdown');
        if (drop) drop.classList.toggle('hidden');
    }
    window.addEventListener('click', function(e) {
        const btn = document.getElementById('profileDropdownBtn');
        const drop = document.getElementById('profileDropdown');
        if (btn && drop && !btn.contains(e.target) && !drop.contains(e.target)) {
            drop.classList.add('hidden');
        }
    });

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
