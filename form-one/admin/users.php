<?php
session_start();
require 'auth.php';
require '../config/db.php';

$school_short_name = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$logo_source       = getSchoolLogoSrc($pdo, '../../');
$academic_year     = getAcademicYear($pdo);

// Secretary role cannot access user management
if ($_SESSION['admin_role'] === 'secretary') {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

// Edit Mode Loading
$editUser = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    if ($_SESSION['admin_role'] !== 'super_admin') {
        $error = "Only Super Administrators can edit users.";
    } else {
        $editId = (int)$_GET['edit'];
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
        $stmt->execute([$editId]);
        $editUser = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Handle Delete User
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if ($_SESSION['admin_role'] !== 'super_admin') {
        $error = "Only Super Administrators can delete users.";
    } else {
        $deleteId = (int)$_GET['delete'];
        
        // Fetch username first for logging
        $userQuery = $pdo->prepare("SELECT username FROM admin_users WHERE id = ?");
        $userQuery->execute([$deleteId]);
        $delUsername = $userQuery->fetchColumn();

        if (!$delUsername) {
            $error = "User not found.";
        } elseif ($deleteId == $_SESSION['admin_id'] || $delUsername === $_SESSION['admin_username']) {
            $error = "You cannot delete your own account.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
            $stmt->execute([$deleteId]);

            // Log activity
            logActivity($pdo, $_SESSION['admin_username'], "Delete User", "Deleted user account: '$delUsername'");

            $success = "User account deleted successfully.";
        }
    }
}

// Handle Add or Update User Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_SESSION['admin_role'] !== 'super_admin') {
        $error = "Only Super Administrators can modify users.";
    } else {
        $action = $_POST['action'] ?? '';

        $firstname = trim($_POST['firstname']);
        $lastname = trim($_POST['lastname']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];

        if ($action === 'add') {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $forceExpiry = isset($_POST['force_expiry']) ? 1 : 0;

            if (empty($firstname) || empty($lastname) || empty($email) || empty($username) || empty($password)) {
                $error = "All fields are required for new users.";
            } else {
                // Check duplicate username
                $check = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username = ?");
                $check->execute([$username]);
                if ($check->fetchColumn() > 0) {
                    $error = "Username already exists.";
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO admin_users (firstname, lastname, email, username, password, role, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$firstname, $lastname, $email, $username, $hashed, $role, $forceExpiry]);

                    // Log activity
                    logActivity($pdo, $_SESSION['admin_username'], "Add User", "Created admin user: '$username' ($firstname $lastname) with role '$role'");

                    $success = "User account created successfully.";
                }
            }
        } elseif ($action === 'update') {
            $userId = (int)$_POST['user_id'];
            $password = $_POST['password'];
            $forceExpiry = isset($_POST['force_expiry']) ? 1 : 0;

            // Fetch current username for log
            $unameQuery = $pdo->prepare("SELECT username FROM admin_users WHERE id = ?");
            $unameQuery->execute([$userId]);
            $tgtUsername = $unameQuery->fetchColumn();

            if (!$tgtUsername) {
                $error = "Target user not found.";
            } elseif (empty($firstname) || empty($lastname) || empty($email)) {
                $error = "Name and email fields cannot be empty.";
            } else {
                if (!empty($password)) {
                    // Update password and force expiry
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE admin_users SET firstname = ?, lastname = ?, email = ?, password = ?, role = ?, must_change_password = 1 WHERE id = ?");
                    $stmt->execute([$firstname, $lastname, $email, $hashed, $role, $userId]);
                    
                    logActivity($pdo, $_SESSION['admin_username'], "Reset Password", "Reset password and forced expiry for user: '$tgtUsername'");
                } else {
                    // Update details only
                    $stmt = $pdo->prepare("UPDATE admin_users SET firstname = ?, lastname = ?, email = ?, role = ?, must_change_password = ? WHERE id = ?");
                    $stmt->execute([$firstname, $lastname, $email, $role, $forceExpiry, $userId]);

                    logActivity($pdo, $_SESSION['admin_username'], "Edit User", "Updated details for user: '$tgtUsername'");
                }

                $success = "User account updated successfully.";
                // Clear edit mode
                header("Location: users.php?success=" . urlencode($success));
                exit;
            }
        }
    }
}

if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Fetch all users
$stmt = $pdo->query("SELECT * FROM admin_users ORDER BY username ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management (S1) | <?= htmlspecialchars($school_short_name) ?></title>
<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<?= renderThemeConfig($pdo) ?>
</head>
<body class="bg-slate-100 font-sans text-slate-800 min-height-screen flex">

    <!-- Sidebar (Cruip dark style) -->
    <aside class="w-64 bg-slate-900 text-slate-100 flex flex-col shrink-0 min-h-screen">
        <!-- Brand Header -->
        <div class="px-6 py-5 border-b border-slate-800 flex items-center gap-3">
            <img src="<?= htmlspecialchars($logo_source) ?>" alt="Logo" class="w-10 h-10 bg-white rounded-full p-1 border-2 border-gold-500 object-contain">
            <div>
                <span class="block text-sm font-bold text-white tracking-tight leading-tight uppercase"><?= htmlspecialchars($school_short_name) ?></span>
                <span class="block text-xs font-semibold text-gold-500 uppercase tracking-widest">S1 Admin Portal</span>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 px-4 py-6 space-y-1.5">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard (S1)
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-graduation-cap text-lg"></i> Admitted List (S1)
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-file-invoice text-lg text-gold-500"></i> Custom Reports
            </a>
            <?php if ($_SESSION['admin_role'] !== 'secretary'): ?>
            <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition duration-150">
                <i class="fa-solid fa-users text-lg"></i> User Management
            </a>
            <a href="logs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-list-check text-lg"></i> Activity Logs
            </a>
            <?php endif; ?>
            <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
            <a href="../../app/settings.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-gear text-lg text-gold-500"></i> System Settings
            </a>
            <?php endif; ?>
            <div class="pt-6 border-t border-slate-800 my-4"></div>
            <a href="../../form-five/admin/dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-right-left text-lg text-gold-500"></i> Go to S5 Portal
            </a>
        </nav>

        <!-- Footer Note -->
        <div class="px-6 py-4 border-t border-slate-800 text-xs text-slate-500 text-center">
            Admissions Office · <?= htmlspecialchars($academic_year) ?>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        
        <!-- Header -->
        <header class="bg-white border-b border-slate-200 px-8 py-4 flex justify-between items-center shadow-sm relative z-10">
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">System User Accounts Management</h1>

            <!-- User Profile Dropdown -->
            <div class="relative">
                <button id="profileDropdownBtn" onclick="toggleDropdown()" class="flex items-center gap-2 focus:outline-none py-1.5 px-3 rounded-full hover:bg-slate-100 transition duration-150">
                    <span class="text-sm font-bold text-slate-700"><?= htmlspecialchars($_SESSION['admin_username']) ?></span>
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-brand-700 text-white font-bold text-xs uppercase">
                        <?= substr($_SESSION['admin_username'], 0, 2) ?>
                    </span>
                    <i class="fa-solid fa-chevron-down text-slate-400 text-xs"></i>
                </button>

                <!-- Dropdown Card -->
                <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-slate-200 rounded-xl shadow-lg py-2 z-20">
                    <div class="px-4 py-2 border-b border-slate-100">
                        <span class="block text-xs font-bold text-slate-400 uppercase">Role</span>
                        <span class="block text-sm font-semibold text-slate-700 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $_SESSION['admin_role'])) ?></span>
                    </div>
                    <a href="change_password.php" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition duration-150">
                        <i class="fa-solid fa-key text-slate-400"></i> Change Password
                    </a>
                    <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-rose-600 hover:bg-rose-50 transition duration-150">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </a>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="flex-1 px-8 py-6 space-y-6">

            <!-- Alerts -->
            <?php if($error): ?>
            <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-800 p-4 rounded-lg flex items-center gap-3 shadow-sm">
                <i class="fa-solid fa-circle-exclamation text-xl text-rose-500"></i>
                <span class="text-sm font-semibold"><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <?php if($success): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 p-4 rounded-lg flex items-center gap-3 shadow-sm">
                <i class="fa-solid fa-circle-check text-xl text-emerald-500"></i>
                <span class="text-sm font-semibold"><?= htmlspecialchars($success) ?></span>
            </div>
            <?php endif; ?>

            <!-- Split Form & Table -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- List Card -->
                <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                        <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider">Active Administrators</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-100 border-b border-slate-200 text-slate-500 font-bold text-xs uppercase tracking-wider">
                                    <th class="px-6 py-3.5">Name</th>
                                    <th class="px-6 py-3.5">Email</th>
                                    <th class="px-6 py-3.5">Username</th>
                                    <th class="px-6 py-3.5">Role</th>
                                    <th class="px-6 py-3.5 text-center">Pwd Reset</th>
                                    <th class="px-6 py-3.5 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                <?php foreach ($users as $u): ?>
                                <tr class="hover:bg-slate-50 transition duration-150">
                                    <td class="px-6 py-4 font-bold text-slate-800"><?= htmlspecialchars($u['firstname'] . ' ' . $u['lastname'] ?: 'N/A') ?></td>
                                    <td class="px-6 py-4 text-slate-600 font-semibold"><?= htmlspecialchars($u['email'] ?: 'N/A') ?></td>
                                    <td class="px-6 py-4 font-mono font-semibold text-slate-600"><?= htmlspecialchars($u['username']) ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-0.5 text-xs font-bold rounded-lg capitalize <?= $u['role'] === 'super_admin' ? 'bg-amber-100 text-amber-800' : ($u['role'] === 'secretary' ? 'bg-sky-100 text-sky-800' : 'bg-slate-100 text-slate-800') ?>">
                                            <?= htmlspecialchars(str_replace('_', ' ', $u['role'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($u['must_change_password'] == 1): ?>
                                            <span class="text-xs text-amber-600 font-bold bg-amber-50 px-2 py-1 rounded border border-amber-200"><i class="fa-solid fa-key"></i> Expired (First Login)</span>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 font-bold"><i class="fa-solid fa-check"></i> Standard</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-center space-x-1.5 whitespace-nowrap">
                                        <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
                                            <a href="users.php?edit=<?= $u['id'] ?>" class="text-xs font-bold text-white bg-brand-700 hover:bg-brand-800 px-2.5 py-1 rounded transition duration-150"><i class="fa-solid fa-user-pen"></i> Edit</a>
                                            <?php if ($u['id'] != $_SESSION['admin_id'] && $u['username'] !== $_SESSION['admin_username']): ?>
                                                <a href="users.php?delete=<?= $u['id'] ?>" onclick="return confirm('Are you sure you want to delete this user? This cannot be undone.')" class="text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 px-2.5 py-1 rounded transition duration-150"><i class="fa-solid fa-trash-can"></i> Delete</a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 italic">None</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Add/Edit Form Card -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 flex flex-col h-fit">
                    <?php if ($editUser): ?>
                        <!-- EDIT MODE -->
                        <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider mb-4 border-b border-slate-100 pb-3 flex justify-between items-center">
                            <span>Edit Account</span>
                            <a href="users.php" class="text-xs font-bold text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i> Cancel</a>
                        </h3>
                        <form method="POST" autocomplete="off" class="space-y-4">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
                            
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">First Name</label>
                                <input type="text" name="firstname" value="<?= htmlspecialchars($editUser['firstname']) ?>" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700">
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Second Name (Last Name)</label>
                                <input type="text" name="lastname" value="<?= htmlspecialchars($editUser['lastname']) ?>" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Email Address</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($editUser['email']) ?>" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Username (Read-Only)</label>
                                <input type="text" disabled value="<?= htmlspecialchars($editUser['username']) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-400">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Reset Password (Optional)</label>
                                <input type="password" name="password" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 placeholder:text-slate-300" placeholder="Leave blank to keep same">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Role Privilege</label>
                                <select name="role" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 bg-white">
                                    <option value="admin" <?= $editUser['role'] === 'admin' ? 'selected' : '' ?>>Administrator (admin)</option>
                                    <option value="super_admin" <?= $editUser['role'] === 'super_admin' ? 'selected' : '' ?>>Super Administrator (super_admin)</option>
                                    <option value="secretary" <?= $editUser['role'] === 'secretary' ? 'selected' : '' ?>>School Secretary (secretary)</option>
                                </select>
                            </div>

                            <div class="flex items-center gap-2">
                                <input type="checkbox" name="force_expiry" id="force_expiry" value="1" <?= $editUser['must_change_password'] == 1 ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-700 focus:ring-brand-700 h-4 w-4">
                                <label for="force_expiry" class="text-xs font-bold text-slate-600">Force Password Change on Login</label>
                            </div>

                            <button type="submit" class="w-full py-2.5 bg-brand-700 hover:bg-brand-800 text-white rounded-lg text-sm font-bold shadow-sm transition duration-150 flex justify-center items-center gap-1.5">
                                <i class="fa-solid fa-floppy-disk"></i> Save Changes
                            </button>
                        </form>
                    <?php else: ?>
                        <!-- CREATE MODE -->
                        <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider mb-4 border-b border-slate-100 pb-3">Create Admin User</h3>
                        <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
                            <form method="POST" autocomplete="off" class="space-y-4">
                                <input type="hidden" name="action" value="add">
                                
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">First Name</label>
                                    <input type="text" name="firstname" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700" placeholder="John">
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Second Name (Last Name)</label>
                                    <input type="text" name="lastname" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700" placeholder="Doe">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Email Address</label>
                                    <input type="email" name="email" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700" placeholder="user@school.ac.ug">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Username</label>
                                    <input type="text" name="username" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700" placeholder="jdoe">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Password</label>
                                    <input type="password" name="password" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700" placeholder="••••••••">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Role Privilege</label>
                                    <select name="role" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 bg-white">
                                        <option value="admin">Administrator (admin)</option>
                                        <option value="super_admin">Super Administrator (super_admin)</option>
                                        <option value="secretary">School Secretary (secretary)</option>
                                    </select>
                                </div>

                                <div class="flex items-center gap-2">
                                    <input type="checkbox" name="force_expiry" id="new_force_expiry" value="1" checked class="rounded border-slate-300 text-brand-700 focus:ring-brand-700 h-4 w-4">
                                    <label for="new_force_expiry" class="text-xs font-bold text-slate-600">Force Password Change on Login</label>
                                </div>

                                <button type="submit" class="w-full py-2.5 bg-brand-700 hover:bg-brand-800 text-white rounded-lg text-sm font-bold shadow-sm transition duration-150 flex justify-center items-center gap-1.5 mt-2">
                                    <i class="fa-solid fa-user-plus"></i> Register User
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="flex-1 flex flex-col justify-center items-center text-center text-slate-400 py-8">
                                <i class="fa-solid fa-lock text-3xl mb-3 text-slate-300"></i>
                                <span class="text-sm font-medium">Access Restricted.<br>Only Super Administrators can create user accounts.</span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </div>

        </div>

    </main>

    <!-- Vanilla Javascript Profile Toggler -->
    <script>
    function toggleDropdown() {
        const drop = document.getElementById('profileDropdown');
        drop.classList.toggle('hidden');
    }
    window.addEventListener('click', function(e) {
        const btn = document.getElementById('profileDropdownBtn');
        const drop = document.getElementById('profileDropdown');
        if (btn && drop && !btn.contains(e.target) && !drop.contains(e.target)) {
            drop.classList.add('hidden');
        }
    });
    </script>
</body>
</html>
