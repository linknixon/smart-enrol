<?php
session_start();
require 'auth.php';
require '../config/db.php';

// Secretary or Admin (Editor) cannot access system settings - Super Admin only
if ($_SESSION['admin_role'] !== 'super_admin') {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

// Handle Settings Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $settingsKeys = [
            'school_name', 'school_address', 'school_phone', 'school_email', 'school_website',
            'admin_email',
            'fees_s1_tuition', 'fees_s1_admission', 'fees_s1_functional',
            'fees_s5_tuition', 'fees_s5_admission', 'fees_s5_functional',
            'fees_trans_tuition', 'fees_trans_admission', 'fees_trans_functional'
        ];

        $updateStmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");

        foreach ($settingsKeys as $key) {
            if (isset($_POST[$key])) {
                $val = trim($_POST[$key]);
                $updateStmt->execute([$val, $key]);
            }
        }

        // Handle Custom Logo Upload
        if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] === UPLOAD_ERR_OK) {
            @mkdir('../images', 0777, true);
            $logoPath = 'images/logo_custom.png';
            $dest = '../' . $logoPath;

            if (move_uploaded_file($_FILES['logo_upload']['tmp_name'], $dest)) {
                $updateStmt->execute([$logoPath, 'school_logo']);
            } else {
                throw new Exception("Failed to save uploaded logo file to destination.");
            }
        }

        $pdo->commit();
        logActivity($pdo, $_SESSION['admin_username'], "Update Settings", "Updated system configurations, contacts, and tuition rates");
        $success = "System configurations updated successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to update configurations: " . $e->getMessage();
    }
}

// Fetch settings values dynamically
$school_name       = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_logo       = getSetting($pdo, 'school_logo', 'https://www.ncedges.com/wp-content/uploads/2026/02/mutosp-logo.png');
$school_address    = getSetting($pdo, 'school_address', 'P.O. Box 12, Kisoro – Uganda');
$school_phone      = getSetting($pdo, 'school_phone', '+256 790 001 631');
$school_email      = getSetting($pdo, 'school_email', 'info@ncedges.com');
$school_website    = getSetting($pdo, 'school_website', 'www.mutolere.sc.ug');
$admin_email       = getSetting($pdo, 'admin_email', 'info@ncedges.com');

$fees_s1_tuition   = getSetting($pdo, 'fees_s1_tuition', '550000');
$fees_s1_admission = getSetting($pdo, 'fees_s1_admission', '100000');
$fees_s1_functional= getSetting($pdo, 'fees_s1_functional', '150000');

$fees_s5_tuition   = getSetting($pdo, 'fees_s5_tuition', '600000');
$fees_s5_admission = getSetting($pdo, 'fees_s5_admission', '120000');
$fees_s5_functional= getSetting($pdo, 'fees_s5_functional', '180000');

$fees_trans_tuition   = getSetting($pdo, 'fees_trans_tuition', '570000');
$fees_trans_admission = getSetting($pdo, 'fees_trans_admission', '110000');
$fees_trans_functional= getSetting($pdo, 'fees_trans_functional', '170000');

// Generate local source path for preview
$logo_source = $school_logo;
if (strpos($logo_source, 'http') === false) {
    $logo_source = '../' . $logo_source;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Settings | Admissions Central Portal</title>
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
            <a href="reports.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-file-invoice text-lg text-gold-500"></i> Custom Reports
            </a>
            <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-users text-lg"></i> User Management
            </a>
            <a href="logs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-list-check text-lg"></i> Activity Logs
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition duration-150">
                <i class="fa-solid fa-gears text-lg text-gold-500"></i> System Settings
            </a>
        </nav>

        <div class="px-6 py-4 border-t border-slate-800 text-xs text-slate-500 text-center">
            Admissions Office · 2026
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <header class="bg-white border-b border-slate-200 px-8 py-4 flex justify-between items-center shadow-sm relative z-10">
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">System Settings & Controls</h1>
            <span class="text-sm font-bold text-slate-600">Admin: <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
        </header>

        <!-- Page Content -->
        <div class="flex-1 px-8 py-6 space-y-6">

            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-800 p-4 rounded-lg flex items-center gap-3 shadow-sm animate-shake">
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

            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                
                <!-- School Profile section -->
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider border-b border-slate-100 pb-2">
                        <i class="fa-solid fa-school text-brand-700 me-1.5"></i> School Profile Details
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Official School Name</label>
                            <input type="text" name="school_name" value="<?= htmlspecialchars($school_name) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 font-semibold" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">School Website Address</label>
                            <input type="text" name="school_website" value="<?= htmlspecialchars($school_website) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 font-mono" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">School Address / P.O. Box</label>
                            <input type="text" name="school_address" value="<?= htmlspecialchars($school_address) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Official Telephone Contacts</label>
                            <input type="text" name="school_phone" value="<?= htmlspecialchars($school_phone) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 font-mono" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Official School Email Address</label>
                            <input type="email" name="school_email" value="<?= htmlspecialchars($school_email) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700" required>
                        </div>
                    </div>

                    <!-- Logo Section -->
                    <div class="pt-4 border-t border-slate-100 flex flex-col md:flex-row items-center gap-6">
                        <div class="shrink-0 text-center">
                            <span class="block text-xs font-bold text-slate-400 uppercase mb-2">Current System Logo</span>
                            <img src="<?= $logo_source ?>?t=<?= time() ?>" alt="School Logo" class="w-24 h-24 object-contain border border-slate-200 rounded-lg p-2 bg-slate-50">
                        </div>
                        <div class="flex-1 space-y-1">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Upload New Logo (Replaces Current Logo System-wide)</label>
                            <input type="file" name="logo_upload" accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                            <p class="text-[10px] text-slate-400">Recommended: Transparent PNG or crisp JPEG file on white backgrounds.</p>
                        </div>
                    </div>
                </div>

                <!-- Notification control -->
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider border-b border-slate-100 pb-2">
                        <i class="fa-solid fa-envelope-open-text text-brand-700 me-1.5"></i> Admissions Alert Routing
                    </h3>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Recipient Email Addresses (Comma-separated)</label>
                        <input type="text" name="admin_email" value="<?= htmlspecialchars($admin_email) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 font-mono" required>
                        <p class="text-[10px] text-slate-400 mt-1">Alerts about new student admissions applications will be sent to these addresses automatically.</p>
                    </div>
                </div>

                <!-- Fees Structure Matrix Section -->
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-6">
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider border-b border-slate-100 pb-2">
                        <i class="fa-solid fa-coins text-brand-700 me-1.5"></i> Tuition & Fees matrix Configuration
                    </h3>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                        <!-- S1 Fees Card -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                            <h4 class="font-bold text-brand-700 text-xs uppercase tracking-wider border-b border-slate-200 pb-1.5"><i class="fa-solid fa-chevron-right"></i> Senior One (S1)</h4>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Tuition Fees (UGX)</label>
                                <input type="number" name="fees_s1_tuition" value="<?= htmlspecialchars($fees_s1_tuition) ?>" class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 font-semibold" required>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Admission Fees (UGX)</label>
                                <input type="number" name="fees_s1_admission" value="<?= htmlspecialchars($fees_s1_admission) ?>" class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 font-semibold" required>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Functional & Other Fees (UGX)</label>
                                <input type="number" name="fees_s1_functional" value="<?= htmlspecialchars($fees_s1_functional) ?>" class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 font-semibold" required>
                            </div>
                        </div>

                        <!-- S5 Fees Card -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                            <h4 class="font-bold text-brand-700 text-xs uppercase tracking-wider border-b border-slate-200 pb-1.5"><i class="fa-solid fa-chevron-right"></i> Senior Five (S5)</h4>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Tuition Fees (UGX)</label>
                                <input type="number" name="fees_s5_tuition" value="<?= htmlspecialchars($fees_s5_tuition) ?>" class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 font-semibold" required>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Admission Fees (UGX)</label>
                                <input type="number" name="fees_s5_admission" value="<?= htmlspecialchars($fees_s5_admission) ?>" class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 font-semibold" required>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Functional & Other Fees (UGX)</label>
                                <input type="number" name="fees_s5_functional" value="<?= htmlspecialchars($fees_s5_functional) ?>" class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 font-semibold" required>
                            </div>
                        </div>

                        <!-- Transfers Fees Card -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                            <h4 class="font-bold text-brand-700 text-xs uppercase tracking-wider border-b border-slate-200 pb-1.5"><i class="fa-solid fa-chevron-right"></i> Transfers (S2, S3, S4, S6)</h4>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Tuition Fees (UGX)</label>
                                <input type="number" name="fees_trans_tuition" value="<?= htmlspecialchars($fees_trans_tuition) ?>" class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 font-semibold" required>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Admission Fees (UGX)</label>
                                <input type="number" name="fees_trans_admission" value="<?= htmlspecialchars($fees_trans_admission) ?>" class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 font-semibold" required>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Functional & Other Fees (UGX)</label>
                                <input type="number" name="fees_trans_functional" value="<?= htmlspecialchars($fees_trans_functional) ?>" class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-700 font-semibold" required>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="flex justify-end gap-3 py-4">
                    <a href="dashboard.php" class="px-6 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-xs font-bold uppercase tracking-wider text-center transition duration-150">Cancel Changes</a>
                    <button type="submit" class="px-8 py-2.5 bg-brand-700 hover:bg-brand-800 text-white rounded-lg text-xs font-bold uppercase tracking-wider shadow-md transition duration-150 flex items-center gap-1.5">
                        <i class="fa-solid fa-floppy-disk"></i> Save System Config
                    </button>
                </div>
            </form>

        </div>
    </main>

</body>
</html>
