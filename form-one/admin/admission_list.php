<?php
session_start();
require 'auth.php';
require '../config/db.php';

// Fetch dynamic configurations
$school_name        = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name  = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_address     = getSetting($pdo, 'school_address', 'P.O. Box 12, Kisoro - Uganda');
$school_phone       = getSetting($pdo, 'school_phone', '+256 790 001 631');
$academic_year      = getAcademicYear($pdo, 'S1');
$year               = getAcademicYearShort($pdo, 'S1');
$school_code        = getSetting($pdo, 'school_code', '011');
$head_teacher_name  = getSetting($pdo, 'head_teacher_name', 'MR. MBONYE CHARLES');
$head_teacher_title = getSetting($pdo, 'head_teacher_title', 'Head Teacher');

$logo_source = getSchoolLogoSrc($pdo, '../../');
$school_logo = $logo_source;

/* Fetch ONLY admitted students */
$stmt = $pdo->prepare("
    SELECT * FROM s1_applications
    WHERE application_status = 'admitted'
    ORDER BY surname ASC
");
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Student number settings */
$class = '01';

/* ================= CSV EXPORT ================= */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=Senior_One_Admission_Register.csv');
    
    $output = fopen("php://output", "w");
    fputcsv($output, ['Serial No', 'Student Number', 'Full Name', 'Gender', 'PLE Index No', 'Aggregates', 'Division', 'Parent Name', 'Parent Contact']);

    $idx = 1;
    foreach($students as $s) {
        $student_number = $year.$class.$school_code.str_pad($idx, 2, '0', STR_PAD_LEFT);
        fputcsv($output, [
            $idx,
            $student_number,
            $s['surname'].' '.$s['firstname'].' '.$s['othernames'],
            ucfirst($s['sex']),
            $s['index_number'],
            $s['aggregates'],
            $s['division'],
            $s['parent_name'],
            $s['parent_phone']
        ]);
        $idx++;
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Senior One Admitted Register - <?= htmlspecialchars($school_short_name) ?></title>
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
            <div class="min-w-0">
                <span class="block text-sm font-bold text-white tracking-tight leading-tight truncate uppercase" title="<?= htmlspecialchars($school_name) ?>"><?= htmlspecialchars($school_short_name) ?></span>
                <span class="block text-xs font-semibold text-gold-500 uppercase tracking-widest text-[10px]">S1 Admin Portal</span>
            </div>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1.5">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard (S1)
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition duration-150">
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
            <a href="../../admin/settings.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-gears text-lg text-gold-500"></i> System Settings
            </a>
            <?php endif; ?>
            <div class="pt-6 border-t border-slate-800 my-4"></div>
            <a href="../../form-five/admin/dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-right-left text-lg text-gold-500"></i> Go to S5 Portal
            </a>
        </nav>

        <div class="px-6 py-4 border-t border-slate-800 text-xs text-slate-500 text-center">
            Admissions Office · <?= htmlspecialchars($academic_year) ?>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        
        <!-- Header (Hidden on print) -->
        <header class="bg-white border-b border-slate-200 px-8 py-4 flex justify-between items-center shadow-sm relative z-10 no-print">
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">Senior One Official Admission Register</h1>

            <!-- Profile -->
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="text-xs font-bold text-brand-700 hover:underline"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
                <span class="text-slate-300">|</span>
                <span class="text-sm font-bold text-slate-600">Admin: <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
            </div>
        </header>

        <!-- Page Content -->
        <div class="flex-1 px-8 py-6 space-y-6 print-container">

            <!-- Print Header Banner (Only on print) -->
            <div class="hidden print:block text-center border-b-4 border-slate-800 pb-4 mb-6">
                <div class="flex justify-center items-center gap-4">
                    <img src="<?= $logo_source ?>" alt="Logo" class="w-16 h-16 object-contain">
                    <div class="text-left">
                        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight uppercase"><?= htmlspecialchars($school_name) ?></h1>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-widest"><?= htmlspecialchars($school_address) ?> | Tel: <?= htmlspecialchars($school_phone) ?></p>
                        <p class="text-sm font-extrabold text-brand-700 mt-1 uppercase">Official Senior One Admission Register - <?= htmlspecialchars($academic_year) ?> Entry</p>
                    </div>
                </div>
                <div class="mt-4 flex justify-between text-xs font-bold text-slate-600 border-t border-slate-200 pt-2">
                    <span>STATUS: ADMITTED REGISTER</span>
                    <span>TOTAL STUDENTS: <?= count($students) ?></span>
                    <span>TIMESTAMP: <?= date('d-m-Y H:i:s') ?></span>
                </div>
            </div>

            <!-- Page Controls Card (Hidden on print) -->
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row justify-between gap-4 items-center no-print">
                <div class="w-full md:w-1/3">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Search Admitted Students</label>
                    <input type="text" id="searchInput" onkeyup="filterTable()" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700" placeholder="Search by name, index, parent...">
                </div>

                <div class="flex gap-2 w-full md:w-auto">
                    <button onclick="window.print()" class="flex-1 md:flex-none px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white rounded-lg text-sm font-bold shadow-sm transition duration-150 flex justify-center items-center gap-1.5">
                        <i class="fa-solid fa-print"></i> Print Register
                    </button>
                    <a href="?export=csv" class="flex-1 md:flex-none px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold shadow-sm transition duration-150 flex justify-center items-center gap-1.5">
                        <i class="fa-solid fa-file-csv"></i> Export CSV
                    </a>
                </div>
            </div>

            <!-- Register Register Card -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col print:border-none print:shadow-none">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center no-print">
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider">Admitted List Register</h3>
                    <span class="text-xs font-bold text-brand-700 bg-brand-50 border border-brand-200 px-2.5 py-1 rounded">Count: <?= count($students) ?></span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse" id="admissionTable">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200 text-slate-500 font-bold text-xs uppercase tracking-wider print:bg-white print:border-b-2 print:border-black">
                                <th class="px-6 py-3.5 text-center">#</th>
                                <th class="px-6 py-3.5 text-center">Student Number</th>
                                <th class="px-6 py-3.5">Student Full Name</th>
                                <th class="px-6 py-3.5 text-center">Gender</th>
                                <th class="px-6 py-3.5 text-center">PLE Index</th>
                                <th class="px-6 py-3.5 text-center">Aggs</th>
                                <th class="px-6 py-3.5 text-center">Div</th>
                                <th class="px-6 py-3.5">Parent Details</th>
                                <th class="px-6 py-3.5 text-center no-print">Letter</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm print:divide-y print:divide-slate-300">
                            <?php 
                            $idx = 1;
                            foreach ($students as $s): 
                                $student_number = $year.$class.$school_code.str_pad($idx, 2, '0', STR_PAD_LEFT);
                            ?>
                            <tr class="hover:bg-slate-50 transition duration-150">
                                <td class="px-6 py-4 text-center font-bold text-slate-400 print:text-black"><?= $idx ?></td>
                                <td class="px-6 py-4 text-center font-mono font-bold text-slate-700 print:text-black"><?= $student_number ?></td>
                                <td class="px-6 py-4 font-bold text-slate-800 print:text-black leading-tight"><?= htmlspecialchars($s['surname'].' '.$s['firstname'].' '.$s['othernames']) ?></td>
                                <td class="px-6 py-4 text-center text-slate-600 print:text-black"><?= ucfirst(htmlspecialchars($s['sex'])) ?></td>
                                <td class="px-6 py-4 text-center font-mono text-slate-600 print:text-black"><?= htmlspecialchars($s['index_number']) ?></td>
                                <td class="px-6 py-4 text-center font-bold text-brand-700 print:text-black"><?= htmlspecialchars($s['aggregates']) ?></td>
                                <td class="px-6 py-4 text-center font-bold text-slate-600 print:text-black"><?= htmlspecialchars($s['division']) ?></td>
                                <td class="px-6 py-4 text-slate-600 print:text-black">
                                    <span class="block font-bold text-xs"><?= htmlspecialchars($s['parent_name']) ?></span>
                                    <span class="block text-xs"><?= htmlspecialchars($s['parent_phone']) ?></span>
                                </td>
                                <td class="px-6 py-4 text-center no-print">
                                    <a href="admission_offer.php?id=<?= $s['id'] ?>" target="_blank" class="px-3 py-1.5 bg-brand-50 hover:bg-brand-100 text-brand-700 rounded border border-brand-200 text-xs font-bold inline-flex items-center gap-1 transition">
                                        <i class="fa-solid fa-file-pdf"></i> PDF Offer
                                    </a>
                                </td>
                            </tr>
                            <?php 
                                $idx++;
                            endforeach; 
                            ?>

                            <?php if (count($students) === 0): ?>
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center text-slate-400 font-semibold print:text-black">
                                    <i class="fa-solid fa-folder-open text-3xl block mb-2 text-slate-300 print:hidden"></i>
                                    No admitted students yet.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Verification Block (Only on print) -->
            <div class="hidden print:flex justify-between items-end mt-16 text-xs pt-12 border-t border-dashed border-slate-300">
                <div>
                    <span class="block font-bold text-slate-700">Official Admissions Register Approved By:</span>
                    <span class="block mt-12 border-t border-black w-48 text-center pt-1 font-semibold"><?= htmlspecialchars($head_teacher_name) ?> (<?= htmlspecialchars($head_teacher_title) ?>)</span>
                </div>
                <div class="text-right">
                    <span class="block font-bold text-slate-700">Compiled By:</span>
                    <span class="block mt-12 border-t border-black w-48 text-center pt-1 font-semibold">School Secretary Signature</span>
                </div>
            </div>

        </div>

    </main>

    <!-- Search Filtering script -->
    <script>
    function filterTable() {
        const input = document.getElementById("searchInput").value.toLowerCase();
        const rows = document.querySelectorAll("#admissionTable tbody tr");

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(input) ? "" : "none";
        });
    }
    </script>
</body>
</html>
