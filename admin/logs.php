<?php
session_start();
require 'auth.php';
require '../config/db.php';

// Secretary role cannot view activity logs
if ($_SESSION['admin_role'] === 'secretary') {
    header("Location: dashboard.php");
    exit;
}

// Fetch last 150 activity logs
$stmt = $pdo->query("SELECT * FROM admin_logs ORDER BY id DESC LIMIT 150");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Logs | Admissions Central Panel</title>
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
            <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-users text-lg"></i> User Management
            </a>
            <a href="logs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition duration-150">
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
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">System Activity Logs</h1>
            <span class="text-sm font-bold text-slate-600">Admin: <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
        </header>

        <div class="flex-1 px-8 py-6 space-y-6">

            <!-- Logs Registry Table Card -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider">Recent Activity Logs (Last 150 Operations)</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200 text-slate-500 font-bold text-xs uppercase tracking-wider">
                                <th class="px-6 py-3.5 text-center">ID</th>
                                <th class="px-6 py-3.5">User</th>
                                <th class="px-6 py-3.5">Operation</th>
                                <th class="px-6 py-3.5">Details</th>
                                <th class="px-6 py-3.5 text-center">IP Address</th>
                                <th class="px-6 py-3.5 text-center">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php foreach ($logs as $l): ?>
                            <tr class="hover:bg-slate-50 transition duration-150">
                                <td class="px-6 py-4 text-center font-bold text-slate-400"><?= $l['id'] ?></td>
                                <td class="px-6 py-4 font-bold text-slate-700 font-mono text-xs"><?= htmlspecialchars($l['username']) ?></td>
                                <td class="px-6 py-4 font-semibold text-brand-700 text-xs uppercase tracking-wider"><?= htmlspecialchars($l['action']) ?></td>
                                <td class="px-6 py-4 text-slate-600 font-medium"><?= htmlspecialchars($l['details']) ?></td>
                                <td class="px-6 py-4 text-center font-mono text-xs text-slate-500"><?= htmlspecialchars($l['ip_address']) ?></td>
                                <td class="px-6 py-4 text-center font-mono text-xs text-slate-500"><?= htmlspecialchars($l['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (count($logs) === 0): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-slate-400 font-semibold">
                                    <i class="fa-solid fa-folder-open text-3xl block mb-2 text-slate-300"></i>
                                    No activity logs registered in the database.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

</body>
</html>
