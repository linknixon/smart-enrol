<?php
session_start();
require 'auth.php';
require '../config/db.php';

// Fetch dynamic configurations
$school_name = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_logo = getSetting($pdo, 'school_logo', 'https://www.ncedges.com/wp-content/uploads/2026/02/mutosp-logo.png');

$logo_source = $school_logo;
if (strpos($logo_source, 'http') === false) {
    $logo_source = '../' . $logo_source;
}

// Parse filter inputs
$classFilter = $_GET['class'] ?? 'ALL';
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];

if ($classFilter === 'S1') {
    $where[] = "class_of_entry = 'S1'";
} elseif ($classFilter === 'S5') {
    $where[] = "class_of_entry = 'S5'";
} elseif ($classFilter === 'OTHER') {
    $where[] = "class_of_entry IN ('S2', 'S3', 'S4', 'S6')";
} elseif (in_array($classFilter, ['S2', 'S3', 'S4', 'S6'])) {
    $where[] = "class_of_entry = ?";
    $params[] = $classFilter;
}

if ($search !== '') {
    $where[] = "(firstname LIKE ? OR surname LIKE ? OR index_number LIKE ? OR parent_name LIKE ? OR former_school LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "SELECT * FROM applications";
if (count($where) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics (Independent overall counts)
$totalApps = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$pendingCount = $pdo->query("SELECT COUNT(*) FROM applications WHERE application_status = 'pending'")->fetchColumn();
$admittedCount = $pdo->query("SELECT COUNT(*) FROM applications WHERE application_status = 'admitted'")->fetchColumn();
$rejectedCount = $pdo->query("SELECT COUNT(*) FROM applications WHERE application_status = 'rejected'")->fetchColumn();

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | Admissions Central Portal</title>
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

    <!-- Sidebar (Cruip dark design style) -->
    <aside class="w-64 bg-slate-900 text-slate-100 flex flex-col shrink-0 min-h-screen">
        <!-- Brand Header -->
        <div class="px-6 py-5 border-b border-slate-800 flex items-center gap-3">
            <img src="<?= $logo_source ?>" alt="Logo" class="w-10 h-10 bg-white rounded-full p-1 border-2 border-gold-500 object-contain">
            <div>
                <span class="block text-sm font-bold text-white tracking-tight leading-tight uppercase"><?= htmlspecialchars(substr($school_name, 0, 15)) ?>...</span>
                <span class="block text-xs font-semibold text-gold-500 uppercase tracking-widest text-[9px]">Consolidated Portal</span>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 px-4 py-6 space-y-1.5">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition duration-150">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard (S1-S6)
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-graduation-cap text-lg"></i> Admitted Register
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-file-invoice text-lg text-gold-500"></i> Custom Reports
            </a>
            <?php if ($_SESSION['admin_role'] !== 'secretary'): ?>
            <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-users text-lg"></i> User Management
            </a>
            <a href="logs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-list-check text-lg"></i> Activity Logs
            </a>
            <?php endif; ?>
            <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-gears text-lg text-gold-500"></i> System Settings
            </a>
            <?php endif; ?>
        </nav>

        <div class="px-6 py-4 border-t border-slate-800 text-xs text-slate-500 text-center">
            Admissions Office · 2026
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        
        <!-- Header -->
        <header class="bg-white border-b border-slate-200 px-8 py-4 flex justify-between items-center shadow-sm relative z-10">
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">Admissions Control Dashboard</h1>

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

            <!-- Flash Message Alert -->
            <?php if($msg): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 p-4 rounded-lg flex items-center gap-3 shadow-sm animate-pulse">
                <i class="fa-solid fa-circle-check text-xl text-emerald-500"></i>
                <span class="text-sm font-semibold">Action completed successfully.</span>
            </div>
            <?php endif; ?>

            <!-- Metrics Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                <!-- Total -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Total Entries</span>
                        <span class="block text-3xl font-extrabold text-slate-800 mt-1"><?= $totalApps ?></span>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-folder-open"></i>
                    </div>
                </div>
                <!-- Pending -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Pending Review</span>
                        <span class="block text-3xl font-extrabold text-slate-800 mt-1"><?= $pendingCount ?></span>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                </div>
                <!-- Admitted -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Admitted Register</span>
                        <span class="block text-3xl font-extrabold text-slate-800 mt-1"><?= $admittedCount ?></span>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
                <!-- Rejected -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Rejected Requests</span>
                        <span class="block text-3xl font-extrabold text-slate-800 mt-1"><?= $rejectedCount ?></span>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </div>
                </div>
            </div>

            <!-- Filters Area -->
            <form method="GET" class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row justify-between gap-4 items-end">
                <div class="w-full md:w-1/4">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Filter by Entry Class</label>
                    <select name="class" onchange="this.form.submit()" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 bg-white">
                        <option value="ALL" <?= $classFilter === 'ALL' ? 'selected' : '' ?>>All Classes (S1-S6)</option>
                        <option value="S1" <?= $classFilter === 'S1' ? 'selected' : '' ?>>Senior One (S1)</option>
                        <option value="S2" <?= $classFilter === 'S2' ? 'selected' : '' ?>>Senior Two (S2)</option>
                        <option value="S3" <?= $classFilter === 'S3' ? 'selected' : '' ?>>Senior Three (S3)</option>
                        <option value="S4" <?= $classFilter === 'S4' ? 'selected' : '' ?>>Senior Four (S4)</option>
                        <option value="S5" <?= $classFilter === 'S5' ? 'selected' : '' ?>>Senior Five (S5)</option>
                        <option value="S6" <?= $classFilter === 'S6' ? 'selected' : '' ?>>Senior Six (S6)</option>
                        <option value="OTHER" <?= $classFilter === 'OTHER' ? 'selected' : '' ?>>Other / Transfers (S2, S3, S4, S6)</option>
                    </select>
                </div>

                <div class="w-full md:w-1/3">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Search Applications</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700" placeholder="Search by student, index, parent, school...">
                </div>

                <div class="flex gap-2 w-full md:w-auto">
                    <button type="submit" class="flex-1 md:flex-none px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white rounded-lg text-sm font-bold shadow-sm transition duration-150">
                        Search <i class="fa-solid fa-magnifying-glass ms-1"></i>
                    </button>
                    <a href="dashboard.php" class="flex-1 md:flex-none px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-sm font-bold shadow-sm transition duration-150 text-center">
                        Clear
                    </a>
                </div>
            </form>

            <!-- Table Card -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider">Candidate Registry</h3>
                    <span class="text-xs font-bold text-brand-700 bg-brand-50 border border-brand-200 px-2.5 py-1 rounded">Filtered Total: <?= count($applications) ?></span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200 text-slate-500 font-bold text-xs uppercase tracking-wider">
                                <th class="px-6 py-3.5 text-center">#</th>
                                <th class="px-6 py-3.5 text-center">Photo</th>
                                <th class="px-6 py-3.5">Student Full Name</th>
                                <th class="px-6 py-3.5 text-center">Class</th>
                                <th class="px-6 py-3.5 text-center">Index / ID</th>
                                <th class="px-6 py-3.5 text-center">Aggs</th>
                                <th class="px-6 py-3.5">Academic details</th>
                                <th class="px-6 py-3.5">Parent / Contact</th>
                                <th class="px-6 py-3.5 text-center">Record</th>
                                <th class="px-6 py-3.5 text-center">Status</th>
                                <th class="px-6 py-3.5 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php foreach ($applications as $app): 
                                $status = strtolower($app['application_status'] ?? 'pending');
                            ?>
                            <tr class="hover:bg-slate-50 transition duration-150">
                                <td class="px-6 py-4 text-center font-bold text-slate-400"><?= $app['id'] ?></td>
                                <td class="px-6 py-4 text-center">
                                    <?php if(!empty($app['passport_photo']) && file_exists('../' . $app['passport_photo'])): ?>
                                        <img src="../<?= $app['passport_photo'] ?>" class="w-10 h-10 object-cover rounded-full border border-slate-200 mx-auto">
                                    <?php else: ?>
                                        <span class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 text-lg mx-auto"><i class="fa-solid fa-user-ninja"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="block font-bold text-slate-800 leading-tight"><?= htmlspecialchars($app['firstname'].' '.$app['surname'].' '.$app['othernames']) ?></span>
                                    <span class="block text-xs text-slate-400 uppercase mt-0.5"><?= htmlspecialchars($app['sex'].' · Age: '.$app['age']) ?></span>
                                </td>
                                <td class="px-6 py-4 text-center font-bold text-slate-700"><?= htmlspecialchars($app['class_of_entry']) ?></td>
                                <td class="px-6 py-4 text-center font-mono text-xs text-slate-600"><?= htmlspecialchars($app['index_number'] ?? 'N/A') ?></td>
                                <td class="px-6 py-4 text-center font-bold text-brand-700"><?= htmlspecialchars($app['aggregates'] ?? 'N/A') ?></td>
                                <td class="px-6 py-4">
                                    <span class="block text-xs font-bold text-slate-700"><?= htmlspecialchars($app['former_school']) ?></span>
                                    <?php if($app['class_of_entry'] === 'S1'): ?>
                                        <span class="block text-xs text-slate-400 mt-0.5">PLE Division: <?= htmlspecialchars($app['division']) ?></span>
                                    <?php elseif($app['class_of_entry'] === 'S5'): ?>
                                        <span class="block text-xs text-gold-600 font-bold mt-0.5"><?= htmlspecialchars($app['combination']) ?> (<?= htmlspecialchars($app['division']) ?>)</span>
                                    <?php else: ?>
                                        <span class="block text-xs text-brand-700 font-semibold mt-0.5">Last Class: <?= htmlspecialchars($app['division']) ?></span>
                                        <span class="block text-[10px] text-slate-400 leading-tight mt-0.5" title="<?= htmlspecialchars($app['transfer_reason']) ?>">Reason: <?= htmlspecialchars(substr($app['transfer_reason'], 0, 30)) ?>...</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="block text-xs font-bold text-slate-700"><?= htmlspecialchars($app['parent_name']) ?></span>
                                    <span class="block text-xs text-slate-500 mt-0.5"><i class="fa-solid fa-phone text-[10px] text-slate-400"></i> <?= htmlspecialchars($app['parent_phone']) ?></span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if(!empty($app['result_slip']) && file_exists('../' . $app['result_slip'])): ?>
                                        <a href="../<?= $app['result_slip'] ?>" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 bg-sky-50 text-sky-700 hover:bg-sky-100 rounded-lg text-xs font-bold transition duration-150">
                                            <i class="fa-solid fa-file-pdf"></i> View
                                        </a>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400 font-bold">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if($status=='admitted'): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">Admitted</span>
                                    <?php elseif($status=='rejected'): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200">Rejected</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center space-y-1">
                                    <?php if ($_SESSION['admin_role'] !== 'secretary'): ?>
                                        <?php if($status=='pending'): ?>
                                            <a href="admit.php?id=<?= $app['id'] ?>" class="inline-flex items-center justify-center px-3 py-1 w-20 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition duration-150 shadow-sm">
                                                Admit
                                            </a>
                                            <a href="reject.php?id=<?= $app['id'] ?>" class="inline-flex items-center justify-center px-3 py-1 w-20 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-lg transition duration-150 shadow-sm">
                                                Reject
                                            </a>
                                        <?php elseif($status=='admitted'): ?>
                                            <a href="admission_offer.php?id=<?= $app['id'] ?>" target="_blank" class="inline-flex items-center justify-center px-3 py-1 w-20 text-xs font-bold text-white bg-sky-600 hover:bg-sky-700 rounded-lg transition duration-150 shadow-sm">
                                                Print Letter
                                            </a>
                                            <?php if($_SESSION['admin_role'] === 'super_admin'): ?>
                                                <a href="reverse.php?id=<?= $app['id'] ?>" class="inline-flex items-center justify-center px-3 py-1 w-20 text-xs font-bold text-white bg-amber-500 hover:bg-amber-600 rounded-lg transition duration-150 shadow-sm">
                                                    Reverse
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 font-bold">Closed</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if($status=='admitted'): ?>
                                            <a href="admission_offer.php?id=<?= $app['id'] ?>" target="_blank" class="inline-flex items-center justify-center px-3 py-1 w-20 text-xs font-bold text-white bg-sky-600 hover:bg-sky-700 rounded-lg transition duration-150 shadow-sm">
                                                Print Letter
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 font-semibold italic">View Only</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (count($applications) === 0): ?>
                            <tr>
                                <td colspan="11" class="px-6 py-12 text-center text-slate-400 font-semibold">
                                    <i class="fa-solid fa-folder-open text-3xl block mb-2 text-slate-300"></i>
                                    No application entries found.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

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
