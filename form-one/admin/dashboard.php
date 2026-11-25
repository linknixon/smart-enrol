<?php
session_start();
require 'auth.php';
require '../config/db.php';

// Fetch dynamic configurations
$school_name       = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$logo_source       = getSchoolLogoSrc($pdo, '../../');
$academic_year     = getAcademicYear($pdo);

// Fetch applications
$stmt = $pdo->query("SELECT * FROM s1_applications ORDER BY id DESC");
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate metrics
$totalApps = count($applications);
$pendingCount = 0;
$admittedCount = 0;
$rejectedCount = 0;
foreach ($applications as $app) {
    $status = strtolower($app['application_status'] ?? 'pending');
    if ($status === 'admitted') {
        $admittedCount++;
    } elseif ($status === 'rejected') {
        $rejectedCount++;
    } else {
        $pendingCount++;
    }
}

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>S1 Admin Dashboard | <?= htmlspecialchars($school_short_name) ?></title>
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
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition duration-150">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard (S1)
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-graduation-cap text-lg"></i> Admitted List (S1)
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
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">Senior One Admissions Control Dashboard</h1>

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

            <!-- Flash Alert -->
            <?php if($msg): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 p-4 rounded-lg flex items-center gap-3 shadow-sm animate-pulse">
                <i class="fa-solid fa-circle-check text-xl text-emerald-500"></i>
                <span class="text-sm font-semibold">Application <?= htmlspecialchars($msg) ?> successfully.</span>
            </div>
            <?php endif; ?>

            <!-- Statistics Grid (Cruip style cards) -->
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
                        <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Pending Reviews</span>
                        <span class="block text-3xl font-extrabold text-slate-800 mt-1"><?= $pendingCount ?></span>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                </div>

                <!-- Admitted -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Admitted Offers</span>
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

            <!-- Main Table Card -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider">Student Application List</h3>
                    <a href="admission_list.php" class="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition duration-150 shadow-sm flex items-center gap-1.5">
                        <i class="fa-solid fa-print"></i> View Print List
                    </a>
                </div>

                <!-- Table Wrapper -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200 text-slate-500 font-bold text-xs uppercase tracking-wider">
                                <th class="px-6 py-3.5 text-center">#</th>
                                <th class="px-6 py-3.5 text-center">Photo</th>
                                <th class="px-6 py-3.5">Student Name</th>
                                <th class="px-6 py-3.5 text-center">PLE Index</th>
                                <th class="px-6 py-3.5 text-center">Aggregates</th>
                                <th class="px-6 py-3.5">Academic Info</th>
                                <th class="px-6 py-3.5">Parent Info</th>
                                <th class="px-6 py-3.5 text-center">Results</th>
                                <th class="px-6 py-3.5 text-center">Status</th>
                                <th class="px-6 py-3.5 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php foreach ($applications as $app): 
                                $status = strtolower($app['application_status'] ?? 'pending');
                            ?>
                            <tr class="hover:bg-slate-50 transition duration-150">
                                
                                <!-- ID -->
                                <td class="px-6 py-4 text-center font-bold text-slate-400"><?= $app['id'] ?></td>

                                <!-- Photo -->
                                <td class="px-6 py-4 text-center">
                                    <?php
                                    $passport_file = $app['passport_photo'];
                                    $passport_path = '../' . $passport_file;
                                    if(!empty($passport_file) && file_exists($passport_path)): ?>
                                        <img src="<?= $passport_path ?>" class="w-10 h-10 object-cover rounded-full border border-slate-200 mx-auto">
                                    <?php else: ?>
                                        <span class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 text-lg mx-auto"><i class="fa-solid fa-user-ninja"></i></span>
                                    <?php endif; ?>
                                </td>

                                <!-- Name -->
                                <td class="px-6 py-4">
                                    <span class="block font-bold text-slate-800 leading-tight"><?= htmlspecialchars($app['firstname'].' '.$app['surname']) ?></span>
                                    <span class="block text-xs text-slate-400 uppercase mt-0.5"><?= htmlspecialchars($app['sex'].' · Age: '.$app['age']) ?></span>
                                </td>

                                <!-- Index -->
                                <td class="px-6 py-4 text-center font-mono font-semibold text-slate-600"><?= htmlspecialchars($app['index_number']) ?></td>

                                <!-- Aggregates -->
                                <td class="px-6 py-4 text-center font-bold text-brand-700"><?= htmlspecialchars($app['aggregates']) ?></td>

                                <!-- Academics -->
                                <td class="px-6 py-4">
                                    <span class="block text-xs font-semibold text-slate-700"><?= htmlspecialchars($app['former_school']) ?></span>
                                    <span class="block text-xs text-slate-400 font-semibold mt-0.5">Division: <?= htmlspecialchars($app['division']) ?></span>
                                </td>

                                <!-- Parent -->
                                <td class="px-6 py-4">
                                    <span class="block text-xs font-bold text-slate-700 leading-tight"><?= htmlspecialchars($app['parent_name']) ?></span>
                                    <span class="block text-xs text-slate-500 mt-0.5"><i class="fa-solid fa-phone text-[10px] text-slate-400"></i> <?= htmlspecialchars($app['parent_phone']) ?></span>
                                </td>

                                <!-- Results Attachment -->
                                <td class="px-6 py-4 text-center">
                                    <?php
                                    $result_file = $app['result_slip'];
                                    $result_path = '../' . $result_file;
                                    if(!empty($result_file) && file_exists($result_path)): ?>
                                        <a href="<?= $result_path ?>" target="_blank" class="inline-flex items-center gap-1 px-3 py-1 bg-sky-50 text-sky-700 hover:bg-sky-100 rounded-lg text-xs font-bold transition duration-150">
                                            <i class="fa-solid fa-file-pdf"></i> View
                                        </a>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400 font-bold">N/A</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status -->
                                <td class="px-6 py-4 text-center">
                                    <?php if($status=='admitted'): ?>
                                        <span class="status-badge status-admitted">Admitted</span>
                                    <?php elseif($status=='rejected'): ?>
                                        <span class="status-badge status-rejected">Rejected</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">Pending</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
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
                                                Print
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
                                                Print
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
                                <td colspan="9" class="px-6 py-12 text-center text-slate-400 font-semibold">
                                    <i class="fa-solid fa-folder-open text-3xl block mb-2 text-slate-300"></i>
                                    No applications submitted yet.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
    // Close profile dropdown when clicking outside
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
