<?php
require_once __DIR__ . '/auth.php';

$currentRole = $_SESSION['admin_role'] ?? '';
$isSuperAdmin = (($currentRole ?? '') === 'super_admin' || in_array('super_admin', $_SESSION['admin_roles'] ?? []));
if (!$isSuperAdmin && !hasPermission($pdo, $currentRole, 'can_manage_roles')) {
    header("Location: dashboard.php?error=unauthorized");
    exit;
}

$school_short_name  = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_name        = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$logo_source        = getSchoolLogoSrc($pdo, '../');
$school_website_url = getSchoolWebsiteUrl($pdo);
$adminFirstName     = getAdminDisplayName($pdo, $_SESSION['admin_username'] ?? '');
$primary_color      = getPrimaryColor($pdo);

$message = '';
$error = '';

// Handle Create / Edit / Delete Role
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_role') {
        $roleName = trim($_POST['role_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $roleKey = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', trim($_POST['role_key'] ?? '')));
        
        if (empty($roleKey)) {
            $roleKey = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $roleName));
        }

        $canSettings = isset($_POST['can_manage_settings']) ? 1 : 0;
        $canPolicies = isset($_POST['can_manage_admission_policies']) ? 1 : 0;
        $canAdmit    = isset($_POST['can_admit_students']) ? 1 : 0;
        $canReject   = isset($_POST['can_reject_students']) ? 1 : 0;
        $canReports  = isset($_POST['can_view_reports']) ? 1 : 0;
        $canExport   = isset($_POST['can_export_data']) ? 1 : 0;
        $canUsers    = isset($_POST['can_manage_users']) ? 1 : 0;
        $canRoles    = isset($_POST['can_manage_roles']) ? 1 : 0;
        $canAppActions = isset($_POST['can_take_application_actions']) ? 1 : 0;
        $canLogs     = isset($_POST['can_view_logs']) ? 1 : 0;

        if (empty($roleName) || empty($roleKey)) {
            $error = "Role Name and Identifier Key are required.";
        } else {
            // Check if key already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_roles WHERE role_key = ?");
            $stmt->execute([$roleKey]);
            if ($stmt->fetchColumn() > 0) {
                $error = "A role with identifier '{$roleKey}' already exists. Please choose another identifier.";
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO system_roles (role_key, role_name, description, can_manage_settings, can_manage_admission_policies, can_admit_students, can_reject_students, can_view_reports, can_export_data, can_manage_users, can_manage_roles, can_take_application_actions, can_view_logs, is_system)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
                ");
                $ins->execute([$roleKey, $roleName, $description, $canSettings, $canPolicies, $canAdmit, $canReject, $canReports, $canExport, $canUsers, $canRoles, $canAppActions, $canLogs]);
                logActivity($pdo, $_SESSION['admin_username'], "Create Role", "Created user role: {$roleName} ({$roleKey})");
                $message = "Role '{$roleName}' successfully created.";
            }
        }
    } elseif ($action === 'edit_role') {
        $roleId   = (int)($_POST['role_id'] ?? 0);
        $roleName = trim($_POST['role_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $canSettings = isset($_POST['can_manage_settings']) ? 1 : 0;
        $canPolicies = isset($_POST['can_manage_admission_policies']) ? 1 : 0;
        $canAdmit    = isset($_POST['can_admit_students']) ? 1 : 0;
        $canReject   = isset($_POST['can_reject_students']) ? 1 : 0;
        $canReports  = isset($_POST['can_view_reports']) ? 1 : 0;
        $canExport   = isset($_POST['can_export_data']) ? 1 : 0;
        $canUsers    = isset($_POST['can_manage_users']) ? 1 : 0;
        $canRoles    = isset($_POST['can_manage_roles']) ? 1 : 0;
        $canAppActions = isset($_POST['can_take_application_actions']) ? 1 : 0;
        $canLogs     = isset($_POST['can_view_logs']) ? 1 : 0;

        $checkStmt = $pdo->prepare("SELECT role_key, is_system FROM system_roles WHERE id = ?");
        $checkStmt->execute([$roleId]);
        $roleData = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$roleData) {
            $error = "Role not found.";
        } elseif ($roleData['role_key'] === 'super_admin' && !empty($roleData['is_system'])) {
            $error = "The core Super Administrator system role permissions cannot be altered.";
        } else {
            $upd = $pdo->prepare("
                UPDATE system_roles
                SET role_name = ?, description = ?, can_manage_settings = ?, can_manage_admission_policies = ?, can_admit_students = ?, can_reject_students = ?, can_view_reports = ?, can_export_data = ?, can_manage_users = ?, can_manage_roles = ?, can_take_application_actions = ?, can_view_logs = ?
                WHERE id = ?
            ");
            $upd->execute([$roleName, $description, $canSettings, $canPolicies, $canAdmit, $canReject, $canReports, $canExport, $canUsers, $canRoles, $canAppActions, $canLogs, $roleId]);
            logActivity($pdo, $_SESSION['admin_username'], "Update Role", "Updated role permissions for: {$roleName} ({$roleData['role_key']})");
            $message = "Role permissions for '{$roleName}' updated successfully.";
        }
    } elseif ($action === 'delete_role') {
        $roleId = (int)($_POST['role_id'] ?? 0);
        $checkStmt = $pdo->prepare("SELECT role_key, is_system FROM system_roles WHERE id = ?");
        $checkStmt->execute([$roleId]);
        $roleData = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$roleData) {
            $error = "Role not found.";
        } elseif ($roleData['is_system'] == 1) {
            $error = "Built-in system roles cannot be deleted.";
        } else {
            // Check if any users have this role
            $uStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE role = ?");
            $uStmt->execute([$roleData['role_key']]);
            $assignedCount = $uStmt->fetchColumn();
            if ($assignedCount > 0) {
                $error = "Cannot delete this role: {$assignedCount} active user(s) are currently assigned to it. Please reassign those users first.";
            } else {
                $del = $pdo->prepare("DELETE FROM system_roles WHERE id = ?");
                $del->execute([$roleId]);
                logActivity($pdo, $_SESSION['admin_username'], "Delete Role", "Deleted role: {$roleData['role_key']}");
                $message = "Role successfully removed.";
            }
        }
    }
}

// Fetch all roles (Hide super_admin if not super_admin)
$roles = getAllRoles($pdo);
$isSuperAdmin = (($currentRole ?? '') === 'super_admin');
if (!$isSuperAdmin) {
    $roles = array_filter($roles, function($r) {
        return ($r['role_key'] ?? '') !== 'super_admin';
    });
}

// Count users assigned to each role
$userCounts = [];
try {
    $cStmt = $pdo->query("SELECT role, COUNT(*) as cnt FROM admin_users " . ($isSuperAdmin ? "" : "WHERE role != 'super_admin' ") . "GROUP BY role");
    while ($row = $cStmt->fetch(PDO::FETCH_ASSOC)) {
        $userCounts[$row['role']] = (int)$row['cnt'];
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roles & Access Levels - <?= htmlspecialchars($school_short_name) ?></title>
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
            <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-users text-lg"></i> Staff Users
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_roles') || hasPermission($pdo, $currentRole, 'can_manage_users')): ?>
            <a href="roles.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-bold bg-white/20 text-white ring-1 ring-white/30 shadow-sm transition duration-150">
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
            <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-users text-lg"></i> Staff Users
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_roles') || hasPermission($pdo, $currentRole, 'can_manage_users')): ?>
            <a href="roles.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-bold bg-white/20 text-white ring-1 ring-white/30 shadow-sm transition duration-150">
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

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col min-w-0 bg-slate-50 min-h-screen">
        <!-- Top Header Bar -->
        <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-4 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
            <div class="flex items-center gap-3">
                <button type="button" onclick="toggleMobileSidebar()" class="md:hidden p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-xl focus:outline-none" aria-label="Toggle Navigation">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <h1 class="text-base sm:text-xl font-bold text-slate-900 tracking-tight">Staff Roles & Access Level Manager</h1>
                    <p class="text-xs text-slate-500 mt-0.5 hidden sm:block">Define custom administrative roles and manage granular feature access levels.</p>
                </div>
            </div>

            <!-- Profile & Actions Dropdown -->
            <div class="flex items-center gap-3 shrink-0 whitespace-nowrap flex-nowrap">
                <button type="button" onclick="document.getElementById('newRoleModal').classList.remove('hidden')" class="px-3.5 py-2 bg-brand-700 hover:bg-brand-800 text-white text-xs font-semibold rounded-xl shadow-sm transition inline-flex items-center gap-2 whitespace-nowrap flex-nowrap shrink-0">
                    <i class="fa-solid fa-plus"></i> Add New Role
                </button>

                <div class="relative shrink-0 whitespace-nowrap">
                    <button type="button" onclick="toggleDropdown()" class="flex items-center gap-2.5 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl hover:bg-slate-100 transition focus:outline-none whitespace-nowrap flex-nowrap">
                        <div class="w-8 h-8 rounded-full bg-brand-700 text-white flex items-center justify-center font-bold text-xs uppercase shadow-sm">
                            <?= strtoupper(substr($adminFirstName, 0, 1)) ?>
                        </div>
                        <div class="text-left hidden sm:block">
                            <span class="block text-xs font-bold text-slate-800">Welcome, <?= htmlspecialchars($adminFirstName) ?></span>
                            <span class="block text-[10px] text-slate-400 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $currentRole)) ?></span>
                        </div>
                        <i class="fa-solid fa-chevron-down text-slate-400 text-xs ml-1"></i>
                    </button>
                    <div id="userDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-slate-200 rounded-xl shadow-lg py-1 z-50">
                        <a href="change_password.php" class="block px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2">
                            <i class="fa-solid fa-key text-slate-400"></i> Change Password
                        </a>
                        <div class="border-t border-slate-100 my-1"></div>
                        <a href="logout.php" class="block px-4 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 flex items-center gap-2">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="p-6 max-w-7xl w-full mx-auto space-y-6">
            <?php if (!empty($message)): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold rounded-xl flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold rounded-xl flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Roles Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php foreach ($roles as $r): 
                    $roleKey = $r['role_key'];
                    $isSys = !empty($r['is_system']);
                    $assignedUsers = $userCounts[$roleKey] ?? 0;
                    $perms = getRolePermissions($pdo, $roleKey);
                ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <div>
                                <h3 class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                                    <?= htmlspecialchars($r['role_name']) ?>
                                    <?php if ($isSys): ?>
                                        <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-md uppercase tracking-wider">System</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 bg-brand-50 text-brand-700 text-[10px] font-bold rounded-md uppercase tracking-wider">Custom</span>
                                    <?php endif; ?>
                                </h3>
                                <span class="font-mono text-xs text-slate-400 font-medium"><?= htmlspecialchars($roleKey) ?></span>
                            </div>
                            <span class="px-2.5 py-1 bg-amber-50 text-amber-700 text-xs font-bold rounded-lg border border-amber-200/50">
                                <i class="fa-solid fa-user-check mr-1 text-[10px]"></i> <?= $assignedUsers ?> <?= $assignedUsers === 1 ? 'User' : 'Users' ?>
                            </span>
                        </div>

                        <p class="text-xs text-slate-500 leading-relaxed mb-4 min-h-[36px]">
                            <?= htmlspecialchars($r['description'] ?: 'Custom role with defined administrative access levels.') ?>
                        </p>

                        <!-- Access Levels Checklist -->
                        <div class="space-y-1.5 border-t border-slate-100 pt-3 text-xs">
                            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Access Privileges</div>
                            
                            <div class="flex items-center justify-between text-slate-700">
                                <span><i class="fa-solid fa-sliders w-4 text-slate-400 mr-1.5"></i> Full Settings (SMTP & Brand)</span>
                                <?= !empty($perms['can_manage_settings']) ? '<i class="fa-solid fa-check text-emerald-600 font-bold"></i>' : '<i class="fa-solid fa-xmark text-slate-300"></i>' ?>
                            </div>
                            <div class="flex items-center justify-between text-slate-700">
                                <span><i class="fa-solid fa-file-pen w-4 text-slate-400 mr-1.5"></i> Policies, Fees & Letters</span>
                                <?= (!empty($perms['can_manage_settings']) || !empty($perms['can_manage_admission_policies'])) ? '<i class="fa-solid fa-check text-emerald-600 font-bold"></i>' : '<i class="fa-solid fa-xmark text-slate-300"></i>' ?>
                            </div>
                            <div class="flex items-center justify-between text-slate-700">
                                <span><i class="fa-solid fa-user-plus w-4 text-slate-400 mr-1.5"></i> Admit Applicants</span>
                                <?= !empty($perms['can_admit_students']) ? '<i class="fa-solid fa-check text-emerald-600 font-bold"></i>' : '<i class="fa-solid fa-xmark text-slate-300"></i>' ?>
                            </div>
                            <div class="flex items-center justify-between text-slate-700">
                                <span><i class="fa-solid fa-user-xmark w-4 text-slate-400 mr-1.5"></i> Reject Applicants</span>
                                <?= !empty($perms['can_reject_students']) ? '<i class="fa-solid fa-check text-emerald-600 font-bold"></i>' : '<i class="fa-solid fa-xmark text-slate-300"></i>' ?>
                            </div>
                            <div class="flex items-center justify-between text-slate-700">
                                <span><i class="fa-solid fa-file-invoice w-4 text-slate-400 mr-1.5"></i> View Reports</span>
                                <?= !empty($perms['can_view_reports']) ? '<i class="fa-solid fa-check text-emerald-600 font-bold"></i>' : '<i class="fa-solid fa-xmark text-slate-300"></i>' ?>
                            </div>
                            <div class="flex items-center justify-between text-slate-700">
                                <span><i class="fa-solid fa-file-csv w-4 text-slate-400 mr-1.5"></i> Export CSV / PDF</span>
                                <?= !empty($perms['can_export_data']) ? '<i class="fa-solid fa-check text-emerald-600 font-bold"></i>' : '<i class="fa-solid fa-xmark text-slate-300"></i>' ?>
                            </div>
                            <div class="flex items-center justify-between text-slate-700">
                                <span><i class="fa-solid fa-users-gear w-4 text-slate-400 mr-1.5"></i> Manage Staff Accounts</span>
                                <?= !empty($perms['can_manage_users']) ? '<i class="fa-solid fa-check text-emerald-600 font-bold"></i>' : '<i class="fa-solid fa-xmark text-slate-300"></i>' ?>
                            </div>
                            <div class="flex items-center justify-between text-slate-700">
                                <span><i class="fa-solid fa-shield-halved w-4 text-slate-400 mr-1.5"></i> Manage Roles & Permissions</span>
                                <?= !empty($perms['can_manage_roles']) ? '<i class="fa-solid fa-check text-emerald-600 font-bold"></i>' : '<i class="fa-solid fa-xmark text-slate-300"></i>' ?>
                            </div>
                            <div class="flex items-center justify-between text-slate-700">
                                <span><i class="fa-solid fa-bolt w-4 text-slate-400 mr-1.5"></i> Actions (Enroll/Comb/Reverse)</span>
                                <?= !empty($perms['can_take_application_actions']) ? '<i class="fa-solid fa-check text-emerald-600 font-bold"></i>' : '<i class="fa-solid fa-xmark text-slate-300"></i>' ?>
                            </div>
                            <div class="flex items-center justify-between text-slate-700">
                                <span><i class="fa-solid fa-clock-rotate-left w-4 text-slate-400 mr-1.5"></i> View Audit Logs</span>
                                <?= !empty($perms['can_view_logs']) ? '<i class="fa-solid fa-check text-emerald-600 font-bold"></i>' : '<i class="fa-solid fa-xmark text-slate-300"></i>' ?>
                            </div>
                        </div>
                    </div>

                    <!-- Role Actions -->
                    <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-end gap-2 whitespace-nowrap flex-nowrap shrink-0">
                        <?php if ($roleKey !== 'super_admin'): ?>
                            <button type="button" onclick="openEditModal(<?= htmlspecialchars(json_encode($r)) ?>)" class="px-3 py-1.5 text-xs font-semibold text-slate-700 hover:text-brand-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition inline-flex items-center gap-1.5 whitespace-nowrap flex-nowrap shrink-0">
                                <i class="fa-solid fa-pen-to-square"></i> Edit
                            </button>
                            <?php if (!$isSys): ?>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to permanently delete role \'<?= htmlspecialchars($r['role_name']) ?>\'?');" class="inline m-0 p-0">
                                    <input type="hidden" name="action" value="delete_role">
                                    <input type="hidden" name="role_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 rounded-lg transition inline-flex items-center gap-1.5 whitespace-nowrap flex-nowrap shrink-0">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-[11px] text-slate-400 italic whitespace-nowrap">Core System Role</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <!-- Create Role Modal -->
    <div id="newRoleModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-2 sm:p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full max-h-[92vh] overflow-y-auto p-4 sm:p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-base font-bold text-slate-900">Define New System Role</h3>
                <button type="button" onclick="document.getElementById('newRoleModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-sm">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form method="POST" class="mt-4 space-y-4">
                <input type="hidden" name="action" value="create_role">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Role Title *</label>
                    <input type="text" name="role_name" required placeholder="e.g. Academic Registrar, Deputy Bursar" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:border-brand-700 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Unique Role Identifier Key *</label>
                    <input type="text" name="role_key" required placeholder="e.g. academic_registrar" class="w-full px-3 py-2 text-xs font-mono border border-slate-200 rounded-xl focus:border-brand-700 focus:outline-none">
                    <span class="text-[11px] text-slate-400">Lowercase letters, numbers, and underscores only.</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Description</label>
                    <textarea name="description" rows="2" placeholder="Brief outline of duties and responsibilities..." class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:border-brand-700 focus:outline-none"></textarea>
                </div>

                <div class="border-t border-slate-100 pt-3">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Access Privileges & Permissions</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_manage_settings" value="1" class="rounded text-brand-700">
                            <span>Full System Settings (SMTP/Brand)</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_manage_admission_policies" value="1" class="rounded text-brand-700">
                            <span>Intake Policies, Fees & Letters Only</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_admit_students" value="1" checked class="rounded text-brand-700">
                            <span>Admit Applicants</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_reject_students" value="1" checked class="rounded text-brand-700">
                            <span>Reject Applicants</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_view_reports" value="1" checked class="rounded text-brand-700">
                            <span>View Custom Reports</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_export_data" value="1" checked class="rounded text-brand-700">
                            <span>Export CSV / PDF</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_manage_users" value="1" class="rounded text-brand-700">
                            <span>Manage Staff Accounts</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_manage_roles" value="1" class="rounded text-brand-700">
                            <span>Manage Roles & Access</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_take_application_actions" value="1" checked class="rounded text-brand-700">
                            <span>Execute Dashboard Actions (Admit/Reject/Comb/Report)</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_view_logs" value="1" class="rounded text-brand-700">
                            <span>View Audit Logs</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                    <button type="button" onclick="document.getElementById('newRoleModal').classList.add('hidden')" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-xs font-semibold rounded-xl shadow-sm transition">
                        Save Role
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Role Modal -->
    <div id="editRoleModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-2 sm:p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full max-h-[92vh] overflow-y-auto p-4 sm:p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-base font-bold text-slate-900">Edit Role Privileges</h3>
                <button type="button" onclick="document.getElementById('editRoleModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-sm">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form method="POST" class="mt-4 space-y-4">
                <input type="hidden" name="action" value="edit_role">
                <input type="hidden" name="role_id" id="editRoleId">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Role Title *</label>
                    <input type="text" name="role_name" id="editRoleName" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:border-brand-700 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Description</label>
                    <textarea name="description" id="editDescription" rows="2" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:border-brand-700 focus:outline-none"></textarea>
                </div>

                <div class="border-t border-slate-100 pt-3">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Access Privileges</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_manage_settings" id="editCanSettings" value="1" class="rounded text-brand-700">
                            <span>Full System Settings (SMTP/Brand)</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_manage_admission_policies" id="editCanPolicies" value="1" class="rounded text-brand-700">
                            <span>Intake Policies, Fees & Letters Only</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_admit_students" id="editCanAdmit" value="1" class="rounded text-brand-700">
                            <span>Admit Applicants</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_reject_students" id="editCanReject" value="1" class="rounded text-brand-700">
                            <span>Reject Applicants</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_view_reports" id="editCanReports" value="1" class="rounded text-brand-700">
                            <span>View Custom Reports</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_export_data" id="editCanExport" value="1" class="rounded text-brand-700">
                            <span>Export CSV / PDF</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_manage_users" id="editCanUsers" value="1" class="rounded text-brand-700">
                            <span>Manage Staff Accounts</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_manage_roles" id="editCanRoles" value="1" class="rounded text-brand-700">
                            <span>Manage Roles & Access</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_take_application_actions" id="editCanAppActions" value="1" class="rounded text-brand-700">
                            <span>Execute Dashboard Actions (Admit/Reject/Comb/Report)</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="can_view_logs" id="editCanLogs" value="1" class="rounded text-brand-700">
                            <span>View Audit Logs</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                    <button type="button" onclick="document.getElementById('editRoleModal').classList.add('hidden')" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-xs font-semibold rounded-xl shadow-sm transition">
                        Update Role
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
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
        const drop = document.getElementById('userDropdown');
        drop.classList.toggle('hidden');
    }
    document.addEventListener('click', function(e) {
        const drop = document.getElementById('userDropdown');
        if (!e.target.closest('#userDropdown') && !e.target.closest('button[onclick="toggleDropdown()"]')) {
            drop.classList.add('hidden');
        }
    });

    function openEditModal(role) {
        document.getElementById('editRoleId').value = role.id;
        document.getElementById('editRoleName').value = role.role_name;
        document.getElementById('editDescription').value = role.description || '';
        document.getElementById('editCanSettings').checked = role.can_manage_settings == 1;
        document.getElementById('editCanPolicies').checked = role.can_manage_admission_policies == 1;
        document.getElementById('editCanAdmit').checked = role.can_admit_students == 1;
        document.getElementById('editCanReject').checked = role.can_reject_students == 1;
        document.getElementById('editCanReports').checked = role.can_view_reports == 1;
        document.getElementById('editCanExport').checked = role.can_export_data == 1;
        document.getElementById('editCanUsers').checked = role.can_manage_users == 1;
        document.getElementById('editCanRoles').checked = role.can_manage_roles == 1;
        document.getElementById('editCanAppActions').checked = role.can_take_application_actions == 1;
        document.getElementById('editCanLogs').checked = role.can_view_logs == 1;
        document.getElementById('editRoleModal').classList.remove('hidden');
    }
    </script>
</body>
</html>
