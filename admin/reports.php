<?php
session_start();
require 'auth.php';
require '../config/db.php';

// Fetch dynamic configurations
$school_name    = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_logo    = getSetting($pdo, 'school_logo', 'https://www.ncedges.com/wp-content/uploads/2026/02/mutosp-logo.png');
$school_address = getSetting($pdo, 'school_address', 'P.O. Box 12, Kisoro – Uganda');
$school_phone   = getSetting($pdo, 'school_phone', '+256 790 001 631');

$logo_source = $school_logo;
if (strpos($logo_source, 'http') === false) {
    $logo_source = '../' . $logo_source;
}

$filterClass = $_GET['class'] ?? 'ALL';
$filterStatus = $_GET['status'] ?? 'all';
$filterSex = $_GET['sex'] ?? 'all';

$query = "SELECT * FROM applications";
$where = [];
$params = [];

if ($filterClass !== 'ALL') {
    if ($filterClass === 'OTHER') {
        $where[] = "class_of_entry IN ('S2', 'S3', 'S4', 'S6')";
    } else {
        $where[] = "class_of_entry = ?";
        $params[] = $filterClass;
    }
}

if ($filterStatus !== 'all') {
    $where[] = "application_status = ?";
    $params[] = $filterStatus;
}

if ($filterSex !== 'all') {
    $where[] = "sex = ?";
    $params[] = $filterSex;
}

if (count($where) > 0) {
    $query .= " WHERE " . implode(" AND ", $where);
}
$query .= " ORDER BY surname ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$count = count($applications);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Custom Admission Reports | Admissions Central Portal</title>
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
<style>
@media print {
    .no-print {
        display: none !important;
    }
    body {
        background-color: white !important;
        color: black !important;
        font-size: 11px;
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
        border: 1px solid #cbd5e1 !important;
        padding: 6px 8px !important;
    }
}
</style>
</head>
<body class="bg-slate-100 font-sans text-slate-800 min-h-screen flex">

    <!-- Sidebar (Hidden on print) -->
    <aside class="w-64 bg-slate-900 text-slate-100 flex flex-col shrink-0 min-h-screen no-print">
        <div class="px-6 py-5 border-b border-slate-800 flex items-center gap-3">
            <img src="<?= $logo_source ?>" alt="Logo" class="w-10 h-10 bg-white rounded-full p-1 border-2 border-gold-500 object-contain">
            <div>
                <span class="block text-sm font-bold text-white tracking-tight leading-tight uppercase"><?= htmlspecialchars(substr($school_name, 0, 15)) ?>...</span>
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
            <a href="settings.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-gears text-lg text-gold-500"></i> System Settings
            </a>
            <?php endif; ?>
        </nav>

        <div class="px-6 py-4 border-t border-slate-800 text-xs text-slate-500 text-center">
            Admissions Office · 2026
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <header class="bg-white border-b border-slate-200 px-8 py-4 flex justify-between items-center shadow-sm relative z-10 no-print">
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">Custom Admissions Reports</h1>
            <span class="text-sm font-bold text-slate-600">Admin: <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
        </header>

        <div class="flex-1 px-8 py-6 space-y-6 print-container">

            <!-- Official Report Header (Only on print) -->
            <div class="hidden print:block text-center border-b-4 border-slate-800 pb-4 mb-6">
                <div class="flex justify-center items-center gap-4">
                    <img src="<?= $logo_source ?>" alt="Logo" class="w-16 h-16 object-contain">
                    <div class="text-left">
                        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight"><?= strtoupper($school_name) ?></h1>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-widest"><?= htmlspecialchars($school_address) ?> | Tel: <?= htmlspecialchars($school_phone) ?></p>
                        <p class="text-sm font-extrabold text-brand-700 mt-1 uppercase">Custom Admission Report Summary</p>
                    </div>
                </div>
                <div class="mt-4 flex justify-between text-[10px] font-bold text-slate-600 border-t border-slate-200 pt-2">
                    <span>CLASS FILTER: <?= $filterClass ?></span>
                    <span>STATUS FILTER: <?= strtoupper($filterStatus) ?></span>
                    <span>GENDER: <?= strtoupper($filterSex) ?></span>
                    <span>TIMESTAMP: <?= date('d-m-Y H:i:s') ?></span>
                </div>
            </div>

            <!-- Filters Area (Hidden on print) -->
            <form method="GET" class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm grid grid-cols-1 md:grid-cols-4 gap-4 items-end no-print">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Class of Entry</label>
                    <select name="class" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 bg-white">
                        <option value="ALL" <?= $filterClass === 'ALL' ? 'selected' : '' ?>>All Classes (S1-S6)</option>
                        <option value="S1" <?= $filterClass === 'S1' ? 'selected' : '' ?>>Senior One (S1)</option>
                        <option value="S2" <?= $filterClass === 'S2' ? 'selected' : '' ?>>Senior Two (S2)</option>
                        <option value="S3" <?= $filterClass === 'S3' ? 'selected' : '' ?>>Senior Three (S3)</option>
                        <option value="S4" <?= $filterClass === 'S4' ? 'selected' : '' ?>>Senior Four (S4)</option>
                        <option value="S5" <?= $filterClass === 'S5' ? 'selected' : '' ?>>Senior Five (S5)</option>
                        <option value="S6" <?= $filterClass === 'S6' ? 'selected' : '' ?>>Senior Six (S6)</option>
                        <option value="OTHER" <?= $filterClass === 'OTHER' ? 'selected' : '' ?>>Other / Transfers</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Application Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 bg-white">
                        <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="admitted" <?= $filterStatus === 'admitted' ? 'selected' : '' ?>>Admitted</option>
                        <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Gender</label>
                    <select name="sex" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 bg-white">
                        <option value="all" <?= $filterSex === 'all' ? 'selected' : '' ?>>All Genders</option>
                        <option value="Male" <?= $filterSex === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $filterSex === 'Female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="flex-1 px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white rounded-lg text-sm font-bold shadow-sm transition duration-150">
                        Filter
                    </button>
                    <button type="button" onclick="window.print()" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold shadow-sm transition duration-150">
                        Print Register
                    </button>
                </div>
            </form>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col print:border-none print:shadow-none">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center no-print">
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider">Report Records</h3>
                    <span class="text-xs font-bold text-brand-700 bg-brand-50 border border-brand-200 px-2.5 py-1 rounded">Records Count: <?= $count ?></span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200 text-slate-500 font-bold text-xs uppercase tracking-wider print:bg-white print:border-b-2 print:border-black">
                                <th class="px-6 py-3.5 text-center">#</th>
                                <th class="px-6 py-3.5">Student Name</th>
                                <th class="px-6 py-3.5 text-center">Gender</th>
                                <th class="px-6 py-3.5 text-center">Class</th>
                                <th class="px-6 py-3.5 text-center">Index Number</th>
                                <th class="px-6 py-3.5 text-center">Aggs</th>
                                <th class="px-6 py-3.5">Academics Details</th>
                                <th class="px-6 py-3.5">Parent Details</th>
                                <th class="px-6 py-3.5 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm print:divide-y print:divide-slate-300">
                            <?php 
                            $idx = 1;
                            foreach ($applications as $app): 
                                $status = strtolower($app['application_status'] ?? 'pending');
                            ?>
                            <tr class="hover:bg-slate-50 transition duration-150">
                                <td class="px-6 py-4 text-center font-bold text-slate-400 print:text-black"><?= $idx ?></td>
                                <td class="px-6 py-4 font-bold text-slate-800 print:text-black leading-tight"><?= htmlspecialchars($app['firstname'].' '.$app['surname'].' '.$app['othernames']) ?></td>
                                <td class="px-6 py-4 text-center text-slate-600 print:text-black"><?= htmlspecialchars($app['sex']) ?></td>
                                <td class="px-6 py-4 text-center font-bold text-slate-600 print:text-black"><?= htmlspecialchars($app['class_of_entry']) ?></td>
                                <td class="px-6 py-4 text-center font-mono text-slate-600 print:text-black"><?= htmlspecialchars($app['index_number'] ?? 'N/A') ?></td>
                                <td class="px-6 py-4 text-center font-bold text-brand-700 print:text-black"><?= htmlspecialchars($app['aggregates'] ?? 'N/A') ?></td>
                                <td class="px-6 py-4 text-slate-600 print:text-black">
                                    <span class="block text-xs font-bold"><?= htmlspecialchars($app['former_school']) ?></span>
                                    <?php if($app['class_of_entry'] === 'S1'): ?>
                                        <span class="block text-[10px] text-slate-400">PLE Division: <?= htmlspecialchars($app['division']) ?></span>
                                    <?php elseif($app['class_of_entry'] === 'S5'): ?>
                                        <span class="block text-[10px] text-gold-600 font-bold"><?= htmlspecialchars($app['combination']) ?></span>
                                    <?php else: ?>
                                        <span class="block text-[10px] text-brand-700 font-semibold">Last Class: <?= htmlspecialchars($app['division']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-slate-600 print:text-black">
                                    <span class="block font-bold text-xs"><?= htmlspecialchars($app['parent_name']) ?></span>
                                    <span class="block text-xs"><?= htmlspecialchars($app['parent_phone']) ?></span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if($status=='admitted'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">Admitted</span>
                                    <?php elseif($status=='rejected'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200">Rejected</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php 
                            $idx++;
                            endforeach; 
                            ?>

                            <?php if (count($applications) === 0): ?>
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center text-slate-400 font-semibold print:text-black">
                                    No records found matching current criteria.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Print Signatures (Only on print) -->
            <div class="hidden print:flex justify-between items-end mt-16 text-xs pt-12 border-t border-dashed border-slate-300">
                <div>
                    <span class="block font-bold text-slate-700">Official Admissions Register Approved By:</span>
                    <span class="block mt-12 border-t border-black w-48 text-center pt-1 font-semibold">Head Teacher Signature</span>
                </div>
                <div class="text-right">
                    <span class="block font-bold text-slate-700">Compiled By:</span>
                    <span class="block mt-12 border-t border-black w-48 text-center pt-1 font-semibold">School Secretary Signature</span>
                </div>
            </div>

        </div>
    </main>

</body>
</html>
