<?php
session_start();
require 'auth.php';
require '../config/db.php';

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
        } elseif ($delUsername === $_SESSION['admin_username']) {
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
<title>User Management | Admissions Control Panel</title>
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
                    50: '#edf5ef',
                    100: '#dbeade',
                    200: '#b7d5be',
                    700: '#0c4a24',
                    800: '#062f14',
                    900: '#031b0b',
                },
                gold: {
                    500: '#b58d2f',
                    600: '#8a661c',
                }
            }
        }
    }
}
</script>
</head>
<body class="bg-slate-100 font-sans text-slate-800 min-h-screen flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 text-slate-100 flex flex-col shrink-0 min-h-screen">
        <div class="px-6 py-5 border-b border-slate-800 flex items-center gap-3">
            <img src="https://www.ncedges.com/wp-content/uploads/2026/02/mutosp-logo.png" alt="Logo" class="w-10 h-10 bg-white rounded-full p-1 border-2 border-gold-500">
            <div>
                <span class="block text-sm font-bold text-white tracking-tight leading-tight">MUTOLERE SS</span>
                <span class="block text-xs font-semibold text-gold-500 uppercase tracking-widest text-[9px]">Consolidated Portal</span>
            </div>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1.5">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard (S1-S6)
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-graduation-cap text-lg"></i> Admitted Register
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-file-invoice text-lg text-gold-500"></i> Custom Reports
            </a>
            <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition duration-150">
                <i class="fa-solid fa-users text-lg"></i> User Management
            </a>
            <a href="logs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-list-check text-lg"></i> Activity Logs
            </a>
        </nav>

        <div class="px-6 py-4 border-t border-slate-800 text-xs text-slate-500 text-center">
            Admissions Office · 2026
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <header class="bg-white border-b border-slate-200 px-8 py-4 flex justify-between items-center shadow-sm relative z-10">
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">User Account Management</h1>
            <span class="text-sm font-bold text-slate-600">Admin: <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
        </header>

        <div class="flex-1 px-8 py-6 space-y-6">

            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-800 p-4 rounded-lg flex items-center gap-3 shadow-sm">
                    <i class="fa-solid fa-triangle-exclamation text-xl text-rose-500"></i>
                    <span class="text-sm font-semibold"><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 p-4 rounded-lg flex items-center gap-3 shadow-sm">
                    <i class="fa-solid fa-circle-check text-xl text-emerald-500"></i>
                    <span class="text-sm font-semibold"><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Form Section (Add / Edit User) -->
                <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex flex-col h-fit">
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider mb-4 border-b border-slate-100 pb-2">
                        <?= $editUser ? 'Edit User Credentials' : 'Create User Account' ?>
                    </h3>

                    <form method="POST" autocomplete="off" class="space-y-4">
                        <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'add' ?>">
                        <?php if ($editUser): ?>
                            <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
                        <?php endif; ?>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">First Name</label>
                            <input type="text" name="firstname" value="<?= $editUser ? htmlspecialchars($editUser['firstname']) : '' ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700" required>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Last Name / Second Name</label>
                            <input type="text" name="lastname" value="<?= $editUser ? htmlspecialchars($editUser['lastname']) : '' ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700" required>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Email Address</label>
                            <input type="email" name="email" value="<?= $editUser ? htmlspecialchars($editUser['email']) : '' ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700" required>
                        </div>

                        <?php if (!$editUser): ?>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Username</label>
                            <input type="text" name="username" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 font-mono" required>
                        </div>
                        <?php endif; ?>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">
                                <?= $editUser ? 'Reset Password (Leave blank to keep current)' : 'Password' ?>
                            </label>
                            <input type="password" name="password" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700" <?= $editUser ? '' : 'required' ?>>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">System Role</label>
                            <select name="role" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 bg-white" required>
                                <option value="admin" <?= ($editUser && $editUser['role'] === 'admin') ? 'selected' : '' ?>>Administrator (Editor)</option>
                                <option value="secretary" <?= ($editUser && $editUser['role'] === 'secretary') ? 'selected' : '' ?>>School Secretary (Viewer)</option>
                                <option value="super_admin" <?= ($editUser && $editUser['role'] === 'super_admin') ? 'selected' : '' ?>>Super Administrator</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2 py-2">
                            <input type="checkbox" id="forceExpiry" name="force_expiry" class="rounded border-slate-300 text-brand-700 focus:ring-brand-700 h-4 w-4" <?= ($editUser && $editUser['must_change_password'] == 1) ? 'checked' : '' ?>>
                            <label for="forceExpiry" class="text-xs text-slate-500 font-semibold cursor-pointer">Force password change on next login</label>
                        </div>

                        <div class="flex gap-2">
                            <button type="submit" class="flex-1 py-2 bg-brand-700 hover:bg-brand-800 text-white rounded-lg text-xs font-bold uppercase tracking-wider shadow-sm transition duration-150">
                                <?= $editUser ? 'Save Updates' : 'Add Account' ?>
                            </button>
                            <?php if ($editUser): ?>
                                <a href="users.php" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-xs font-bold uppercase tracking-wider text-center">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Table List Section -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col <?= ($_SESSION['admin_role'] === 'super_admin') ? 'lg:col-span-2' : 'lg:col-span-3' ?>">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                        <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider">User Account Registry</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-100 border-b border-slate-200 text-slate-500 font-bold text-xs uppercase tracking-wider">
                                    <th class="px-6 py-3">Full Name</th>
                                    <th class="px-6 py-3 font-mono text-center">Username</th>
                                    <th class="px-6 py-3">Email Address</th>
                                    <th class="px-6 py-3 text-center">System Role</th>
                                    <th class="px-6 py-3 text-center">Status</th>
                                    <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
                                    <th class="px-6 py-3 text-center">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                <?php foreach ($users as $u): ?>
                                <tr class="hover:bg-slate-50 transition duration-150">
                                    <td class="px-6 py-4 font-semibold text-slate-800"><?= htmlspecialchars($u['firstname'] . ' ' . $u['lastname']) ?></td>
                                    <td class="px-6 py-4 text-center font-mono font-bold text-slate-600"><?= htmlspecialchars($u['username']) ?></td>
                                    <td class="px-6 py-4 text-slate-500"><?= htmlspecialchars($u['email']) ?></td>
                                    <td class="px-6 py-4 text-center capitalize text-slate-600 font-semibold"><?= htmlspecialchars(str_replace('_', ' ', $u['role'])) ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if($u['must_change_password'] == 1): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">Force Reset</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
                                    <td class="px-6 py-4 text-center space-x-1.5 whitespace-nowrap">
                                        <a href="users.php?edit=<?= $u['id'] ?>" class="text-brand-700 hover:text-brand-900 font-bold"><i class="fa-solid fa-pen"></i> Edit</a>
                                        <?php if ($u['username'] !== $_SESSION['admin_username']): ?>
                                            <a href="users.php?delete=<?= $u['id'] ?>" onclick="return confirm('Are you sure you want to delete user <?= htmlspecialchars($u['username']) ?>?')" class="text-rose-600 hover:text-rose-800 font-bold"><i class="fa-solid fa-trash-can"></i> Delete</a>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </main>

</body>
</html>
