<?php
session_start();
require 'auth.php';
require '../config/db.php';

// Fetch dynamic configurations
$school_name       = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_address    = getSetting($pdo, 'school_address', 'P.O. Box 12, Kisoro - Uganda');
$school_phone      = getSetting($pdo, 'school_phone', '+256 790 001 631');
$academic_year     = getAcademicYear($pdo, 'S1');
$logo_source       = getSchoolLogoSrc($pdo, '../../');

$filterStatus = $_GET['status'] ?? 'all';
$query = "SELECT * FROM s1_applications";
$params = [];

if ($filterStatus !== 'all') {
    $query .= " WHERE application_status = ?";
    $params[] = $filterStatus;
}
$query .= " ORDER BY surname ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total count
$count = count($applications);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Custom Admission Reports (S1) | <?= htmlspecialchars($school_short_name) ?></title>
<!-- Tailwind CDN -->
<script src="https://cdn.tailwindcss.com"></script>
<!-- Font Awesome & Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<?= renderThemeConfig($pdo) ?>
<style>
@media print {
    .no-print {
        display: none !important;
    }
    body {
        background-color: white !important;
        color: black !important;
        font-size: 12px;
    }
    .print-container {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: none !important;
    }
    table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    th, td {
        border: 1px solid #000 !important;
        padding: 6px 8px !important;
        text-align: left !important;
    }
}
</style>
</head>
<body class="bg-slate-100 font-sans text-slate-800 min-height-screen flex">

    <!-- Sidebar (Cruip dark style) - Hidden on print -->
    <aside class="w-64 bg-slate-900 text-slate-100 flex flex-col shrink-0 min-h-screen no-print">
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
            <a href="reports.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition duration-150">
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
            <a href="../../admin/settings.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-gear text-lg text-gold-500"></i> System Settings
            </a>
            <?php endif; ?>
            <div class="pt-6 border-t border-slate-800 my-4"></div>
            <a href="../../form-five/admin/dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-right-left text-lg text-gold-500"></i> Go to S5 Portal
            </a>
        </nav>
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
        
        <!-- Header - Hidden on print -->
        <header class="bg-white border-b border-slate-200 px-8 py-4 flex justify-between items-center shadow-sm relative z-10 no-print">
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">Admissions Custom Report Builder</h1>

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
        <div class="flex-1 px-8 py-6 space-y-6 print-container">

            <!-- Print Banner (Visible ONLY on print) -->
            <div class="hidden print:block text-center border-b-4 border-slate-800 pb-4 mb-6">
                <div class="flex justify-center items-center gap-4">
                    <img src="<?= htmlspecialchars($logo_source) ?>" alt="Logo" class="w-16 h-16 object-contain">
                    <div class="text-left">
                        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight"><?= strtoupper($school_name) ?></h1>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-widest"><?= htmlspecialchars($school_address) ?> | Tel: <?= htmlspecialchars($school_phone) ?></p>
                        <p class="text-sm font-extrabold text-brand-700 mt-1 uppercase">Official Online Admissions Report - Senior One • Academic Year <?= htmlspecialchars($academic_year) ?></p>
                    </div>
                </div>
                
                <div class="mt-4 flex justify-between text-xs font-bold text-slate-600 border-t border-slate-200 pt-2">
                    <span>INTAKE YEAR: <?= htmlspecialchars($academic_year) ?></span>
                    <span>REPORT FILTER: <?= strtoupper($filterStatus) ?> STATUS</span>
                    <span>TOTAL STUDENTS LISTED: <?= $count ?></span>
                    <span>GENERATED ON: <?= date('d-m-Y H:i:s') ?></span>
                </div>
            </div>

            <!-- Filter Controls Panel - Hidden on print -->
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm no-print">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Filter by Admission Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 bg-white">
                            <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Applications (Submitted)</option>
                            <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending Reviews</option>
                            <option value="admitted" <?= $filterStatus === 'admitted' ? 'selected' : '' ?>>Admitted Offers</option>
                            <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected Applications</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="w-full py-2 bg-brand-700 hover:bg-brand-800 text-white rounded-lg text-sm font-bold shadow-sm transition duration-150 flex justify-center items-center gap-1.5">
                            <i class="fa-solid fa-filter"></i> Apply Filters
                        </button>
                    </div>
                    <div>
                        <button type="button" onclick="window.print()" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold shadow-sm transition duration-150 flex justify-center items-center gap-1.5">
                            <i class="fa-solid fa-print"></i> Print Customized Report
                        </button>
                    </div>
                </form>
            </div>

            <!-- Report Output Card -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col print:border-none print:shadow-none">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center no-print">
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider">Filtered Student Output List</h3>
                    <span class="text-xs font-bold text-brand-700 bg-brand-50 border border-brand-200 px-2.5 py-1 rounded">Count: <?= $count ?> students</span>
                </div>

                <!-- Table Wrapper -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse print:text-xs">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200 text-slate-500 font-bold text-xs uppercase tracking-wider print:bg-white print:border-b-2 print:border-black">
                                <th class="px-6 py-3.5 text-center">#</th>
                                <th class="px-6 py-3.5">Student Full Name</th>
                                <th class="px-6 py-3.5 text-center">Gender</th>
                                <th class="px-6 py-3.5 text-center">PLE Index</th>
                                <th class="px-6 py-3.5 text-center">Aggregates</th>
                                <th class="px-6 py-3.5 text-center">Division</th>
                                <th class="px-6 py-3.5">Parent Contact Details</th>
                                <th class="px-6 py-3.5 text-center">Status</th>
                                <th class="px-6 py-3.5 text-center">Admitted By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm print:divide-y print:divide-slate-300">
                            <?php foreach ($applications as $idx => $app): ?>
                            <tr class="hover:bg-slate-50 transition duration-150">
                                <td class="px-6 py-3 text-center font-bold text-slate-400 print:text-black"><?= $idx + 1 ?></td>
                                <td class="px-6 py-3 font-bold text-slate-800 print:text-black leading-tight"><?= htmlspecialchars($app['firstname'].' '.$app['surname']) ?></td>
                                <td class="px-6 py-3 text-center capitalize text-slate-600 print:text-black"><?= htmlspecialchars($app['sex']) ?></td>
                                <td class="px-6 py-3 text-center font-mono font-semibold text-slate-600 print:text-black"><?= htmlspecialchars($app['index_number']) ?></td>
                                <td class="px-6 py-3 text-center font-bold text-brand-700 print:text-black"><?= htmlspecialchars($app['aggregates']) ?></td>
                                <td class="px-6 py-3 text-center text-slate-600 print:text-black"><?= htmlspecialchars($app['division']) ?></td>
                                <td class="px-6 py-3 text-slate-600 print:text-black">
                                    <span class="block font-bold text-xs"><?= htmlspecialchars($app['parent_name']) ?></span>
                                    <span class="block text-xs"><?= htmlspecialchars($app['parent_phone']) ?></span>
                                </td>
                                <td class="px-6 py-3 text-center capitalize print:text-black font-semibold">
                                    <?= htmlspecialchars($app['application_status']) ?>
                                </td>
                                <td class="px-6 py-3 text-center print:text-black">
                                    <?= !empty($app['admitted_by']) ? htmlspecialchars($app['admitted_by']) : ($app['application_status'] === 'admitted' ? 'Admissions Office' : '—') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (count($applications) === 0): ?>
                            <tr>
                                <td colspan="10" class="px-6 py-12 text-center text-slate-400 font-semibold print:text-black">
                                    <i class="fa-solid fa-folder-open text-3xl block mb-2 text-slate-300 print:hidden"></i>
                                    No records match selected filter criteria.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Print Verification Sign Block (Visible ONLY on print) -->
            <div class="hidden print:flex justify-between items-end mt-16 text-xs pt-12 border-t border-dashed border-slate-300">
                <div>
                    <span class="block font-bold text-slate-700">Admissions Verification Office</span>
                    <span class="block mt-12 border-t border-black w-48 text-center pt-1 font-semibold">Authorized Signature</span>
                </div>
                
                <div class="text-right">
                    <span class="block font-bold text-slate-700">Office of the School Secretary</span>
                    <span class="block mt-12 border-t border-black w-48 text-center pt-1 font-semibold">Secretary Signature</span>
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
