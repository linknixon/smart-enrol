<?php
require_once __DIR__ . '/auth.php';

$school_short_name  = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$logo_source        = getSchoolLogoSrc($pdo, '../');
$school_website_url = getSchoolWebsiteUrl($pdo);
$adminFirstName     = getAdminDisplayName($pdo, $_SESSION['admin_username'] ?? '');
$primary_color      = getPrimaryColor($pdo);

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

// Compute Statistics for Top Cards
$totalStaff = count($users);
$superAdminCount = 0;
$mfaEnrolledCount = 0;
$activeCount = 0;
foreach ($users as $u) {
    if (($u['role'] ?? '') === 'super_admin') {
        $superAdminCount++;
    }
    if (!empty($u['mfa_enabled'])) {
        $mfaEnrolledCount++;
    }
    if (($u['must_change_password'] ?? 0) == 0) {
        $activeCount++;
    }
}
$mfaPercent = ($totalStaff > 0) ? round(($mfaEnrolledCount / $totalStaff) * 100) : 0;

$isFormExpanded = ($editUser !== null || !empty($error));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Account Management | <?= htmlspecialchars($school_short_name) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<?= renderThemeConfig($pdo) ?>
<style>
.user-card-transition {
    transition: all 0.25s ease-in-out;
}
</style>
</head>
<body class="bg-slate-100 font-sans text-slate-800 min-h-screen flex flex-col md:flex-row overflow-x-hidden">

    <!-- Mobile Drawer Overlay & Sidebar -->
    <div id="mobileSidebarBackdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden md:hidden" onclick="toggleMobileSidebar()"></div>
    <aside id="mobileSidebar" class="fixed top-0 bottom-0 left-0 w-72 text-white flex flex-col z-50 transform -translate-x-full transition-transform duration-200 ease-in-out md:hidden shadow-2xl" style="background-color: <?= htmlspecialchars($primary_color) ?>;">
        <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="<?= htmlspecialchars($logo_source) ?>" alt="Logo" class="w-9 h-9 bg-white rounded-full p-1 border-2 border-gold-500 object-contain">
                <div class="min-w-0">
                    <span class="block text-xs font-bold text-white tracking-tight uppercase truncate"><?= htmlspecialchars($school_short_name) ?></span>
                    <span class="block text-[9px] font-semibold text-gold-400 uppercase tracking-widest">Consolidated Portal</span>
                </div>
            </div>
            <button type="button" onclick="toggleMobileSidebar()" class="text-white/70 hover:text-white p-1" aria-label="Close menu">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <nav class="flex-1 px-4 py-4 space-y-1.5 overflow-y-auto">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard (S1-S6)
            </a>
            <a href="applications.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-id-card-clip text-lg text-gold-400"></i> Applications
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-graduation-cap text-lg"></i> Admitted Register
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-file-invoice text-lg text-gold-400"></i> Custom Reports
            </a>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_users')): ?>
            <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-bold bg-white/20 text-white ring-1 ring-white/30 shadow-sm transition duration-150">
                <i class="fa-solid fa-users text-lg"></i> Staff Users
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_roles') || hasPermission($pdo, $currentRole, 'can_manage_users')): ?>
            <a href="roles.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-shield-halved text-lg"></i> Roles & Access
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_view_logs')): ?>
            <a href="logs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-clock-rotate-left text-lg"></i> Activity Logs
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_settings') || hasPermission($pdo, $currentRole, 'can_manage_admission_policies')): ?>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-sliders text-lg text-gold-400"></i> <?= hasPermission($pdo, $currentRole, 'can_manage_settings') ? 'System Settings' : 'Policies & Fees' ?>
            </a>
            <?php endif; ?>
            <div class="pt-4 border-t border-white/10 mt-4">
                <a href="<?= htmlspecialchars($school_website_url) ?>" target="_blank" class="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-white/80 hover:text-white rounded-lg hover:bg-white/10 transition">
                    <i class="fa-solid fa-globe text-gold-400"></i> Main School Website <i class="fa-solid fa-arrow-up-right-from-square text-[9px] ml-auto"></i>
                </a>
            </div>
        </nav>
        <div class="px-5 py-3 border-t border-white/10 text-[11px] text-white/60 text-center">
            Admissions Office · <?= htmlspecialchars(getAcademicYear($pdo)) ?>
        </div>
    </aside>

    <!-- Desktop Sidebar -->
    <aside class="hidden md:flex w-64 text-white flex-col shrink-0 min-h-screen" style="background-color: <?= htmlspecialchars($primary_color) ?>;">
        <div class="px-6 py-5 border-b border-white/10 flex items-center gap-3">
            <img src="<?= htmlspecialchars($logo_source) ?>" alt="Logo" class="w-10 h-10 bg-white rounded-full p-1 border-2 border-gold-500 object-contain">
            <div class="min-w-0">
                <span class="block text-sm font-bold text-white tracking-tight leading-tight uppercase truncate"><?= htmlspecialchars($school_short_name) ?></span>
                <span class="block text-xs font-semibold text-gold-400 uppercase tracking-widest text-[9px]">Consolidated Portal</span>
            </div>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1.5">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard (S1-S6)
            </a>
            <a href="applications.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-id-card-clip text-lg text-gold-400"></i> Applications
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-graduation-cap text-lg"></i> Admitted Register
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-file-invoice text-lg text-gold-400"></i> Custom Reports
            </a>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_users')): ?>
            <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-bold bg-white/20 text-white ring-1 ring-white/30 shadow-sm transition duration-150">
                <i class="fa-solid fa-users text-lg"></i> Staff Users
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_roles') || hasPermission($pdo, $currentRole, 'can_manage_users')): ?>
            <a href="roles.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-shield-halved text-lg"></i> Roles & Access
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_view_logs')): ?>
            <a href="logs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-clock-rotate-left text-lg"></i> Activity Logs
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_settings') || hasPermission($pdo, $currentRole, 'can_manage_admission_policies')): ?>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-sliders text-lg text-gold-400"></i> <?= hasPermission($pdo, $currentRole, 'can_manage_settings') ? 'System Settings' : 'Policies & Fees' ?>
            </a>
            <?php endif; ?>

            <div class="pt-4 border-t border-white/10 mt-4">
                <a href="<?= htmlspecialchars($school_website_url) ?>" target="_blank" class="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-white/80 hover:text-white rounded-lg hover:bg-white/10 transition">
                    <i class="fa-solid fa-globe text-gold-400"></i> Main School Website <i class="fa-solid fa-arrow-up-right-from-square text-[9px] ml-auto"></i>
                </a>
            </div>
        </nav>

        <div class="px-6 py-4 border-t border-white/10 text-xs text-white/60 text-center">
            Admissions Office · <?= htmlspecialchars(getAcademicYear($pdo)) ?>
        </div>
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
                    <p class="text-xs text-slate-500 mt-0.5 hidden sm:block">Authorize staff credentials, enforce MFA authentication, and manage system access roles.</p>
                </div>
            </div>

            <!-- Profile & Action Buttons -->
            <div class="flex items-center gap-3">
                <a href="roles.php" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition flex items-center gap-2 border border-slate-200 shadow-2xs">
                    <i class="fa-solid fa-shield-halved text-brand-700"></i>
                    <span>Roles & Permissions</span>
                </a>

                <div class="relative">
                    <button id="profileDropdownBtn" onclick="toggleDropdown()" class="flex items-center gap-2.5 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl hover:bg-slate-100 transition focus:outline-none shadow-2xs">
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

        <div class="p-4 sm:p-6 max-w-7xl w-full mx-auto space-y-5">

            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold rounded-2xl flex items-center gap-3 shadow-xs">
                    <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                    </div>
                    <div class="flex-1"><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold rounded-2xl flex items-center gap-3 shadow-xs">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-circle-check text-sm"></i>
                    </div>
                    <div class="flex-1"><?= htmlspecialchars($success) ?></div>
                </div>
            <?php endif; ?>

            <!-- Metric Summary Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-4">
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Staff</span>
                        <span class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1 block"><?= $totalStaff ?></span>
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Configured accounts</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-brand-50 text-brand-700 flex items-center justify-center text-lg shadow-2xs">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>

                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Super Admins</span>
                        <span class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1 block"><?= $superAdminCount ?></span>
                        <span class="text-[11px] text-amber-600 font-semibold mt-0.5 block">Protected root access</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg shadow-2xs">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                </div>

                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">MFA Enrolled</span>
                        <span class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1 block"><?= $mfaEnrolledCount ?></span>
                        <span class="text-[11px] text-emerald-600 font-semibold mt-0.5 block"><?= $mfaPercent ?>% 2FA security coverage</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg shadow-2xs">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                </div>

                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Active Status</span>
                        <span class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1 block"><?= $activeCount ?></span>
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Ready for portal login</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg shadow-2xs">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                </div>
            </div>

            <!-- Form Section (Collapsible / Edit Panel) -->
            <div id="staffFormContainer" class="<?= $isFormExpanded ? '' : 'hidden' ?> bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden transition-all duration-300">
                <div class="px-5 sm:px-6 py-4 bg-slate-50/75 border-b border-slate-200 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl <?= $editUser ? 'bg-amber-100 text-amber-700' : 'bg-brand-50 text-brand-700' ?> flex items-center justify-center text-sm font-bold shadow-2xs">
                            <i class="fa-solid <?= $editUser ? 'fa-user-pen' : 'fa-user-plus' ?>"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-900 text-sm">
                                <?= $editUser ? 'Edit Staff Account: <span class="text-amber-700 font-mono">@' . htmlspecialchars($editUser['username']) . '</span>' : 'Register New Staff Account' ?>
                            </h3>
                            <p class="text-[11px] text-slate-500">Provide staff profile details, set secure initial password, and assign granular permissions.</p>
                        </div>
                    </div>
                    <div>
                        <?php if ($editUser): ?>
                            <a href="users.php" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition inline-flex items-center gap-1.5 border border-slate-200">
                                <i class="fa-solid fa-xmark text-xs"></i> Cancel Edit
                            </a>
                        <?php else: ?>
                            <button type="button" onclick="toggleStaffForm()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-semibold transition inline-flex items-center gap-1.5">
                                <i class="fa-solid fa-chevron-up text-xs"></i> Close
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="POST" autocomplete="off" class="p-5 sm:p-6">
                    <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'add' ?>">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Column 1: Personal & Account Identity -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2 pb-2 border-b border-slate-100">
                                <i class="fa-solid fa-address-card text-brand-700"></i> Profile &amp; Login Credentials
                            </h4>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">First Name *</label>
                                    <input type="text" name="firstname" value="<?= $editUser ? htmlspecialchars($editUser['firstname']) : '' ?>" placeholder="First Name" class="w-full px-3.5 py-2.5 bg-slate-50/50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:bg-white focus:border-brand-700 transition" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Last Name / Surname *</label>
                                    <input type="text" name="lastname" value="<?= $editUser ? htmlspecialchars($editUser['lastname']) : '' ?>" placeholder="Last Name / Surname" class="w-full px-3.5 py-2.5 bg-slate-50/50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:bg-white focus:border-brand-700 transition" required>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Email Address *</label>
                                <input type="email" name="email" value="<?= $editUser ? htmlspecialchars($editUser['email']) : '' ?>" placeholder="staff@school.sc.ug" class="w-full px-3.5 py-2.5 bg-slate-50/50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:bg-white focus:border-brand-700 transition" required>
                            </div>

                            <?php if (!$editUser): ?>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Username *</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 font-mono text-xs">@</span>
                                    <input type="text" name="username" placeholder="username" class="w-full pl-8 pr-3.5 py-2.5 bg-slate-50/50 border border-slate-200 rounded-xl text-xs font-mono focus:outline-none focus:bg-white focus:border-brand-700 transition" required>
                                </div>
                                <p class="text-[11px] text-slate-400 mt-1">Unique login username. Letters, digits, and underscores only.</p>
                            </div>
                            <?php else: ?>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Username (Fixed)</label>
                                <input type="text" value="@<?= htmlspecialchars($editUser['username']) ?>" class="w-full px-3.5 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs font-mono text-slate-500 cursor-not-allowed" readonly>
                            </div>
                            <?php endif; ?>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                    <?= $editUser ? 'Reset Password <span class="font-normal text-slate-400 normal-case">(Leave blank to keep current)</span>' : 'Initial Password *' ?>
                                </label>
                                <div class="relative">
                                    <input type="password" id="staffPassword" name="password" oninput="checkPasswordRule(this.value)" placeholder="••••••••••••" class="w-full px-3.5 py-2.5 pr-10 bg-slate-50/50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:bg-white focus:border-brand-700 transition" <?= $editUser ? '' : 'required' ?>>
                                    <button type="button" onclick="togglePasswordVisibility('staffPassword', this)" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer" aria-label="Toggle password visibility">
                                        <i class="fa-solid fa-eye text-xs"></i>
                                    </button>
                                </div>
                                
                                <!-- Password Strength Requirements Checklist -->
                                <div class="mt-2.5 p-3 bg-slate-50 border border-slate-200 rounded-xl text-[11px] space-y-1 text-slate-500">
                                    <div class="font-bold text-slate-700 uppercase text-[10px] tracking-wider mb-1">Password Strength Requirements:</div>
                                    <div class="grid grid-cols-2 gap-x-2 gap-y-1">
                                        <div id="rule-length" class="flex items-center gap-1.5"><i class="fa-solid fa-circle text-[7px] text-slate-300"></i> At least 8 characters</div>
                                        <div id="rule-upper" class="flex items-center gap-1.5"><i class="fa-solid fa-circle text-[7px] text-slate-300"></i> Uppercase (A-Z)</div>
                                        <div id="rule-lower" class="flex items-center gap-1.5"><i class="fa-solid fa-circle text-[7px] text-slate-300"></i> Lowercase (a-z)</div>
                                        <div id="rule-number" class="flex items-center gap-1.5"><i class="fa-solid fa-circle text-[7px] text-slate-300"></i> Number (0-9)</div>
                                    </div>
                                    <div id="rule-symbol" class="flex items-center gap-1.5 pt-0.5"><i class="fa-solid fa-circle text-[7px] text-slate-300"></i> Special symbol (!@#$%^&amp;*)</div>
                                </div>
                            </div>
                        </div>

                        <!-- Column 2: Roles & Security Controls -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                                <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                    <i class="fa-solid fa-shield-halved text-brand-700"></i> Assigned System Roles
                                </h4>
                                <a href="roles.php" class="text-[11px] font-semibold text-brand-700 hover:underline flex items-center gap-1">
                                    <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i> Role Directory
                                </a>
                            </div>

                            <p class="text-xs text-slate-500">Select one or more roles. The user inherits all permissions combined from selected roles:</p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-64 overflow-y-auto p-1">
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
                                    $isSuper = ($rKey === 'super_admin');
                                ?>
                                <label class="relative flex items-start gap-3 p-3 rounded-xl border <?= $isChecked ? 'border-brand-500 bg-brand-50/40 ring-1 ring-brand-400/20' : 'border-slate-200 bg-slate-50/50 hover:bg-slate-100/70' ?> cursor-pointer transition select-none">
                                    <input type="checkbox" name="roles[]" value="<?= htmlspecialchars($rKey) ?>" class="mt-0.5 rounded border-slate-300 text-brand-700 focus:ring-brand-700 h-4 w-4" <?= $isChecked ? 'checked' : '' ?> onchange="this.closest('label').classList.toggle('border-brand-500', this.checked); this.closest('label').classList.toggle('bg-brand-50/40', this.checked);">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-xs font-bold text-slate-800"><?= $shortLabel ?></span>
                                            <?php if ($isSuper): ?>
                                                <span class="text-[9px] px-1.5 py-0.2 rounded font-extrabold bg-amber-100 text-amber-800 border border-amber-300">ROOT</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-[10.5px] text-slate-500 leading-snug mt-0.5"><?= htmlspecialchars($r['description'] ?? 'Portal operations role') ?></p>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>

                            <!-- Additional Security Switches -->
                            <div class="pt-3 border-t border-slate-100 space-y-3">
                                <label class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-100 transition select-none">
                                    <input type="checkbox" id="forceExpiry" name="force_expiry" class="rounded border-slate-300 text-brand-700 focus:ring-brand-700 h-4 w-4" <?= ($editUser && $editUser['must_change_password'] == 1) ? 'checked' : '' ?>>
                                    <div class="min-w-0">
                                        <span class="block text-xs font-bold text-slate-800">Require Password Change on Next Login</span>
                                        <span class="block text-[11px] text-slate-500">Forces the user to create a new password immediately upon logging in.</span>
                                    </div>
                                </label>

                                <?php if ($editUser && $isSuperAdmin): ?>
                                <div class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl flex items-center justify-between">
                                    <div class="min-w-0">
                                        <span class="block text-xs font-bold text-amber-900">MFA Login Grace Period</span>
                                        <?php if (!empty($editUser['mfa_grace_until']) && strtotime($editUser['mfa_grace_until']) > time()): ?>
                                            <span class="block text-[11px] text-amber-700">Active until <?= date('d M Y, H:i', strtotime($editUser['mfa_grace_until'])) ?></span>
                                        <?php else: ?>
                                            <span class="block text-[11px] text-amber-700">Allow 3 days login bypass if user lost their authenticator.</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($editUser['mfa_grace_until']) && strtotime($editUser['mfa_grace_until']) > time()): ?>
                                        <a href="users.php?revoke_grace=<?= $editUser['id'] ?>" class="px-2.5 py-1 bg-white hover:bg-amber-100 text-rose-700 text-xs font-bold rounded-lg border border-amber-300 transition shrink-0">Revoke</a>
                                    <?php else: ?>
                                        <a href="users.php?grant_grace=<?= $editUser['id'] ?>" class="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-lg transition shrink-0">Grant 3 Days</a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Submission Buttons -->
                            <div class="flex items-center gap-3 pt-4">
                                <button type="submit" class="flex-1 py-3 bg-brand-700 hover:bg-brand-800 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm transition flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-check"></i>
                                    <span><?= $editUser ? 'Save Updates' : 'Create Staff Account' ?></span>
                                </button>
                                <?php if ($editUser): ?>
                                    <a href="users.php" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold uppercase tracking-wider text-center transition">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Staff Accounts Directory Card -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <!-- Action & Filter Bar -->
                <div class="p-4 sm:p-5 border-b border-slate-200 bg-white flex flex-col md:flex-row justify-between md:items-center gap-4">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 flex-1 max-w-xl">
                        <!-- Search Box -->
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            </span>
                            <input type="text" id="userSearchInput" onkeyup="filterUsers()" placeholder="Search staff by name, username, email or role..." class="w-full pl-9 pr-8 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:bg-white focus:border-brand-700 transition">
                            <button type="button" id="clearSearchBtn" onclick="clearUserSearch()" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 hidden" aria-label="Clear Search">
                                <i class="fa-solid fa-xmark text-xs"></i>
                            </button>
                        </div>

                        <!-- Role Filter Select -->
                        <div class="sm:w-48">
                            <select id="roleFilterSelect" onchange="filterUsers()" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-700 focus:outline-none focus:border-brand-700 transition">
                                <option value="all">All Roles (<?= $totalStaff ?>)</option>
                                <?php if ($isSuperAdmin): ?>
                                    <option value="super_admin">Super Admins</option>
                                <?php endif; ?>
                                <?php foreach ($availableRoles as $r): ?>
                                    <?php if ($r['role_key'] !== 'super_admin'): ?>
                                        <option value="<?= htmlspecialchars($r['role_key']) ?>"><?= htmlspecialchars($r['role_name']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Right Controls -->
                    <div class="flex items-center gap-2.5 shrink-0">
                        <span id="filteredCountBadge" class="text-xs text-slate-500 font-semibold px-2.5 py-1 bg-slate-100 rounded-lg">
                            <?= $totalStaff ?> accounts
                        </span>

                        <?php if (!$editUser): ?>
                        <button type="button" id="toggleFormBtn" onclick="toggleStaffForm()" class="px-4 py-2.5 bg-brand-700 hover:bg-brand-800 text-white rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-xs">
                            <i id="toggleFormIcon" class="fa-solid <?= $isFormExpanded ? 'fa-chevron-up' : 'fa-plus' ?>"></i>
                            <span id="toggleFormText"><?= $isFormExpanded ? 'Close Form' : 'Add Staff Member' ?></span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="overflow-x-auto" style="-webkit-overflow-scrolling: touch;">
                    <table id="staffTable" class="w-full text-left border-collapse min-w-[760px]">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 font-bold text-xs uppercase tracking-wider">
                                <th class="px-5 py-3.5">Staff Member</th>
                                <th class="px-4 py-3.5 text-center font-mono">Username</th>
                                <th class="px-4 py-3.5 text-center">Assigned Roles</th>
                                <th class="px-4 py-3.5 text-center">2FA / MFA</th>
                                <th class="px-4 py-3.5 text-center">Status</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="staffTableBody" class="divide-y divide-slate-100 text-xs">
                            <?php foreach ($users as $u): 
                                $fn = trim($u['firstname'] ?? '');
                                $ln = trim($u['lastname'] ?? '');
                                $fullName = trim($fn . ' ' . $ln);
                                if (empty($fullName)) $fullName = $u['username'];
                                
                                $initials = strtoupper(substr($fn ?: $u['username'], 0, 1) . substr($ln ?: substr($u['username'], 1, 1), 0, 1));
                                
                                $uRoles = [];
                                if (!empty($u['roles_json'])) {
                                    $dec = json_decode($u['roles_json'], true);
                                    if (is_array($dec)) $uRoles = $dec;
                                }
                                if (!empty($u['role']) && !in_array($u['role'], $uRoles)) {
                                    $uRoles[] = $u['role'];
                                }
                                if (empty($uRoles)) $uRoles = [$u['role'] ?? 'admin'];

                                $isUserSuperAdmin = in_array('super_admin', $uRoles);
                                $rolesFilterData = implode(' ', $uRoles);

                                $hasGrace = (!empty($u['mfa_grace_until']) && strtotime($u['mfa_grace_until']) > time());
                            ?>
                            <tr class="hover:bg-slate-50/80 transition staff-row" 
                                data-name="<?= htmlspecialchars(strtolower($fullName)) ?>" 
                                data-username="<?= htmlspecialchars(strtolower($u['username'])) ?>" 
                                data-email="<?= htmlspecialchars(strtolower($u['email'])) ?>" 
                                data-roles="<?= htmlspecialchars(strtolower($rolesFilterData)) ?>">
                                
                                <!-- Staff Member Profile -->
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full <?= $isUserSuperAdmin ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-brand-50 text-brand-700 border border-brand-200' ?> flex items-center justify-center font-bold text-xs uppercase shadow-2xs shrink-0">
                                            <?= htmlspecialchars($initials) ?>
                                        </div>
                                        <div class="min-w-0">
                                            <span class="block font-bold text-slate-900 truncate"><?= htmlspecialchars($fullName) ?></span>
                                            <span class="block text-[11px] text-slate-500 truncate flex items-center gap-1.5">
                                                <i class="fa-regular fa-envelope text-[10px] text-slate-400"></i>
                                                <?= htmlspecialchars($u['email']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Username -->
                                <td class="px-4 py-3.5 text-center">
                                    <span class="px-2.5 py-1 rounded-lg font-mono font-bold text-[11.5px] bg-slate-100 text-slate-700 border border-slate-200">
                                        @<?= htmlspecialchars($u['username']) ?>
                                    </span>
                                </td>

                                <!-- Assigned Roles -->
                                <td class="px-4 py-3.5 text-center">
                                    <div class="flex flex-wrap gap-1.5 justify-center">
                                        <?php foreach ($uRoles as $ur): 
                                            $lbl = ($ur === 'secretary' || $ur === 'admissions_registry') ? 'Admissions Registry' : ucwords(str_replace('_', ' ', $ur));
                                            $isRoot = ($ur === 'super_admin');
                                        ?>
                                            <span class="px-2.5 py-0.5 rounded-full text-[10.5px] font-bold <?= $isRoot ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-slate-100 text-slate-700 border border-slate-200' ?> shadow-2xs">
                                                <?= htmlspecialchars($lbl) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>

                                <!-- MFA Status -->
                                <td class="px-4 py-3.5 text-center">
                                    <?php if ($hasGrace): 
                                        $daysLeft = max(1, (int)ceil((strtotime($u['mfa_grace_until']) - time()) / 86400));
                                    ?>
                                        <div class="inline-flex flex-col items-center">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10.5px] font-bold bg-amber-100 text-amber-800 border border-amber-300" title="Grace bypass active until <?= htmlspecialchars($u['mfa_grace_until']) ?>">
                                                <i class="fa-solid fa-hourglass-half text-[9px] mr-1.5"></i> Grace (<?= $daysLeft ?>d)
                                            </span>
                                            <?php if ($isSuperAdmin): ?>
                                                <a href="users.php?revoke_grace=<?= $u['id'] ?>" class="text-[9.5px] text-rose-600 hover:underline mt-0.5 font-bold">Revoke Grace</a>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif (!empty($u['mfa_enabled'])): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10.5px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i class="fa-solid fa-shield-check text-[10px] mr-1.5 text-emerald-600"></i> Enrolled
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10.5px] font-medium bg-slate-100 text-slate-500 border border-slate-200">
                                            <i class="fa-solid fa-minus text-[8px] mr-1 text-slate-400"></i> Pending Setup
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status -->
                                <td class="px-4 py-3.5 text-center">
                                    <?php if($u['must_change_password'] == 1): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10.5px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                            <i class="fa-solid fa-key text-[9px] mr-1 text-amber-600"></i> Force Reset
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10.5px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i class="fa-solid fa-circle text-[6px] mr-1.5 text-emerald-500"></i> Active
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
                                <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-1.5">
                                        <a href="users.php?edit=<?= $u['id'] ?>" class="p-1.5 px-2.5 text-slate-700 hover:text-brand-700 hover:bg-slate-100 rounded-lg text-xs font-bold transition inline-flex items-center gap-1 border border-transparent hover:border-slate-200" title="Edit User">
                                            <i class="fa-solid fa-pen text-[10px]"></i>
                                            <span class="hidden sm:inline">Edit</span>
                                        </a>

                                        <?php if ($isSuperAdmin && !$hasGrace): ?>
                                            <a href="users.php?grant_grace=<?= $u['id'] ?>" class="p-1.5 px-2.5 text-amber-600 hover:text-amber-800 hover:bg-amber-50 rounded-lg text-xs font-bold transition inline-flex items-center gap-1 border border-transparent hover:border-amber-200" title="Grant 3-day login grace without MFA">
                                                <i class="fa-solid fa-clock-rotate-left text-[10px]"></i>
                                                <span class="hidden sm:inline">Grace</span>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($u['username'] !== $_SESSION['admin_username'] && $u['role'] !== 'super_admin'): ?>
                                            <a href="users.php?delete=<?= $u['id'] ?>" onclick="return confirm('Are you sure you want to permanently delete user account @<?= htmlspecialchars($u['username']) ?>? This action cannot be undone.')" class="p-1.5 px-2.5 text-rose-600 hover:text-rose-800 hover:bg-rose-50 rounded-lg text-xs font-bold transition inline-flex items-center gap-1 border border-transparent hover:border-rose-200" title="Delete Account">
                                                <i class="fa-solid fa-trash text-[10px]"></i>
                                                <span class="hidden sm:inline">Delete</span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Empty Search State -->
                    <div id="noStaffMatches" class="hidden p-10 text-center">
                        <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto text-lg mb-3">
                            <i class="fa-solid fa-user-slash"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700">No matching staff accounts</h4>
                        <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">No staff members match the query. Try adjusting your search term or role filter.</p>
                        <button type="button" onclick="clearUserSearch()" class="mt-3 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
                            Reset Search Filters
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Floating Back-to-Top Button (Strictly Round Circle) -->
    <button id="backToTopBtn" type="button" aria-label="Back to Top" onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
        class="fixed bottom-6 right-6 z-50 w-11 h-11 rounded-full flex items-center justify-center aspect-square text-white shadow-xl opacity-0 pointer-events-none transition-all duration-300 hover:scale-110 focus:outline-none"
        style="background-color: <?= htmlspecialchars($primary_color) ?>;">
        <i class="fa-solid fa-arrow-up text-sm"></i>
    </button>

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

    function toggleStaffForm() {
        const container = document.getElementById('staffFormContainer');
        const icon = document.getElementById('toggleFormIcon');
        const text = document.getElementById('toggleFormText');
        if (!container) return;

        const isHidden = container.classList.contains('hidden');
        if (isHidden) {
            container.classList.remove('hidden');
            if (icon) {
                icon.classList.remove('fa-plus');
                icon.classList.add('fa-chevron-up');
            }
            if (text) text.innerText = 'Close Form';
            container.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            container.classList.add('hidden');
            if (icon) {
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-plus');
            }
            if (text) text.innerText = 'Add Staff Member';
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
            const icon = el.querySelector('i');
            if (pass) {
                el.classList.remove('text-slate-500');
                el.classList.add('text-emerald-600', 'font-semibold');
                if (icon) {
                    icon.className = 'fa-solid fa-check text-[9px] text-emerald-600';
                }
            } else {
                el.classList.remove('text-emerald-600', 'font-semibold');
                el.classList.add('text-slate-500');
                if (icon) {
                    icon.className = 'fa-solid fa-circle text-[7px] text-slate-300';
                }
            }
        }
        update('rule-length', pw.length >= 8);
        update('rule-upper', /[A-Z]/.test(pw));
        update('rule-lower', /[a-z]/.test(pw));
        update('rule-number', /[0-9]/.test(pw));
        update('rule-symbol', /[\W_]/.test(pw));
    }

    // Client-side instant filter by search input & role dropdown
    function filterUsers() {
        const query = (document.getElementById('userSearchInput')?.value || '').toLowerCase().trim();
        const roleFilter = (document.getElementById('roleFilterSelect')?.value || 'all').toLowerCase().trim();
        const rows = document.querySelectorAll('.staff-row');
        const clearBtn = document.getElementById('clearSearchBtn');
        const emptyState = document.getElementById('noStaffMatches');
        const countBadge = document.getElementById('filteredCountBadge');

        if (clearBtn) {
            if (query.length > 0) {
                clearBtn.classList.remove('hidden');
            } else {
                clearBtn.classList.add('hidden');
            }
        }

        let visibleCount = 0;
        rows.forEach(function(row) {
            const name = row.getAttribute('data-name') || '';
            const username = row.getAttribute('data-username') || '';
            const email = row.getAttribute('data-email') || '';
            const roles = row.getAttribute('data-roles') || '';

            const matchesQuery = !query || name.includes(query) || username.includes(query) || email.includes(query) || roles.includes(query);
            const matchesRole = (roleFilter === 'all') || roles.split(' ').includes(roleFilter);

            if (matchesQuery && matchesRole) {
                row.classList.remove('hidden');
                visibleCount++;
            } else {
                row.classList.add('hidden');
            }
        });

        if (emptyState) {
            if (visibleCount === 0 && rows.length > 0) {
                emptyState.classList.remove('hidden');
            } else {
                emptyState.classList.add('hidden');
            }
        }

        if (countBadge) {
            countBadge.innerText = visibleCount + ' accounts';
        }
    }

    function clearUserSearch() {
        const searchInput = document.getElementById('userSearchInput');
        const roleSelect = document.getElementById('roleFilterSelect');
        if (searchInput) searchInput.value = '';
        if (roleSelect) roleSelect.value = 'all';
        filterUsers();
    }

    // Round Back-to-Top button behavior
    const bttBtn = document.getElementById('backToTopBtn');
    if (bttBtn) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 250) {
                bttBtn.classList.remove('opacity-0', 'pointer-events-none');
                bttBtn.classList.add('opacity-100', 'pointer-events-auto');
            } else {
                bttBtn.classList.add('opacity-0', 'pointer-events-none');
                bttBtn.classList.remove('opacity-100', 'pointer-events-auto');
            }
        });
    }
    </script>
</body>
</html>
