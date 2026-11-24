<?php
require_once __DIR__ . '/auth.php';

// Check Permissions: Super Admin or Admissions Policy Manager
$currentRole = $_SESSION['admin_role'] ?? '';
$canAllSettings = hasPermission($pdo, $currentRole, 'can_manage_settings');
$canPolicySettings = hasPermission($pdo, $currentRole, 'can_manage_admission_policies');

if (!$canAllSettings && !$canPolicySettings) {
    header("Location: dashboard.php?error=unauthorized");
    exit;
}

$error = '';
$success = '';

// Handle Settings Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        if (!$canAllSettings && $canPolicySettings) {
            // Restricted keys for Admissions Policy Manager (Policies, Fees, Letters, Signature & Documents)
            $settingsKeys = [
                'academic_year',
                'a_level_combinations',
                'ai_auto_admit_enabled', 'ai_auto_combination_enabled',
                'show_parent_id_upload', 'show_passport_photo_upload',
                'admission_s1_start_date', 'admission_s1_end_date',
                'admission_s5_start_date', 'admission_s5_end_date',
                'admission_trans_start_date', 'admission_trans_end_date',
                'admission_s1_passmark', 'admission_s5_passmark', 'admission_trans_passmark',
                'reporting_date_s1', 'reporting_date_s5', 'reporting_date_trans',
                'school_requirements', 'admission_letter_custom_note',
                'head_teacher_name', 'head_teacher_title',
                'student_number_format',
                // Fees matrices
                'fees_s1_tuition', 'fees_s1_admission', 'fees_s1_functional',
                'fees_s2_tuition', 'fees_s2_admission', 'fees_s2_functional',
                'fees_s3_tuition', 'fees_s3_admission', 'fees_s3_functional',
                'fees_s4_tuition', 'fees_s4_admission', 'fees_s4_functional',
                'fees_s5_tuition', 'fees_s5_admission', 'fees_s5_functional',
                'fees_s6_tuition', 'fees_s6_admission', 'fees_s6_functional',
                'fees_trans_tuition', 'fees_trans_admission', 'fees_trans_functional',
                'fees_structure_matrix'
            ];
        } else {
            $settingsKeys = [
                'school_name', 'school_short_name', 'school_motto',
                'school_address', 'school_phone', 'school_email', 'school_website', 'portal_base_url',
                'head_teacher_name', 'head_teacher_title', 'school_code', 'academic_year',
                'student_number_format',
                'school_gender_type',
                'a_level_combinations',
                'ai_auto_admit_enabled', 'ai_auto_combination_enabled',
                'show_parent_id_upload', 'show_passport_photo_upload',
                'cf_turnstile_enabled', 'cf_turnstile_site_key', 'cf_turnstile_secret_key',
                'staff_mfa_enabled',
                'primary_color', 'accent_color',
                'admin_email', 'application_notification_emails',
                'admission_s1_start_date', 'admission_s1_end_date',
                'admission_s5_start_date', 'admission_s5_end_date',
                'admission_trans_start_date', 'admission_trans_end_date',
                'admission_s1_passmark', 'admission_s5_passmark', 'admission_trans_passmark',
                'reporting_date_s1', 'reporting_date_s5', 'reporting_date_trans',
                'school_requirements', 'admission_letter_custom_note',
                // SMTP Settings
                'smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_auth',
                'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name',
                // Fees matrices
                'fees_s1_tuition', 'fees_s1_admission', 'fees_s1_functional',
                'fees_s2_tuition', 'fees_s2_admission', 'fees_s2_functional',
                'fees_s3_tuition', 'fees_s3_admission', 'fees_s3_functional',
                'fees_s4_tuition', 'fees_s4_admission', 'fees_s4_functional',
                'fees_s5_tuition', 'fees_s5_admission', 'fees_s5_functional',
                'fees_s6_tuition', 'fees_s6_admission', 'fees_s6_functional',
                'fees_trans_tuition', 'fees_trans_admission', 'fees_trans_functional',
                'fees_structure_matrix'
            ];
        }

        $saveStmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");

        $checkboxKeys = ['ai_auto_admit_enabled', 'ai_auto_combination_enabled', 'show_parent_id_upload', 'show_passport_photo_upload', 'smtp_enabled'];
        foreach ($checkboxKeys as $cbKey) {
            if (in_array($cbKey, $settingsKeys)) {
                $val = isset($_POST[$cbKey]) ? '1' : '0';
                $saveStmt->execute([$cbKey, $val]);
            }
        }

        foreach ($settingsKeys as $key) {
            if (in_array($key, $checkboxKeys)) {
                continue;
            }
            // Strictly enforce: Only Super Admin can change school gender admission policy
            if ($key === 'school_gender_type' && ($_SESSION['admin_role'] ?? '') !== 'super_admin') {
                continue;
            }
            if (isset($_POST[$key])) {
                $val = trim($_POST[$key]);
                if (strpos($key, 'fees_') === 0 && $key !== 'fees_structure_matrix') {
                    $val = preg_replace('/[^\d]/', '', $val);
                }
                $saveStmt->execute([$key, $val]);
            }
        }

        // Automatic synchronization of legacy fee keys from fees_structure_matrix
        if (!empty($_POST['fees_structure_matrix'])) {
            $parsedMatrix = json_decode($_POST['fees_structure_matrix'], true);
            if (is_array($parsedMatrix) && !empty($parsedMatrix['rows'])) {
                foreach ($parsedMatrix['rows'] as $r) {
                    $rawCls = strtoupper(str_replace(['.', ' ', '-'], '', $r['class'] ?? ''));
                    $keyCls = strtolower($rawCls);
                    if (in_array($keyCls, ['s1', 's2', 's3', 's4', 's5', 's6'])) {
                        $tNum = preg_replace('/[^\d]/', '', $r['tuition_boarding'] ?? $r['tuition_day'] ?? '');
                        $aNum = preg_replace('/[^\d]/', '', $r['admission_fee'] ?? '');
                        $fNum = preg_replace('/[^\d]/', '', $r['functional'] ?? '');
                        if ($tNum !== '') $saveStmt->execute(["fees_{$keyCls}_tuition", $tNum]);
                        if ($aNum !== '') $saveStmt->execute(["fees_{$keyCls}_admission", $aNum]);
                        if ($fNum !== '') $saveStmt->execute(["fees_{$keyCls}_functional", $fNum]);
                    }
                }
            }
        }

        $baseDir = realpath(__DIR__ . '/..');

        // Handle Official Enclosure Document Uploads (Rules, Circular, Medical Form)
        $docUploads = [
            'doc_school_rules_file'       => 'doc_school_rules',
            'doc_admission_circular_file' => 'doc_admission_circular',
            'doc_medical_form_file'       => 'doc_medical_form'
        ];
        @mkdir($baseDir . '/uploads/documents', 0777, true);

        foreach ($docUploads as $fileKey => $settingKey) {
            if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $fileInfo = pathinfo($_FILES[$fileKey]['name']);
                $ext = strtolower($fileInfo['extension'] ?? 'pdf');
                $docFilename = $settingKey . '_' . time() . '.' . $ext;
                $docRelPath = 'uploads/documents/' . $docFilename;
                $docDest = $baseDir . '/' . $docRelPath;
                if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $docDest)) {
                    $saveStmt->execute([$settingKey, $docRelPath]);
                }
            }
        }

        // Handle Custom Logo Upload or Direct URL (Super Admin only)
        if ($canAllSettings) {
            if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] === UPLOAD_ERR_OK) {
                @mkdir($baseDir . '/images', 0777, true);
                $fileInfo = pathinfo($_FILES['logo_upload']['name']);
                $ext = strtolower($fileInfo['extension'] ?? 'png');
                if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'])) {
                    $ext = 'png';
                }
                $newFilename = 'logo_custom_' . time() . '.' . $ext;
                $logoPath = 'images/' . $newFilename;
                $dest = $baseDir . '/' . $logoPath;

                if (move_uploaded_file($_FILES['logo_upload']['tmp_name'], $dest)) {
                    @copy($dest, $baseDir . '/images/logo_custom.png');
                    $saveStmt->execute(['school_logo', $logoPath]);
                } else {
                    throw new Exception("Failed to save uploaded logo file.");
                }
            } elseif (!empty($_POST['school_logo_url']) && (strpos($_POST['school_logo_url'], 'http://') === 0 || strpos($_POST['school_logo_url'], 'https://') === 0)) {
                $saveStmt->execute(['school_logo', trim($_POST['school_logo_url'])]);
            }
        }

        // Handle Head Teacher E-Signature Upload
        if (isset($_FILES['headteacher_signature_upload']) && $_FILES['headteacher_signature_upload']['error'] === UPLOAD_ERR_OK) {
            @mkdir($baseDir . '/images', 0777, true);
            $fileInfo = pathinfo($_FILES['headteacher_signature_upload']['name']);
            $ext = strtolower($fileInfo['extension'] ?? 'png');
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                $ext = 'png';
            }
            $sigFilename = 'signature_headteacher_' . time() . '.' . $ext;
            $sigRelPath = 'images/' . $sigFilename;
            $dest = $baseDir . '/' . $sigRelPath;

            if (move_uploaded_file($_FILES['headteacher_signature_upload']['tmp_name'], $dest)) {
                @copy($dest, $baseDir . '/images/signature_headteacher.png');
                $saveStmt->execute(['head_teacher_signature', $sigRelPath]);
            }
        }

        // Handle Hero Background Slider Images Uploads & URLs
        if ($canAllSettings) {
            $sliderImages = [];
            for ($i = 1; $i <= 4; $i++) {
                $fileKey = "slider_image_file_{$i}";
                $urlKey  = "slider_image_url_{$i}";
                $imgPath = '';

                if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                    @mkdir($baseDir . '/images/slider', 0777, true);
                    $fileInfo = pathinfo($_FILES[$fileKey]['name']);
                    $ext = strtolower($fileInfo['extension'] ?? 'jpg');
                    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                        $ext = 'jpg';
                    }
                    $slideFilename = 'hero_slide_' . $i . '_' . time() . '.' . $ext;
                    $slideRelPath = 'images/slider/' . $slideFilename;
                    $dest = $baseDir . '/' . $slideRelPath;
                    if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $dest)) {
                        $imgPath = $slideRelPath;
                    }
                }
                
                if (empty($imgPath) && !empty($_POST[$urlKey])) {
                    $imgPath = trim($_POST[$urlKey]);
                }

                if (!empty($imgPath)) {
                    $sliderImages[] = $imgPath;
                }
            }

            if (!empty($sliderImages)) {
                $saveStmt->execute(['hero_slider_images', json_encode(array_values($sliderImages))]);
            }
        }

        $pdo->commit();

        // Invalidate settings cache
        getSetting($pdo, 'school_name', '', true);
        getAllSettings($pdo, true);

        logActivity($pdo, $_SESSION['admin_username'], "Update Settings", "Updated system configurations, branding, SMTP gateway, fee schedules, and e-signatures");
        $success = "System configurations updated successfully! All changes are live system-wide.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to update configurations: " . $e->getMessage();
    }
}

// Fetch settings values dynamically
$school_name        = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name  = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_motto       = getSetting($pdo, 'school_motto', 'Education for Service');
$school_logo        = getSetting($pdo, 'school_logo', '');
$primary_color      = getPrimaryColor($pdo);
$accent_color       = getAccentColor($pdo);
$school_address     = getSetting($pdo, 'school_address', 'P.O. Box 12, Kisoro - Uganda');
$school_phone       = getSetting($pdo, 'school_phone', '+256 790 001 631');
$school_email       = getSetting($pdo, 'school_email', 'info@ncedges.com');
$school_website     = getSetting($pdo, 'school_website', 'www.mutolere.sc.ug');
$portal_base_url    = getSetting($pdo, 'portal_base_url', '');

$head_teacher_name  = getSetting($pdo, 'head_teacher_name', 'MRS. PEACE RUZAZA');
$head_teacher_title = getSetting($pdo, 'head_teacher_title', 'Head Teacher');
$school_code        = getSetting($pdo, 'school_code', '011');
$student_number_format = getSetting($pdo, 'student_number_format', '{YY}{CLASS}{CODE}{SERIAL}');
$academic_year      = getSetting($pdo, 'academic_year', date('Y'));
$school_gender_type = getSchoolGenderType($pdo);

$admin_email        = getSetting($pdo, 'admin_email', 'info@ncedges.com');
$application_notification_emails = getSetting($pdo, 'application_notification_emails', $admin_email);

// SMTP Settings
$smtp_enabled       = getSetting($pdo, 'smtp_enabled', '1');
$smtp_host          = getSetting($pdo, 'smtp_host', 'mail-gw.ncedges.com');
$smtp_port          = getSetting($pdo, 'smtp_port', '587');
$smtp_encryption    = getSetting($pdo, 'smtp_encryption', 'tls');
$smtp_auth          = getSetting($pdo, 'smtp_auth', '1');
$smtp_username      = getSetting($pdo, 'smtp_username', 'admissions-mutoleress@ncedges.com');
$smtp_password      = getSetting($pdo, 'smtp_password', 'Uganda@23');
$smtp_from_email    = getSetting($pdo, 'smtp_from_email', 'admissions-mutoleress@ncedges.com');
$smtp_from_name     = getSetting($pdo, 'smtp_from_name', 'Admissions Office');

// Intake Dates
$admission_s1_start_date    = getSetting($pdo, 'admission_s1_start_date', '2026-01-05');
$admission_s1_end_date      = getSetting($pdo, 'admission_s1_end_date', '2026-12-31');
$admission_s5_start_date    = getSetting($pdo, 'admission_s5_start_date', '2026-01-10');
$admission_s5_end_date      = getSetting($pdo, 'admission_s5_end_date', '2026-12-31');
$admission_trans_start_date = getSetting($pdo, 'admission_trans_start_date', '2026-01-15');
$admission_trans_end_date   = getSetting($pdo, 'admission_trans_end_date', '2026-12-31');

$admission_s1_passmark      = getSetting($pdo, 'admission_s1_passmark', '28');
$admission_s5_passmark      = getSetting($pdo, 'admission_s5_passmark', '42');

$reporting_date_s1          = getSetting($pdo, 'reporting_date_s1', '2026-02-02');
$reporting_date_s5          = getSetting($pdo, 'reporting_date_s5', '2026-02-09');
$reporting_date_trans       = getSetting($pdo, 'reporting_date_trans', '2026-02-02');

$val_date_s1    = preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($reporting_date_s1)) ? trim($reporting_date_s1) : (strtotime($reporting_date_s1) ? date('Y-m-d', strtotime($reporting_date_s1)) : '2026-02-02');
$val_date_s5    = preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($reporting_date_s5)) ? trim($reporting_date_s5) : (strtotime($reporting_date_s5) ? date('Y-m-d', strtotime($reporting_date_s5)) : '2026-02-09');
$val_date_trans = preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($reporting_date_trans)) ? trim($reporting_date_trans) : (strtotime($reporting_date_trans) ? date('Y-m-d', strtotime($reporting_date_trans)) : '2026-02-02');

$school_requirements        = getSetting($pdo, 'school_requirements', "1. School Uniform (Available at School Bursar's Office)\n2. Black leather shoes and white socks / stockings\n3. 10 Counter books (4-quire) and mathematical set\n4. Ream of photocopy paper (Rotatrim / Double A)\n5. 1 Hoe with handle and 1 Slasher\n6. Standard 2.5-inch Mattress, 2 pairs of bedsheets, and a blanket\n7. Metallic suitcase / trunk with sturdy padlock\n8. Passport photos (4 copies) and original PLE / UCE result slip");
$admission_letter_custom_note = getSetting($pdo, 'admission_letter_custom_note', 'Please note: This provisional admission offer is subject to physical verification of original UNEB results slips and birth certificate upon arrival on the reporting date. Fees once paid are non-refundable.');

$ai_auto_admit_enabled       = getSetting($pdo, 'ai_auto_admit_enabled', '0');
$ai_auto_combination_enabled = getSetting($pdo, 'ai_auto_combination_enabled', '0');
$show_parent_id_upload       = getSetting($pdo, 'show_parent_id_upload', '1');
$show_passport_photo_upload  = getSetting($pdo, 'show_passport_photo_upload', '1');

$doc_school_rules           = getSetting($pdo, 'doc_school_rules', '');
$doc_admission_circular     = getSetting($pdo, 'doc_admission_circular', '');
$doc_medical_form           = getSetting($pdo, 'doc_medical_form', '');

$status_s1    = getAdmissionStatus($pdo, 'S1');
$status_s5    = getAdmissionStatus($pdo, 'S5');
$status_trans = getAdmissionStatus($pdo, 'trans');

// Fees
$fees_s1_tuition    = getSetting($pdo, 'fees_s1_tuition', '550000');
$fees_s1_admission  = getSetting($pdo, 'fees_s1_admission', '100000');
$fees_s1_functional = getSetting($pdo, 'fees_s1_functional', '150000');

$fees_s2_tuition    = getSetting($pdo, 'fees_s2_tuition', '560000');
$fees_s2_admission  = getSetting($pdo, 'fees_s2_admission', '100000');
$fees_s2_functional = getSetting($pdo, 'fees_s2_functional', '155000');

$fees_s3_tuition    = getSetting($pdo, 'fees_s3_tuition', '570000');
$fees_s3_admission  = getSetting($pdo, 'fees_s3_admission', '100000');
$fees_s3_functional = getSetting($pdo, 'fees_s3_functional', '160000');

$fees_s4_tuition    = getSetting($pdo, 'fees_s4_tuition', '580000');
$fees_s4_admission  = getSetting($pdo, 'fees_s4_admission', '110000');
$fees_s4_functional = getSetting($pdo, 'fees_s4_functional', '165000');

$fees_s5_tuition    = getSetting($pdo, 'fees_s5_tuition', '600000');
$fees_s5_admission  = getSetting($pdo, 'fees_s5_admission', '120000');
$fees_s5_functional = getSetting($pdo, 'fees_s5_functional', '180000');

$fees_s6_tuition    = getSetting($pdo, 'fees_s6_tuition', '620000');
$fees_s6_admission  = getSetting($pdo, 'fees_s6_admission', '120000');
$fees_s6_functional = getSetting($pdo, 'fees_s6_functional', '185000');
$currentFeesMatrix  = getFeesStructureMatrix($pdo);

// Logo and Signature preview URLs
$logo_source = getSchoolLogoSrc($pdo, '../');
$signature_url = getSchoolSignatureUrl($pdo, '../');
$school_website_url = getSchoolWebsiteUrl($pdo);
$adminFirstName = getAdminDisplayName($pdo, $_SESSION['admin_username'] ?? '');

$cf_turnstile_enabled    = getSetting($pdo, 'cf_turnstile_enabled', '0');
$cf_turnstile_site_key   = getSetting($pdo, 'cf_turnstile_site_key', '');
$cf_turnstile_secret_key = getSetting($pdo, 'cf_turnstile_secret_key', '');
$staff_mfa_enabled       = getSetting($pdo, 'staff_mfa_enabled', '1');
$a_level_combinations    = getSetting($pdo, 'a_level_combinations', '');

$slider_images_json      = getSetting($pdo, 'hero_slider_images', '');
$current_slider_images   = [];
if (!empty($slider_images_json)) {
    $decoded = json_decode($slider_images_json, true);
    if (is_array($decoded)) {
        $current_slider_images = $decoded;
    }
}
if (empty($current_slider_images)) {
    $current_slider_images = [
        'images/campus.png',
        'images/students.png',
        'images/lab.png'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $canAllSettings ? 'System Settings' : 'Admissions Intake Policies & Fees Matrix' ?> | <?= htmlspecialchars($school_short_name) ?></title>
<link rel="icon" type="image/png" href="<?= !empty($logo_source) ? htmlspecialchars($logo_source) : '../images/default_logo.png' ?>">
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
    <aside id="mobileSidebar" class="fixed top-0 bottom-0 left-0 w-72 bg-slate-900 text-slate-100 flex flex-col z-50 transform -translate-x-full transition-transform duration-200 ease-in-out md:hidden shadow-2xl">
        <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="<?= htmlspecialchars($logo_source) ?>" alt="Logo" class="w-9 h-9 bg-white rounded-full p-1 border-2 border-gold-500 object-contain">
                <div class="min-w-0">
                    <span class="block text-xs font-bold text-white tracking-tight uppercase truncate"><?= htmlspecialchars($school_short_name) ?></span>
                    <span class="block text-[9px] font-semibold text-gold-500 uppercase tracking-widest">Staff Portal</span>
                </div>
            </div>
            <button type="button" onclick="toggleMobileSidebar()" class="text-slate-400 hover:text-white p-1" aria-label="Close menu">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <nav class="flex-1 px-4 py-4 space-y-1.5 overflow-y-auto">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-graduation-cap text-lg"></i> Admitted Register
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-file-invoice text-lg text-gold-500"></i> Custom Reports
            </a>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_users')): ?>
            <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-users text-lg"></i> Staff Users
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_roles') || hasPermission($pdo, $currentRole, 'can_manage_users')): ?>
            <a href="roles.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-shield-halved text-lg"></i> Roles & Access
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_view_logs')): ?>
            <a href="logs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-clock-rotate-left text-lg"></i> Activity Logs
            </a>
            <?php endif; ?>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition">
                <i class="fa-solid fa-sliders text-lg"></i> <?= $canAllSettings ? 'System Settings' : 'Policies & Fees' ?>
            </a>
            <div class="pt-4 border-t border-slate-800 mt-4">
                <a href="<?= htmlspecialchars($school_website_url) ?>" target="_blank" class="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition">
                    <i class="fa-solid fa-globe text-gold-500"></i> Main School Website <i class="fa-solid fa-arrow-up-right-from-square text-[9px] ml-auto"></i>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Desktop Sidebar -->
    <aside class="hidden md:flex w-64 bg-slate-900 text-slate-100 flex-col shrink-0 min-h-screen">
        <div class="px-6 py-5 border-b border-slate-800 flex items-center gap-3">
            <img src="<?= htmlspecialchars($logo_source) ?>" alt="Logo" class="w-10 h-10 bg-white rounded-full p-1 border-2 border-gold-500 object-contain">
            <div class="min-w-0">
                <span class="block text-sm font-bold text-white tracking-tight uppercase truncate"><?= htmlspecialchars($school_short_name) ?></span>
                <span class="block text-xs font-semibold text-gold-500 uppercase tracking-widest text-[9px]">Staff Portal</span>
            </div>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1.5">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-graduation-cap text-lg"></i> Admitted Register
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-file-invoice text-lg text-gold-500"></i> Custom Reports
            </a>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_users')): ?>
            <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-users text-lg"></i> Staff Users
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_roles') || hasPermission($pdo, $currentRole, 'can_manage_users')): ?>
            <a href="roles.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-shield-halved text-lg"></i> Roles & Access
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_view_logs')): ?>
            <a href="logs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <i class="fa-solid fa-clock-rotate-left text-lg"></i> Activity Logs
            </a>
            <?php endif; ?>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition">
                <i class="fa-solid fa-sliders text-lg"></i> <?= $canAllSettings ? 'System Settings' : 'Policies & Fees' ?>
            </a>

            <div class="pt-4 border-t border-slate-800 mt-4">
                <a href="<?= htmlspecialchars($school_website_url) ?>" target="_blank" class="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition">
                    <i class="fa-solid fa-globe text-gold-500"></i> Main School Website <i class="fa-solid fa-arrow-up-right-from-square text-[9px] ml-auto"></i>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 bg-slate-50 min-h-screen">
        <header class="bg-white border-b border-slate-200 px-4 sm:px-6 md:px-8 py-3.5 flex flex-col sm:flex-row justify-between sm:items-center gap-3">
            <div class="flex items-center gap-3">
                <button type="button" onclick="toggleMobileSidebar()" class="md:hidden p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-xl focus:outline-none" aria-label="Toggle Navigation">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <h1 class="text-base sm:text-xl font-bold text-slate-900 tracking-tight">
                        <?= $canAllSettings ? 'System Configuration & Master Controls' : 'Intake Policies, Fees Matrix & Offer Letters' ?>
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5 hidden sm:block">
                        <?= $canAllSettings ? 'Customize school branding, intake dates, fee matrices, SMTP gateways, and official documents.' : 'Manage intake dates, passmarks, class fee matrices, offer letter details, and signature.' ?>
                    </p>
                </div>
            </div>

            <!-- Profile & Header Actions -->
            <div class="flex items-center gap-3 self-end sm:self-auto">
                <button type="submit" form="mainSettingsForm" class="px-3.5 sm:px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-xs font-semibold rounded-xl shadow-sm transition flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i> <span class="hidden sm:inline">Save</span> All Settings
                </button>

                <div class="relative">
                    <button id="profileDropdownBtn" onclick="toggleDropdown()" class="flex items-center gap-2.5 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl hover:bg-slate-100 transition focus:outline-none">
                        <div class="w-8 h-8 rounded-full bg-brand-700 text-white flex items-center justify-center font-bold text-xs uppercase shadow-sm">
                            <?= strtoupper(substr($adminFirstName, 0, 1)) ?>
                        </div>
                        <div class="text-left hidden sm:block">
                            <span class="block text-xs font-bold text-slate-800">Welcome, <?= htmlspecialchars($adminFirstName) ?></span>
                            <span class="block text-[10px] text-slate-400 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $currentRole)) ?></span>
                        </div>
                        <i class="fa-solid fa-chevron-down text-slate-400 text-xs ml-1"></i>
                    </button>

                    <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-slate-200 rounded-xl shadow-lg py-1 z-50">
                        <a href="change_password.php" class="block px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2">
                            <i class="fa-solid fa-key text-slate-400"></i> Change Password
                        </a>
                        <div class="border-t border-slate-100 my-1"></div>
                        <a href="logout.php" class="block px-4 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 flex items-center gap-2">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Interactive Settings Category Navigation Pills (Condensed titles to avoid horizontal scrolling) -->
        <div class="bg-white border-b border-slate-200 px-4 sm:px-6 py-2.5 overflow-x-auto">
            <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                <?php if ($canAllSettings): ?>
                <button type="button" onclick="switchTab('profile')" id="pill-profile" class="tab-pill px-3 py-1.5 text-xs font-bold rounded-xl transition flex items-center gap-1.5 bg-brand-700 text-white shadow-sm whitespace-nowrap">
                    <i class="fa-solid fa-school text-[11px]"></i> Branding
                </button>
                <?php endif; ?>
                <button type="button" onclick="switchTab('admissions')" id="pill-admissions" class="tab-pill px-3 py-1.5 text-xs font-bold rounded-xl transition flex items-center gap-1.5 <?= !$canAllSettings ? 'bg-brand-700 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?> whitespace-nowrap">
                    <i class="fa-solid fa-calendar-check text-[11px] text-gold-600"></i> Admissions
                </button>
                <button type="button" onclick="switchTab('fees')" id="pill-fees" class="tab-pill px-3 py-1.5 text-xs font-bold rounded-xl transition flex items-center gap-1.5 bg-slate-100 text-slate-600 hover:bg-slate-200 whitespace-nowrap">
                    <i class="fa-solid fa-coins text-[11px] text-brand-700"></i> Fees
                </button>
                <?php if ($canAllSettings): ?>
                <button type="button" onclick="switchTab('smtp')" id="pill-smtp" class="tab-pill px-3 py-1.5 text-xs font-bold rounded-xl transition flex items-center gap-1.5 bg-slate-100 text-slate-600 hover:bg-slate-200 whitespace-nowrap">
                    <i class="fa-solid fa-envelope-circle-check text-[11px] text-blue-600"></i> SMTP
                </button>
                <?php endif; ?>
                <button type="button" onclick="switchTab('letters')" id="pill-letters" class="tab-pill px-3 py-1.5 text-xs font-bold rounded-xl transition flex items-center gap-1.5 bg-slate-100 text-slate-600 hover:bg-slate-200 whitespace-nowrap">
                    <i class="fa-solid fa-file-signature text-[11px] text-purple-600"></i> Letters
                </button>
                <?php if ($canAllSettings): ?>
                <button type="button" onclick="switchTab('slider')" id="pill-slider" class="tab-pill px-3 py-1.5 text-xs font-bold rounded-xl transition flex items-center gap-1.5 bg-slate-100 text-slate-600 hover:bg-slate-200 whitespace-nowrap">
                    <i class="fa-solid fa-images text-[11px] text-amber-600"></i> Slider
                </button>
                <button type="button" onclick="switchTab('security')" id="pill-security" class="tab-pill px-3 py-1.5 text-xs font-bold rounded-xl transition flex items-center gap-1.5 bg-slate-100 text-slate-600 hover:bg-slate-200 whitespace-nowrap">
                    <i class="fa-solid fa-shield-halved text-[11px] text-emerald-600"></i> Security
                </button>
                <?php endif; ?>
                <?php if ($canAllSettings || hasPermission($pdo, $currentRole, 'can_manage_roles')): ?>
                <a href="roles.php" class="px-3 py-1.5 text-xs font-bold rounded-xl transition flex items-center gap-1.5 bg-slate-100 text-slate-600 hover:bg-slate-200 whitespace-nowrap ms-auto">
                    <i class="fa-solid fa-user-shield text-[11px] text-slate-500"></i> Roles & Access
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="p-4 sm:p-6 max-w-7xl w-full mx-auto space-y-6">

            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold rounded-xl flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-triangle-exclamation text-rose-600 text-base"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold rounded-xl flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <form id="mainSettingsForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="active_tab" id="activeTabInput" value="<?= htmlspecialchars($_POST['active_tab'] ?? ($canAllSettings ? 'profile' : 'admissions')) ?>">

                <!-- TAB 1: SCHOOL PROFILE & BRANDING (Super Admin Only) -->
                <?php if ($canAllSettings): ?>
                <div id="tab-profile" class="tab-content space-y-6">
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-5">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                    <i class="fa-solid fa-building-columns text-brand-700"></i> General School Identity
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">These parameters populate all web headers, offer letters, student documents, and report headers.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Official School Name *</label>
                                <input type="text" name="school_name" value="<?= htmlspecialchars($school_name) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:border-brand-700" required>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Short Name / Acronym *</label>
                                <input type="text" name="school_short_name" value="<?= htmlspecialchars($school_short_name) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:border-brand-700" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">School Motto</label>
                                <input type="text" name="school_motto" value="<?= htmlspecialchars($school_motto) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-brand-700">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">UNEB Centre Code</label>
                                <input type="text" name="school_code" value="<?= htmlspecialchars($school_code) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:outline-none focus:border-brand-700" placeholder="e.g. 011">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Current Academic Intake Year</label>
                                <input type="text" id="academic_year_branding" value="<?= htmlspecialchars($academic_year) ?>" class="sync-academic-year w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold font-mono text-slate-800 focus:outline-none focus:border-brand-700" placeholder="e.g. 2026">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Physical Campus Address</label>
                                <input type="text" name="school_address" value="<?= htmlspecialchars($school_address) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-brand-700">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Official Telephone Line</label>
                                <input type="text" name="school_phone" value="<?= htmlspecialchars($school_phone) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-brand-700">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Official School Email</label>
                                <input type="email" name="school_email" value="<?= htmlspecialchars($school_email) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-brand-700">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Main School Website (URL)</label>
                                <input type="text" name="school_website" value="<?= htmlspecialchars($school_website) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-brand-700" placeholder="e.g. www.sesemegirls.sc.ug">
                                <p class="text-[11px] text-slate-400 mt-1">Appears on navigation menus and offer letters with direct link.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Portal Base URL (Optional)</label>
                                <input type="text" name="portal_base_url" value="<?= htmlspecialchars($portal_base_url) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-brand-700" placeholder="Leave blank for auto-detect">
                            </div>
                        </div>

                        <!-- Student Number Format Configuration Card -->
                        <div class="p-4 sm:p-5 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-200/80 pb-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="w-8 h-8 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center text-sm font-bold border border-brand-200">
                                        <i class="fa-solid fa-id-card-clip"></i>
                                    </span>
                                    <div>
                                        <h4 class="text-xs font-bold text-slate-900 uppercase">Student Number / Admission ID Format</h4>
                                        <p class="text-[11px] text-slate-500">Configure institutional numbering rules for enrolled and admitted students.</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 self-start sm:self-auto">
                                    <span class="text-[11px] font-bold text-slate-500">Live Preview:</span>
                                    <span id="studentNumberPreviewBadge" class="px-2.5 py-1 rounded-lg text-xs font-mono font-black bg-brand-100 text-brand-900 border border-brand-300">
                                        <?= htmlspecialchars(getAdmissionNumber(['id' => 1, 'class_of_entry' => 'S5', 'academic_year' => $academic_year], $pdo)) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase">
                                    Pattern / Format String
                                </label>
                                <input type="text" name="student_number_format" id="studentNumberFormatInput" value="<?= htmlspecialchars($student_number_format) ?>" oninput="updateStudentNumberPreview()" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-xs sm:text-sm font-mono font-bold text-slate-800 bg-white focus:outline-none focus:border-brand-700 transition" placeholder="{YY}{CLASS}{CODE}{SERIAL}">
                            </div>

                            <!-- Preset Buttons -->
                            <div class="space-y-1.5 pt-1">
                                <span class="block text-[10.5px] font-bold text-slate-500 uppercase">Quick Presets (Click to apply):</span>
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <button type="button" onclick="setStudentNumberPreset('{YY}{CLASS}{CODE}{SERIAL}')" class="px-2.5 py-1 bg-white hover:bg-slate-100 text-slate-700 rounded-lg border border-slate-200 font-mono text-[11px] transition">
                                        {YY}{CLASS}{CODE}{SERIAL} <span class="text-slate-400 font-sans">(e.g. 270509801)</span>
                                    </button>
                                    <button type="button" onclick="setStudentNumberPreset('ADM/{YYYY}/{CLASS}/{SERIAL4}')" class="px-2.5 py-1 bg-white hover:bg-slate-100 text-slate-700 rounded-lg border border-slate-200 font-mono text-[11px] transition">
                                        ADM/{YYYY}/{CLASS}/{SERIAL4} <span class="text-slate-400 font-sans">(e.g. ADM/2026/05/0001)</span>
                                    </button>
                                    <button type="button" onclick="setStudentNumberPreset('{CODE}/{YY}/{SERIAL4}')" class="px-2.5 py-1 bg-white hover:bg-slate-100 text-slate-700 rounded-lg border border-slate-200 font-mono text-[11px] transition">
                                        {CODE}/{YY}/{SERIAL4} <span class="text-slate-400 font-sans">(e.g. 011/26/0001)</span>
                                    </button>
                                    <button type="button" onclick="setStudentNumberPreset('{YYYY}-{CLASS_NAME}-{SERIAL3}')" class="px-2.5 py-1 bg-white hover:bg-slate-100 text-slate-700 rounded-lg border border-slate-200 font-mono text-[11px] transition">
                                        {YYYY}-{CLASS_NAME}-{SERIAL3} <span class="text-slate-400 font-sans">(e.g. 2026-S5-001)</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Token Explanation Guide -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2 text-[10.5px] text-slate-600 bg-white p-3 rounded-xl border border-slate-200/80">
                                <div><code class="font-bold text-brand-800">{YY}</code> : 2-digit Year (26)</div>
                                <div><code class="font-bold text-brand-800">{YYYY}</code> : 4-digit Year (2026)</div>
                                <div><code class="font-bold text-brand-800">{CLASS}</code> : 2-digit Class (01, 05)</div>
                                <div><code class="font-bold text-brand-800">{CLASS_NAME}</code> : Class Code (S1, S5)</div>
                                <div><code class="font-bold text-brand-800">{CODE}</code> : Center Code (011)</div>
                                <div><code class="font-bold text-brand-800">{SERIAL}</code> : 2-digit Serial (01)</div>
                                <div><code class="font-bold text-brand-800">{SERIAL3}</code> : 3-digit Serial (001)</div>
                                <div><code class="font-bold text-brand-800">{SERIAL4}</code> : 4-digit Serial (0001)</div>
                            </div>
                        </div>

                        <!-- System Colors & Logo Upload -->
                        <div class="pt-4 border-t border-slate-100 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Theme Colors -->
                            <div class="space-y-3">
                                <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">System Color Branding</h4>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Primary Color</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" id="primaryColorPicker" name="primary_color" value="<?= htmlspecialchars($primary_color) ?>" class="w-8 h-8 rounded cursor-pointer border border-slate-200">
                                            <input type="text" id="primaryColorInput" value="<?= htmlspecialchars($primary_color) ?>" class="w-full px-2 py-1 border border-slate-200 rounded-lg text-xs font-mono">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Accent Gold Color</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" id="accentColorPicker" name="accent_color" value="<?= htmlspecialchars($accent_color) ?>" class="w-8 h-8 rounded cursor-pointer border border-slate-200">
                                            <input type="text" id="accentColorInput" value="<?= htmlspecialchars($accent_color) ?>" class="w-full px-2 py-1 border border-slate-200 rounded-lg text-xs font-mono">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Logo Upload -->
                            <div class="space-y-3">
                                <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Official School Crest / Logo</h4>
                                <div class="flex items-center gap-4">
                                    <div class="w-16 h-16 rounded-xl border border-slate-200 p-1 bg-white shadow-sm flex items-center justify-center shrink-0">
                                        <img src="<?= htmlspecialchars($logo_source) ?>" id="currentLogoPreview" alt="Logo Preview" class="max-w-full max-h-full object-contain">
                                    </div>
                                    <div class="flex-1 space-y-1">
                                        <input type="file" name="logo_upload" accept="image/*" onchange="previewSelectedLogo(event)" class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer">
                                        <p class="text-[10px] text-slate-400">Upload PNG or JPG. Changes reflect immediately system-wide.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- TAB 2: ADMISSIONS & INTAKE POLICIES -->
                <div id="tab-admissions" class="tab-content <?= $canAllSettings ? 'hidden' : '' ?> space-y-6">
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-5">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                    <i class="fa-solid fa-venus-mars text-brand-700"></i> School Gender Policy
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">Define if the school admits boys only, girls only, or both.</p>
                            </div>
                        </div>

                        <div>
                            <?php if (($_SESSION['admin_role'] ?? '') === 'super_admin'): ?>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                                        <input type="radio" name="school_gender_type" value="mixed" <?= ($school_gender_type === 'mixed') ? 'checked' : '' ?> class="text-brand-700">
                                        <div>
                                            <span class="block text-xs font-bold text-slate-800">Mixed School</span>
                                            <span class="block text-[10px] text-slate-400">Accepts Male & Female</span>
                                        </div>
                                    </label>
                                    <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                                        <input type="radio" name="school_gender_type" value="girls" <?= ($school_gender_type === 'girls') ? 'checked' : '' ?> class="text-brand-700">
                                        <div>
                                            <span class="block text-xs font-bold text-slate-800">Girls Only School</span>
                                            <span class="block text-[10px] text-slate-400">Female Applicants Only</span>
                                        </div>
                                    </label>
                                    <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                                        <input type="radio" name="school_gender_type" value="boys" <?= ($school_gender_type === 'boys') ? 'checked' : '' ?> class="text-brand-700">
                                        <div>
                                            <span class="block text-xs font-bold text-slate-800">Boys Only School</span>
                                            <span class="block text-[10px] text-slate-400">Male Applicants Only</span>
                                        </div>
                                    </label>
                                </div>
                            <?php else: ?>
                                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                                    <div class="flex items-center gap-2 text-xs font-bold text-amber-800">
                                        <i class="fa-solid fa-lock text-amber-600"></i>
                                        <span>Restricted Setting: Only Super Administrator can modify the school gender admission policy.</span>
                                    </div>
                                    <div class="flex items-center gap-2 pt-1">
                                        <span class="text-xs text-slate-500 font-semibold">Active Institutional Policy:</span>
                                        <span class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-brand-100 text-brand-900 border border-brand-300">
                                            <?= ($school_gender_type === 'girls') ? 'Girls Only School' : (($school_gender_type === 'boys') ? 'Boys Only School' : 'Mixed School') ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="pt-4 border-t border-slate-100">
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3">Intake Calendars & Cutoff Passmarks</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                <!-- Senior One -->
                                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                                    <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                                        <span class="font-bold text-brand-700 text-xs uppercase">Senior One (S1)</span>
                                        <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= $status_s1['status'] === 'open' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                            <?= strtoupper($status_s1['status']) ?>
                                        </span>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Start Date</label>
                                        <input type="date" name="admission_s1_start_date" value="<?= htmlspecialchars($admission_s1_start_date) ?>" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs bg-white">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Application Deadline</label>
                                        <input type="date" name="admission_s1_end_date" value="<?= htmlspecialchars($admission_s1_end_date) ?>" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs bg-white">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">PLE Cutoff Passmark (Aggregates)</label>
                                        <input type="number" name="admission_s1_passmark" value="<?= htmlspecialchars($admission_s1_passmark) ?>" min="4" max="36" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-mono font-bold bg-white">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Reporting Date</label>
                                        <input type="date" name="reporting_date_s1" value="<?= htmlspecialchars($val_date_s1) ?>" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs bg-white">
                                    </div>
                                </div>

                                <!-- Senior Five -->
                                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                                    <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                                        <span class="font-bold text-brand-700 text-xs uppercase">Senior Five (S5)</span>
                                        <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= $status_s5['status'] === 'open' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                            <?= strtoupper($status_s5['status']) ?>
                                        </span>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Start Date</label>
                                        <input type="date" name="admission_s5_start_date" value="<?= htmlspecialchars($admission_s5_start_date) ?>" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs bg-white">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Application Deadline</label>
                                        <input type="date" name="admission_s5_end_date" value="<?= htmlspecialchars($admission_s5_end_date) ?>" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs bg-white">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">UCE Cutoff Passmark (Aggregates)</label>
                                        <input type="number" name="admission_s5_passmark" value="<?= htmlspecialchars($admission_s5_passmark) ?>" min="8" max="54" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-mono font-bold bg-white">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Reporting Date</label>
                                        <input type="date" name="reporting_date_s5" value="<?= htmlspecialchars($val_date_s5) ?>" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs bg-white">
                                    </div>
                                </div>

                                <!-- Transfers -->
                                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                                    <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                                        <span class="font-bold text-brand-700 text-xs uppercase">Transfers (S2-S6)</span>
                                        <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= $status_trans['status'] === 'open' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                            <?= strtoupper($status_trans['status']) ?>
                                        </span>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Start Date</label>
                                        <input type="date" name="admission_trans_start_date" value="<?= htmlspecialchars($admission_trans_start_date) ?>" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs bg-white">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Application Deadline</label>
                                        <input type="date" name="admission_trans_end_date" value="<?= htmlspecialchars($admission_trans_end_date) ?>" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs bg-white">
                                    </div>
                                    <div class="p-2 bg-amber-50 rounded-lg border border-amber-200 text-[10px] text-amber-800 font-medium">
                                        <i class="fa-solid fa-circle-check text-amber-600"></i> No Cutoff Point Required: Transfers are evaluated manually.
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Reporting Date</label>
                                        <input type="date" name="reporting_date_trans" value="<?= htmlspecialchars($val_date_trans) ?>" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs bg-white">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Smart Admissions & Subject Combination Engine Card -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                    <i class="fa-solid fa-wand-magic-sparkles text-brand-700"></i> AI Smart Admissions & Subject Combination Engine
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">Automate candidate qualification evaluation, smart merit auto-admissions, and A-Level combination assignments.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                                <label class="flex items-center justify-between cursor-pointer">
                                    <div>
                                        <span class="block text-xs font-bold text-slate-800">System Auto-Admissions (Disabled - Manual Admissions Enforced)</span>
                                        <span class="block text-[11px] text-slate-500">School admissions committee manually evaluates all candidate documents and approves admissions.</span>
                                    </div>
                                    <input type="checkbox" name="ai_auto_admit_enabled" value="1" <?= ($ai_auto_admit_enabled === '1') ? 'checked' : '' ?> class="w-5 h-5 rounded text-brand-700 focus:ring-brand-700">
                                </label>
                                <p class="text-[10px] text-slate-400 leading-relaxed">
                                    Recommended: Keep disabled. Per school admissions policy, all submitted applications remain in pending review until authorized school administrators manually verify documents and click Admit.
                                </p>
                            </div>

                            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                                <label class="flex items-center justify-between cursor-pointer">
                                    <div>
                                        <span class="block text-xs font-bold text-slate-800">Manual S5/S6 Combination Assignment (Active)</span>
                                        <span class="block text-[11px] text-slate-500">Candidates submit desired combinations; admissions committee assigns final combinations.</span>
                                    </div>
                                    <input type="checkbox" name="ai_auto_combination_enabled" value="1" <?= ($ai_auto_combination_enabled === '1') ? 'checked' : '' ?> class="w-5 h-5 rounded text-brand-700 focus:ring-brand-700">
                                </label>
                                <p class="text-[10px] text-slate-400 leading-relaxed">
                                    In accordance with Ugandan UNEB curriculum guidelines, all combinations involving Principal Mathematics strictly take Subsidiary ICT. The school admissions committee manually assigns or confirms the combination upon admission.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Applicant Document Upload Requirements Card -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                    <i class="fa-solid fa-file-shield text-brand-700"></i> Public Form Document Upload Requirements
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">Control which optional identification documents are required from applicants on the public admission form.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Parent National ID Upload Requirement -->
                            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                                <label class="flex items-center justify-between cursor-pointer">
                                    <div>
                                        <span class="block text-xs font-bold text-slate-800">Require Parent National ID Upload</span>
                                        <span class="block text-[11px] text-slate-500">Require parents to upload a copy of their National ID / NIN card.</span>
                                    </div>
                                    <input type="checkbox" name="show_parent_id_upload" value="1" <?= ($show_parent_id_upload === '1') ? 'checked' : '' ?> class="w-5 h-5 rounded text-brand-700 focus:ring-brand-700">
                                </label>
                                <p class="text-[10px] text-slate-400 leading-relaxed">
                                    <i class="fa-solid fa-circle-info mr-1 text-brand-700"></i> If unchecked, this field is completely hidden on the public portal. Many parents prefer not to share ID photos online; they can present physical ID on reporting day.
                                </p>
                            </div>

                            <!-- Student Passport Photo Upload Requirement -->
                            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                                <label class="flex items-center justify-between cursor-pointer">
                                    <div>
                                        <span class="block text-xs font-bold text-slate-800">Require Student Passport Photo Upload</span>
                                        <span class="block text-[11px] text-slate-500">Require candidates to upload a digital passport photograph.</span>
                                    </div>
                                    <input type="checkbox" name="show_passport_photo_upload" value="1" <?= ($show_passport_photo_upload === '1') ? 'checked' : '' ?> class="w-5 h-5 rounded text-brand-700 focus:ring-brand-700">
                                </label>
                                <p class="text-[10px] text-slate-400 leading-relaxed">
                                    <i class="fa-solid fa-circle-info mr-1 text-brand-700"></i> If unchecked, passport photo upload is skipped to make applying faster and easier on mobile phones. Physical photos will be collected upon arrival.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Academic Year Cohort Archival & Active Intake Card -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                    <i class="fa-solid fa-clock-rotate-left text-brand-700"></i> Academic Intake Cohort & Historical Archival
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">Control the current public admissions year while segregating and preserving historical admission cohorts.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 items-center">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Active Academic Year *</label>
                                <input type="text" name="academic_year" id="academic_year_admissions" value="<?= htmlspecialchars($academic_year) ?>" required class="sync-academic-year w-full px-3 py-2 border-2 border-brand-700/60 rounded-xl text-sm font-bold font-mono text-brand-900 bg-brand-50/20 focus:outline-none focus:border-brand-700 transition" placeholder="e.g. 2026">
                                <p class="text-[10px] text-slate-400 mt-1">Populates public forms, offer letters, and admission lists.</p>
                            </div>
                            <div class="md:col-span-2 p-4 bg-emerald-50 rounded-xl border border-emerald-200 text-xs text-emerald-900 space-y-1">
                                <span class="font-bold flex items-center gap-1.5"><i class="fa-solid fa-shield-halved text-emerald-600"></i> Zero-Data-Loss Archival Guarantee:</span>
                                <p class="text-[11px] leading-relaxed text-emerald-800">
                                    Changing this year automatically isolates the public portal to the new intake. All candidate records from previous academic years (e.g. 2024, 2025) are safely preserved and archived from the public view, remaining fully accessible to authorized staff with the Academic Year filter in the Admin Dashboard and Custom Reports.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- A-Level Subject Combinations & Electives Matrix Card -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                    <i class="fa-solid fa-graduation-cap text-brand-700"></i> A-Level Subject Combinations Matrix (S5 & S6)
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">Define and customize the subject combinations and subsidiaries offered to Senior Five and Senior Six applicants.</p>
                            </div>
                            <button type="button" onclick="resetDefaultCombinations()" class="px-3 py-1.5 rounded-lg text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition">
                                <i class="fa-solid fa-rotate-left mr-1"></i> Reset to UNEB Standards
                            </button>
                        </div>

                        <?php
                        $rawCombs = !empty($a_level_combinations) ? $a_level_combinations : json_encode([
                            'Sciences' => [
                                'PCM/ICT' => 'PCM/ICT (Physics, Chemistry, Mathematics & ICT)',
                                'PCB/ICT' => 'PCB/ICT (Physics, Chemistry, Biology & ICT)',
                                'PCB/SMT' => 'PCB/SMT (Physics, Chemistry, Biology & Sub-Math)',
                                'BCM/ICT' => 'BCM/ICT (Biology, Chemistry, Mathematics & ICT)',
                                'PEM/ICT' => 'PEM/ICT (Physics, Economics, Mathematics & ICT)',
                                'MEG/ICT' => 'MEG/ICT (Mathematics, Economics, Geography & ICT)',
                                'BCG/ICT' => 'BCG/ICT (Biology, Chemistry, Geography & ICT)',
                                'BCG/SMT' => 'BCG/SMT (Biology, Chemistry, Geography & Sub-Math)',
                                'MAG/ICT' => 'MAG/ICT (Mathematics, Agriculture, Geography & ICT)',
                            ],
                            'Arts & Humanities' => [
                                'HEG/ICT' => 'HEG/ICT (History, Economics, Geography & ICT)',
                                'HEG/SMT' => 'HEG/SMT (History, Economics, Geography & Sub-Math)',
                                'HEL/ICT' => 'HEL/ICT (History, Economics, Literature & ICT)',
                                'HEL/SMT' => 'HEL/SMT (History, Economics, Literature & Sub-Math)',
                                'HLD/ICT' => 'HLD/ICT (History, Literature, Divinity & ICT)',
                                'HLD/SMT' => 'HLD/SMT (History, Literature, Divinity & Sub-Math)',
                                'DEG/ICT' => 'DEG/ICT (Divinity, Economics, Geography & ICT)',
                                'DEG/SMT' => 'DEG/SMT (Divinity, Economics, Geography & Sub-Math)',
                                'HED/ICT' => 'HED/ICT (History, Economics, Divinity & ICT)',
                                'LEG/ICT' => 'LEG/ICT (Literature, Economics, Geography & ICT)',
                                'GEA/ICT' => 'GEA/ICT (Geography, Entrepreneurship, Art & ICT)',
                                'MEA/ICT' => 'MEA/ICT (Mathematics, Economics, Art & ICT)',
                            ]
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                        ?>

                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-700 uppercase">Combinations Configuration (JSON Matrix)</label>
                            <textarea name="a_level_combinations" id="aLevelCombsTextarea" rows="10" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 bg-slate-50 focus:bg-white focus:outline-none focus:border-brand-700 transition leading-relaxed"><?= htmlspecialchars($rawCombs) ?></textarea>
                            <p class="text-[11px] text-slate-500">
                                <i class="fa-solid fa-circle-info mr-1 text-brand-700"></i> Organize combinations by category (e.g. Sciences, Arts & Humanities). Each code maps to its full title and subsidiary (ICT or Sub-Math).
                            </p>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: O & A-LEVEL FEES MATRIX -->
                <div id="tab-fees" class="tab-content hidden space-y-6">
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-6">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                    <i class="fa-solid fa-coins text-brand-700"></i> Flexible Fees Structure Matrix (2026 Schedule & Custom Items)
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">Customize table title, fee components, payment intervals, and amounts per class tier. Syncs live to public portal & admission letters.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                                <button type="button" onclick="addFeeColumn()" class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition">
                                    <i class="fa-solid fa-table-columns mr-1 text-brand-700"></i> Add Column
                                </button>
                                <button type="button" onclick="addClassRow()" class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition">
                                    <i class="fa-solid fa-plus mr-1 text-brand-700"></i> Add Class Row
                                </button>
                                <button type="button" onclick="resetDefaultFeesMatrix()" class="px-3 py-1.5 rounded-xl text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition" title="Reset back to official 2026 table layout">
                                    <i class="fa-solid fa-rotate-left mr-1"></i> Reset to 2026 Format
                                </button>
                                <button type="button" onclick="toggleJsonFeesEditor()" class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition">
                                    <i class="fa-solid fa-code mr-1"></i> JSON View
                                </button>
                            </div>
                        </div>

                        <!-- Schedule Display Title Input -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <div class="flex-1 max-w-lg">
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Fees Structure Table Title</label>
                                <input type="text" id="feesMatrixTitleInput" oninput="updateFeesMatrixTitle(this.value)" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 bg-white focus:outline-none focus:border-brand-700" value="<?= htmlspecialchars($currentFeesMatrix['title'] ?? ('Fees structure for ' . $academic_year)) ?>" placeholder="e.g. Fees structure for 2026">
                                <p class="text-[10px] text-slate-400 mt-1">Displayed directly above the fees table on the public homepage and admission portal.</p>
                            </div>
                            <div class="text-[11px] text-slate-500 font-medium">
                                <span class="font-bold text-slate-700 block"><i class="fa-solid fa-circle-info text-brand-700 mr-1"></i> Formatting Guide:</span>
                                <span>Amounts can be entered with commas and /= (e.g. <code class="font-mono bg-white px-1 py-0.5 rounded border border-slate-200">1,265,000/=</code>). Leave blank for exempt classes.</span>
                            </div>
                        </div>

                        <!-- Interactive Table Editor -->
                        <div class="overflow-x-auto rounded-xl border border-slate-300 shadow-xs bg-white">
                            <table class="w-full text-xs text-left border-collapse min-w-[780px]" id="feesMatrixEditorTable">
                                <thead id="feesMatrixTableHead">
                                    <!-- Rendered dynamically by JavaScript -->
                                </thead>
                                <tbody id="feesMatrixTableBody" class="divide-y divide-slate-200 bg-white">
                                    <!-- Rendered dynamically by JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Advanced JSON Editor (Collapsible) -->
                        <div id="feesJsonEditorContainer" class="hidden p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
                                    <i class="fa-solid fa-code text-brand-700"></i> Direct Matrix JSON Payload
                                </span>
                                <button type="button" onclick="applyFeesJson()" class="px-3 py-1 rounded-lg text-xs font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-xs">
                                    Apply JSON Changes
                                </button>
                            </div>
                            <textarea id="feesJsonTextarea" rows="8" class="w-full font-mono text-xs p-3 rounded-lg border border-slate-200 bg-white focus:outline-none focus:border-brand-700"></textarea>
                            <p class="text-[10px] text-slate-400">Advanced: You can directly copy/paste full JSON payloads or edit custom keys.</p>
                        </div>

                        <!-- Hidden Data Inputs -->
                        <input type="hidden" name="fees_structure_matrix" id="fees_structure_matrix_input" value="<?= htmlspecialchars(json_encode($currentFeesMatrix, JSON_UNESCAPED_UNICODE)) ?>">

                        <!-- Hidden Legacy Fee Inputs for Full Backwards Compatibility -->
                        <div class="hidden">
                            <?php foreach (['s1', 's2', 's3', 's4', 's5', 's6', 'trans'] as $k): ?>
                            <input type="hidden" name="fees_<?= $k ?>_tuition" id="hidden_fees_<?= $k ?>_tuition" value="<?= htmlspecialchars(${'fees_' . $k . '_tuition'} ?? '') ?>">
                            <input type="hidden" name="fees_<?= $k ?>_admission" id="hidden_fees_<?= $k ?>_admission" value="<?= htmlspecialchars(${'fees_' . $k . '_admission'} ?? '') ?>">
                            <input type="hidden" name="fees_<?= $k ?>_functional" id="hidden_fees_<?= $k ?>_functional" value="<?= htmlspecialchars(${'fees_' . $k . '_functional'} ?? '') ?>">
                            <?php endforeach; ?>
                        </div>

                        <!-- Public Portal Preview Card -->
                        <div class="pt-4 border-t border-slate-100 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <i class="fa-solid fa-eye text-brand-700"></i> Live Public Portal Display Preview
                                </span>
                                <span class="text-[10px] text-slate-400">Updates live as you type above</span>
                            </div>
                            <div class="overflow-x-auto rounded-xl border border-slate-200 p-4 bg-slate-50/50">
                                <div id="feesMatrixLivePreview"></div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- TAB 4: EMAIL & SMTP GATEWAY (Super Admin Only) -->
                <?php if ($canAllSettings): ?>
                <div id="tab-smtp" class="tab-content hidden space-y-6">
                    <!-- INCOMING APPLICATION SUBMISSION ALERTS WINDOW -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                    <i class="fa-solid fa-bell text-amber-600"></i> Application Submission Notification Recipients
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">Specify who should receive email alerts whenever a new admission application is submitted through the portal.</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 uppercase tracking-wider">Instant Alerts</span>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Notification Recipient Emails (Comma-separated) *</label>
                                <textarea name="application_notification_emails" rows="2" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:outline-none focus:border-brand-700" placeholder="e.g. admissions@sesemegirls.sc.ug, headteacher@sesemegirls.sc.ug, registrar@sesemegirls.sc.ug"><?= htmlspecialchars($application_notification_emails) ?></textarea>
                                <span class="text-[11px] text-slate-500 mt-1 block">Enter one or multiple email addresses separated by commas. Every email listed here will instantly receive full student summary alerts for incoming applications.</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-5">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                    <i class="fa-solid fa-server text-blue-600"></i> SMTP Mail Server Gateway Setup
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">Configure custom SMTP servers (webmail.ncedges.com, Gmail, Outlook, cPanel). All system emails route through this server.</p>
                            </div>
                            <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                                <input type="checkbox" name="smtp_enabled" value="1" <?= ($smtp_enabled == '1') ? 'checked' : '' ?> class="rounded text-brand-700">
                                <span>Enable SMTP Gateway</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">SMTP Server Host *</label>
                                <input type="text" id="smtpHostInput" name="smtp_host" value="<?= htmlspecialchars($smtp_host) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:outline-none focus:border-brand-700" placeholder="e.g. mail-gw.ncedges.com, smtp.gmail.com">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">SMTP Port *</label>
                                <input type="number" id="smtpPortInput" name="smtp_port" value="<?= htmlspecialchars($smtp_port) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:outline-none focus:border-brand-700" placeholder="465, 587, or 25">
                                <span class="text-[10px] text-slate-400">465 (SSL) / 587 (TLS) / 25 (Standard)</span>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Security Encryption *</label>
                                <select id="smtpEncInput" name="smtp_encryption" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-brand-700 bg-white">
                                    <option value="tls" <?= ($smtp_encryption === 'tls') ? 'selected' : '' ?>>TLS (Recommended for Port 587)</option>
                                    <option value="ssl" <?= ($smtp_encryption === 'ssl') ? 'selected' : '' ?>>SSL (Port 465)</option>
                                    <option value="none" <?= ($smtp_encryption === 'none' || empty($smtp_encryption)) ? 'selected' : '' ?>>None / Unencrypted (Port 25)</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">SMTP Username / Email *</label>
                                <input type="text" id="smtpUserInput" name="smtp_username" value="<?= htmlspecialchars($smtp_username) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-brand-700" placeholder="e.g. admissions@sesemegirls.sc.ug">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">SMTP Password *</label>
                                <input type="password" id="smtpPassInput" name="smtp_password" value="<?= htmlspecialchars($smtp_password) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-brand-700">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">From Sender Email</label>
                                <input type="email" id="smtpFromEmailInput" name="smtp_from_email" value="<?= htmlspecialchars($smtp_from_email) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-brand-700" placeholder="admissions@school.sc.ug">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">From Sender Display Name</label>
                                <input type="text" id="smtpFromNameInput" name="smtp_from_name" value="<?= htmlspecialchars($smtp_from_name) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-brand-700" placeholder="e.g. Seseme Girls SS Admissions">
                            </div>
                        </div>

                        <!-- LIVE SMTP TEST UTILITY -->
                        <div class="p-5 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-paper-plane text-brand-700"></i> Test SMTP Gateway Connection
                            </h4>
                            <p class="text-xs text-slate-500">Send an immediate test verification email to ensure host, port, credentials, and handshake succeed before live admissions emails are dispatched.</p>

                            <div class="flex flex-col sm:flex-row items-center gap-3">
                                <input type="email" id="testEmailRecipient" placeholder="Enter recipient email (e.g. info@ncedges.com)" class="flex-1 px-3 py-2 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-brand-700 bg-white">
                                <button type="button" onclick="runSmtpTest()" id="smtpTestBtn" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-2">
                                    <i class="fa-solid fa-plug"></i> Test SMTP Connection
                                </button>
                            </div>

                            <div id="smtpTestResult" class="hidden p-3.5 rounded-xl text-xs font-semibold"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- TAB 5: LETTERS, SIGNATURE & OFFICIAL DOCUMENTS -->
                <div id="tab-letters" class="tab-content hidden space-y-6">
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-5">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                    <i class="fa-solid fa-file-contract text-brand-700"></i> Admission Letter Sign-off & Head Teacher Credentials
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">Configure sign-off authority and upload transparent e-signature to appear on admission letters.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Head Teacher Official Name *</label>
                                <input type="text" name="head_teacher_name" value="<?= htmlspecialchars($head_teacher_name) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:border-brand-700" placeholder="e.g. MRS. PEACE RUZAZA">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Signatory Title</label>
                                <input type="text" name="head_teacher_title" value="<?= htmlspecialchars($head_teacher_title) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-brand-700" placeholder="Head Teacher">
                            </div>
                        </div>

                        <!-- E-Signature Upload -->
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Official E-Signature Upload</h4>
                            <p class="text-xs text-slate-500">Upload high-resolution transparent PNG or clean signature scan. When uploaded, this appears directly on the Head Teacher signature line on admission letters. If not set, a formal signature line is printed for manual pen signing.</p>

                            <div class="flex items-center gap-5">
                                <div class="w-40 h-16 border border-slate-200 rounded-xl bg-white p-2 flex items-center justify-center shrink-0">
                                    <?php if ($signature_url): ?>
                                        <img src="<?= htmlspecialchars($signature_url) ?>" alt="Signature" class="max-h-full max-w-full object-contain">
                                    <?php else: ?>
                                        <span class="text-[10px] text-slate-400 font-mono">No Signature Set</span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 space-y-1">
                                    <input type="file" name="headteacher_signature_upload" accept="image/png,image/jpeg,image/webp" class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer">
                                    <p class="text-[10px] text-slate-400">Transparent PNG recommended. Size will be neatly balanced on PDF letters.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Custom Note -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Admission Letter Custom Note / Instructions</label>
                            <textarea name="admission_letter_custom_note" rows="2" class="w-full px-3.5 py-2 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-brand-700"><?= htmlspecialchars($admission_letter_custom_note) ?></textarea>
                            <p class="text-[10px] text-slate-400 mt-1">Appears at the bottom of the official admission offer letter in small font.</p>
                        </div>

                        <!-- Requirements Checklist -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">School Requirements Checklist</label>
                            <textarea name="school_requirements" rows="4" class="w-full px-3.5 py-2 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-brand-700"><?= htmlspecialchars($school_requirements) ?></textarea>
                        </div>

                        <!-- Enclosures -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase">School Rules & Regulations</label>
                                <?php if (!empty($doc_school_rules)): ?>
                                    <a href="../<?= htmlspecialchars($doc_school_rules) ?>" target="_blank" class="text-[11px] font-semibold text-brand-700 underline flex items-center gap-1"><i class="fa-solid fa-file-pdf text-rose-500"></i> View Uploaded Rules</a>
                                <?php endif; ?>
                                <input type="file" name="doc_school_rules_file" accept=".pdf" class="w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[10px] file:bg-brand-50 file:text-brand-700 cursor-pointer">
                            </div>
                            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase">Term Admissions Circular</label>
                                <?php if (!empty($doc_admission_circular)): ?>
                                    <a href="../<?= htmlspecialchars($doc_admission_circular) ?>" target="_blank" class="text-[11px] font-semibold text-brand-700 underline flex items-center gap-1"><i class="fa-solid fa-file-pdf text-rose-500"></i> View Uploaded Circular</a>
                                <?php endif; ?>
                                <input type="file" name="doc_admission_circular_file" accept=".pdf" class="w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[10px] file:bg-brand-50 file:text-brand-700 cursor-pointer">
                            </div>
                            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase">Medical Examination Form</label>
                                <?php if (!empty($doc_medical_form)): ?>
                                    <a href="../<?= htmlspecialchars($doc_medical_form) ?>" target="_blank" class="text-[11px] font-semibold text-brand-700 underline flex items-center gap-1"><i class="fa-solid fa-file-pdf text-rose-500"></i> View Medical Form</a>
                                <?php endif; ?>
                                <input type="file" name="doc_medical_form_file" accept=".pdf" class="w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[10px] file:bg-brand-50 file:text-brand-700 cursor-pointer">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 6: HERO SLIDER IMAGES (Super Admin Only) -->
                <?php if ($canAllSettings): ?>
                <div id="tab-slider" class="tab-content hidden space-y-6">
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-6">
                        <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                            <span class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg">
                                <i class="fa-solid fa-images"></i>
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Hero Section Background Slider Images</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Configure cycling slideshow images displayed behind the welcome headline on the public admissions portal (auto-fading every 5 seconds).</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <?php for($i = 1; $i <= 4; $i++): 
                                $currImg = $current_slider_images[$i - 1] ?? '';
                            ?>
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-800 uppercase flex items-center gap-1.5">
                                        <i class="fa-regular fa-image text-amber-600"></i> Slide Image <?= $i ?>
                                    </span>
                                    <?php if(!empty($currImg)): ?>
                                        <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Active</span>
                                    <?php else: ?>
                                        <span class="text-[10px] text-slate-400">Optional</span>
                                    <?php endif; ?>
                                </div>

                                <?php if(!empty($currImg)): ?>
                                <div class="h-28 w-full rounded-lg overflow-hidden border border-slate-200 bg-slate-900 relative">
                                    <img src="../<?= htmlspecialchars(ltrim($currImg, './')) ?>" alt="Slide <?= $i ?>" class="w-full h-full object-cover">
                                    <span class="absolute bottom-1 right-2 text-[10px] text-white/90 bg-black/60 px-2 py-0.5 rounded font-mono truncate max-w-[200px]"><?= htmlspecialchars($currImg) ?></span>
                                </div>
                                <?php endif; ?>

                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Image Relative Path / URL</label>
                                    <input type="text" name="slider_image_url_<?= $i ?>" value="<?= htmlspecialchars($currImg) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs font-mono text-slate-800 bg-white" placeholder="images/campus.png or https://...">
                                </div>

                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Or Upload Replacement Image File</label>
                                    <input type="file" name="slider_image_file_<?= $i ?>" accept="image/png,image/jpeg,image/webp" class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200 cursor-pointer">
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>

                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900 flex items-center gap-2">
                            <i class="fa-solid fa-circle-info text-amber-600 text-base"></i>
                            <span>The public page hero welcome section will automatically cycle through all configured slider images with a dark readability tint and smooth CSS fade effect.</span>
                        </div>
                    </div>
                </div>

                <!-- TAB 7: SECURITY & MFA CONTROLS (Super Admin Only) -->
                <div id="tab-security" class="tab-content hidden space-y-6">
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-6">
                        <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                            <span class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                                <i class="fa-solid fa-shield-halved"></i>
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Portal Security & Anti-Bot Verification</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Protect application forms against automated spambots and enforce Staff Two-Factor Authentication.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Cloudflare Turnstile -->
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                                <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="w-7 h-7 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center text-xs">
                                            <i class="fa-brands fa-cloudflare text-sm"></i>
                                        </span>
                                        <span class="text-xs font-bold text-slate-800 uppercase">Cloudflare Turnstile</span>
                                    </div>
                                    <select name="cf_turnstile_enabled" class="px-2.5 py-1 text-xs font-bold rounded-lg border border-slate-200 bg-white">
                                        <option value="0" <?= ($cf_turnstile_enabled !== '1') ? 'selected' : '' ?>>Disabled</option>
                                        <option value="1" <?= ($cf_turnstile_enabled === '1') ? 'selected' : '' ?>>Enabled</option>
                                    </select>
                                </div>
                                <p class="text-[11px] text-slate-500 leading-relaxed">
                                    Protects the public online application form (`apply.php`) from bots with zero-friction Turnstile challenges.
                                </p>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Turnstile Site Key</label>
                                    <input type="text" name="cf_turnstile_site_key" value="<?= htmlspecialchars($cf_turnstile_site_key) ?>" class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-mono text-slate-800 bg-white" placeholder="0x4AAAAAA...">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Turnstile Secret Key</label>
                                    <input type="password" name="cf_turnstile_secret_key" value="<?= htmlspecialchars($cf_turnstile_secret_key) ?>" class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-mono text-slate-800 bg-white" placeholder="0x4AAAAAA...">
                                </div>
                            </div>

                            <!-- Staff MFA -->
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                                <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="w-7 h-7 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center text-xs">
                                            <i class="fa-solid fa-key text-xs"></i>
                                        </span>
                                        <span class="text-xs font-bold text-slate-800 uppercase">Staff Two-Factor Authentication</span>
                                    </div>
                                    <select name="staff_mfa_enabled" class="px-2.5 py-1 text-xs font-bold rounded-lg border border-slate-200 bg-white">
                                        <option value="1" <?= ($staff_mfa_enabled !== '0') ? 'selected' : '' ?>>Enabled (OTP via Email)</option>
                                        <option value="0" <?= ($staff_mfa_enabled === '0') ? 'selected' : '' ?>>Disabled</option>
                                    </select>
                                </div>
                                <p class="text-[11px] text-slate-500 leading-relaxed">
                                    Requires all administrative and admissions registry staff to enter a temporary 6-digit passcode sent to their registered staff email address upon login.
                                </p>
                                <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-[11px] text-blue-900 space-y-1">
                                    <span class="font-bold block"><i class="fa-solid fa-circle-check text-blue-600 mr-1"></i> Security Enforced</span>
                                    <span>Passcodes are single-use and expire within 15 minutes of issuance. Inactive sessions automatically terminate after 20 minutes.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- BOTTOM SAVE ACTION -->
                <div class="flex items-center justify-end gap-3 pt-4">
                    <a href="dashboard.php" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2.5 bg-brand-700 hover:bg-brand-800 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> Save System Settings
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
    // Mobile Sidebar Drawer Toggle
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

    // Tab Switching Logic
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
        document.querySelectorAll('.tab-pill').forEach(p => {
            p.classList.remove('bg-brand-700', 'text-white', 'shadow-sm');
            p.classList.add('bg-slate-100', 'text-slate-600');
        });

        const activeContent = document.getElementById('tab-' + tabId);
        const activePill = document.getElementById('pill-' + tabId);
        if (activeContent) activeContent.classList.remove('hidden');
        if (activePill) {
            activePill.classList.remove('bg-slate-100', 'text-slate-600');
            activePill.classList.add('bg-brand-700', 'text-white', 'shadow-sm');
        }
        window.location.hash = tabId;
        const tabInput = document.getElementById('activeTabInput');
        if (tabInput) {
            tabInput.value = tabId;
        }
    }

    // Auto load tab from URL hash if available or preserved POST tab
    window.addEventListener('DOMContentLoaded', () => {
        const postTab = '<?= !empty($_POST['active_tab']) ? htmlspecialchars($_POST['active_tab']) : "" ?>';
        const hash = window.location.hash.replace('#', '');
        const defaultTab = '<?= $canAllSettings ? "profile" : "admissions" ?>';
        const targetTab = hash || postTab || defaultTab;
        if (targetTab && document.getElementById('tab-' + targetTab)) {
            switchTab(targetTab);
        } else {
            switchTab(defaultTab);
        }
    });

    // Sync academic year across Branding & Admissions tabs
    document.addEventListener('input', function(e) {
        if (e.target && e.target.classList.contains('sync-academic-year')) {
            document.querySelectorAll('.sync-academic-year').forEach(el => {
                if (el !== e.target) el.value = e.target.value;
            });
        }
    });

    const settingsForm = document.getElementById('mainSettingsForm');
    if (settingsForm) {
        settingsForm.addEventListener('submit', function() {
            const brandVal = document.getElementById('academic_year_branding')?.value;
            const admInput = document.getElementById('academic_year_admissions');
            if (brandVal && admInput) {
                admInput.value = brandVal;
            }
            const activeTab = window.location.hash.replace('#', '') || '<?= $canAllSettings ? "profile" : "admissions" ?>';
            const tabInput = document.getElementById('activeTabInput');
            if (tabInput) {
                tabInput.value = activeTab;
            }
        });
    }

    // Logo Selection Preview
    function previewSelectedLogo(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const prev = document.getElementById('currentLogoPreview');
                if (prev) prev.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }

    // Currency Formatting
    function formatCurrency(val) {
        let clean = val.toString().replace(/[^\d]/g, '');
        if (!clean) return '';
        return clean.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function updateFeeTotals() {
        ['s1', 's2', 's3', 's4', 's5', 's6'].forEach(cls => {
            let total = 0;
            document.querySelectorAll(`.fee-calc[data-class="${cls}"]`).forEach(input => {
                let val = parseInt(input.value.replace(/[^\d]/g, '') || 0, 10);
                total += val;
            });
            const preview = document.getElementById(`total_${cls}_preview`);
            if (preview) {
                preview.textContent = 'UGX ' + formatCurrency(total);
            }
        });
    }

    // ========================================================
    // Flexible Fees Structure Matrix Editor (2026 Format & Custom)
    // ========================================================
    let feesMatrixData = <?= json_encode($currentFeesMatrix, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (!feesMatrixData || !feesMatrixData.columns || !feesMatrixData.rows) {
        feesMatrixData = {
            title: "Fees structure for <?= htmlspecialchars($academic_year) ?>",
            columns: [
                { id: "admission_fee",    label: "ADMISSION FEE",           sub: "(paid once)" },
                { id: "uniforms",         label: "UNIFORMS",                sub: "(paid once)" },
                { id: "vocational",       label: "VOCATIONAL SKILLS",       sub: "(termly)" },
                { id: "tuition_boarding", label: "TUITION AND BOARDING",   sub: "(termly)" },
                { id: "tuition_day",      label: "TUITION FOR DAY SCHOLARS",sub: "(termly)" },
                { id: "functional",       label: "FUNCTIONAL FEES",         sub: "(termly)" }
            ],
            rows: [
                { class: "S.1", admission_fee: "100,000/=", uniforms: "400,000/=", vocational: "100,000/=", tuition_boarding: "1,265,000/=", tuition_day: "750,000/=", functional: "150,000/=" },
                { class: "S.2", admission_fee: "100,000/=", uniforms: "400,000/=", vocational: "100,000/=", tuition_boarding: "1,265,000/=", tuition_day: "750,000/=", functional: "100,000/=" },
                { class: "S.3", admission_fee: "100,000/=", uniforms: "400,000/=", vocational: "100,000/=", tuition_boarding: "1,265,000/=", tuition_day: "750,000/=", functional: "100,000/=" },
                { class: "S.4", admission_fee: "100,000/=", uniforms: "400,000/=", vocational: "", tuition_boarding: "2,065,000/=", tuition_day: "750,000/=", functional: "100,000/=" },
                { class: "S.5", admission_fee: "100,000/=", uniforms: "400,000/=", vocational: "100,000/=", tuition_boarding: "1,265,000/=", tuition_day: "750,000/=", functional: "150,000/=" },
                { class: "S.6", admission_fee: "100,000/=", uniforms: "400,000/=", vocational: "", tuition_boarding: "1,965,000/=", tuition_day: "750,000/=", functional: "100,000/=" }
            ]
        };
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderFeesMatrixEditor() {
        const tableHead = document.getElementById('feesMatrixTableHead');
        const tableBody = document.getElementById('feesMatrixTableBody');
        if (!tableHead || !tableBody) return;

        let headHtml = '<tr class="bg-slate-100 border-b border-slate-300 text-slate-800 text-[11px] font-bold uppercase">';
        headHtml += '<th class="p-3 border-r border-slate-300 text-center w-24">CLASS</th>';

        feesMatrixData.columns.forEach((col, cIdx) => {
            headHtml += `<th class="p-2 border-r border-slate-300 text-center relative min-w-[150px]">
                <div class="flex items-center justify-between gap-1 mb-1">
                    <input type="text" value="${escapeHtml(col.label)}" onchange="updateColProp(${cIdx}, 'label', this.value)" class="w-full text-center text-[11px] font-black uppercase text-slate-900 border border-slate-200 rounded px-1.5 py-1 bg-white focus:border-brand-700 focus:outline-none" placeholder="COL TITLE">
                    <button type="button" onclick="removeFeeColumn(${cIdx})" title="Delete Column" class="text-slate-400 hover:text-rose-600 p-1 text-xs transition"><i class="fa-solid fa-trash-can"></i></button>
                </div>
                <input type="text" value="${escapeHtml(col.sub || '')}" onchange="updateColProp(${cIdx}, 'sub', this.value)" class="w-full text-center text-[10px] text-slate-500 lowercase border border-slate-200 rounded px-1 py-0.5 bg-white/90 focus:border-brand-700 focus:outline-none" placeholder="(frequency)">
            </th>`;
        });
        headHtml += '<th class="p-2 text-center w-14">ACTION</th></tr>';
        tableHead.innerHTML = headHtml;

        let bodyHtml = '';
        feesMatrixData.rows.forEach((row, rIdx) => {
            bodyHtml += `<tr class="hover:bg-slate-50 border-b border-slate-200 transition">`;
            bodyHtml += `<td class="p-2 border-r border-slate-300 font-bold text-center text-slate-900 bg-slate-50/80">
                <input type="text" value="${escapeHtml(row.class)}" onchange="updateRowClass(${rIdx}, this.value)" class="w-20 text-center font-bold text-xs uppercase text-slate-900 border border-slate-200 rounded px-2 py-1 bg-white focus:border-brand-700 focus:outline-none">
            </td>`;

            feesMatrixData.columns.forEach(col => {
                const val = row[col.id] !== undefined ? row[col.id] : '';
                bodyHtml += `<td class="p-2 border-r border-slate-300 text-center">
                    <input type="text" value="${escapeHtml(val)}" oninput="updateRowCell(${rIdx}, '${col.id}', this.value)" class="w-full text-center font-mono font-medium text-xs text-slate-800 border border-slate-200 rounded px-2 py-1 bg-white hover:border-slate-300 focus:border-brand-700 focus:outline-none" placeholder="-">
                </td>`;
            });

            bodyHtml += `<td class="p-2 text-center">
                <button type="button" onclick="removeFeeRow(${rIdx})" title="Delete Class Row" class="w-7 h-7 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition inline-flex items-center justify-center text-xs">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>`;
            bodyHtml += `</tr>`;
        });
        tableBody.innerHTML = bodyHtml;

        syncFeesMatrixHidden();
        renderFeesMatrixPreview();
    }

    function updateColProp(cIdx, prop, val) {
        if (feesMatrixData.columns[cIdx]) {
            feesMatrixData.columns[cIdx][prop] = val.trim();
            syncFeesMatrixHidden();
            renderFeesMatrixPreview();
        }
    }

    function updateRowClass(rIdx, val) {
        if (feesMatrixData.rows[rIdx]) {
            feesMatrixData.rows[rIdx].class = val.trim().toUpperCase();
            syncFeesMatrixHidden();
            renderFeesMatrixPreview();
        }
    }

    function updateRowCell(rIdx, colId, val) {
        if (feesMatrixData.rows[rIdx]) {
            feesMatrixData.rows[rIdx][colId] = val.trim();
            syncFeesMatrixHidden();
            renderFeesMatrixPreview();
        }
    }

    function updateFeesMatrixTitle(val) {
        feesMatrixData.title = val.trim();
        syncFeesMatrixHidden();
        renderFeesMatrixPreview();
    }

    function addFeeColumn() {
        const label = prompt("Enter Column Header Name (e.g. VOCATIONAL SKILLS, UNIFORMS, SWIMMING):");
        if (!label || !label.trim()) return;
        const sub = prompt("Enter Payment Frequency / Subtitle (e.g. (termly), (paid once), or leave blank):", "(termly)");
        const colId = label.trim().toLowerCase().replace(/[^a-z0-9]+/g, '_');
        feesMatrixData.columns.push({
            id: colId,
            label: label.trim().toUpperCase(),
            sub: sub ? sub.trim() : ''
        });
        feesMatrixData.rows.forEach(r => {
            if (r[colId] === undefined) r[colId] = '';
        });
        renderFeesMatrixEditor();
    }

    function removeFeeColumn(cIdx) {
        if (feesMatrixData.columns.length <= 1) {
            alert("At least one fee column is required.");
            return;
        }
        const col = feesMatrixData.columns[cIdx];
        if (confirm(`Are you sure you want to delete column "${col.label}"?`)) {
            const colId = col.id;
            feesMatrixData.columns.splice(cIdx, 1);
            feesMatrixData.rows.forEach(r => {
                delete r[colId];
            });
            renderFeesMatrixEditor();
        }
    }

    function addClassRow() {
        const clsName = prompt("Enter Class Name (e.g. S.7, P.7, Pre-Primary):");
        if (!clsName || !clsName.trim()) return;
        const newRow = { class: clsName.trim().toUpperCase() };
        feesMatrixData.columns.forEach(c => {
            newRow[c.id] = '';
        });
        feesMatrixData.rows.push(newRow);
        renderFeesMatrixEditor();
    }

    function removeFeeRow(rIdx) {
        if (feesMatrixData.rows.length <= 1) {
            alert("At least one class row is required.");
            return;
        }
        const r = feesMatrixData.rows[rIdx];
        if (confirm(`Are you sure you want to delete row "${r.class}"?`)) {
            feesMatrixData.rows.splice(rIdx, 1);
            renderFeesMatrixEditor();
        }
    }

    function resetDefaultFeesMatrix() {
        if (confirm("Reset fees structure matrix back to official standards? (Matches attached schedule)")) {
            feesMatrixData = {
                title: "Fees structure for <?= htmlspecialchars($academic_year) ?>",
                columns: [
                    { id: "admission_fee",    label: "ADMISSION FEE",           sub: "(paid once)" },
                    { id: "uniforms",         label: "UNIFORMS",                sub: "(paid once)" },
                    { id: "vocational",       label: "VOCATIONAL SKILLS",       sub: "(termly)" },
                    { id: "tuition_boarding", label: "TUITION AND BOARDING",   sub: "(termly)" },
                    { id: "tuition_day",      label: "TUITION FOR DAY SCHOLARS",sub: "(termly)" },
                    { id: "functional",       label: "FUNCTIONAL FEES",         sub: "(termly)" }
                ],
                rows: [
                    { class: "S.1", admission_fee: "100,000/=", uniforms: "400,000/=", vocational: "100,000/=", tuition_boarding: "1,265,000/=", tuition_day: "750,000/=", functional: "150,000/=" },
                    { class: "S.2", admission_fee: "100,000/=", uniforms: "400,000/=", vocational: "100,000/=", tuition_boarding: "1,265,000/=", tuition_day: "750,000/=", functional: "100,000/=" },
                    { class: "S.3", admission_fee: "100,000/=", uniforms: "400,000/=", vocational: "100,000/=", tuition_boarding: "1,265,000/=", tuition_day: "750,000/=", functional: "100,000/=" },
                    { class: "S.4", admission_fee: "100,000/=", uniforms: "400,000/=", vocational: "", tuition_boarding: "2,065,000/=", tuition_day: "750,000/=", functional: "100,000/=" },
                    { class: "S.5", admission_fee: "100,000/=", uniforms: "400,000/=", vocational: "100,000/=", tuition_boarding: "1,265,000/=", tuition_day: "750,000/=", functional: "150,000/=" },
                    { class: "S.6", admission_fee: "100,000/=", uniforms: "400,000/=", vocational: "", tuition_boarding: "1,965,000/=", tuition_day: "750,000/=", functional: "100,000/=" }
                ]
            };
            const titleInput = document.getElementById('feesMatrixTitleInput');
            if (titleInput) titleInput.value = feesMatrixData.title;
            renderFeesMatrixEditor();
        }
    }

    function toggleJsonFeesEditor() {
        const box = document.getElementById('feesJsonEditorContainer');
        if (box) box.classList.toggle('hidden');
    }

    function applyFeesJson() {
        const textarea = document.getElementById('feesJsonTextarea');
        try {
            const parsed = JSON.parse(textarea.value);
            if (parsed.columns && parsed.rows) {
                feesMatrixData = parsed;
                const titleInput = document.getElementById('feesMatrixTitleInput');
                if (titleInput && parsed.title) titleInput.value = parsed.title;
                renderFeesMatrixEditor();
                alert("Fees matrix JSON applied successfully!");
            } else {
                alert("Invalid matrix format: columns and rows arrays required.");
            }
        } catch (e) {
            alert("JSON Syntax Error: " + e.message);
        }
    }

    function syncFeesMatrixHidden() {
        const jsonStr = JSON.stringify(feesMatrixData);
        const hiddenInput = document.getElementById('fees_structure_matrix_input');
        if (hiddenInput) hiddenInput.value = jsonStr;

        const jsonArea = document.getElementById('feesJsonTextarea');
        if (jsonArea && document.activeElement !== jsonArea) {
            jsonArea.value = JSON.stringify(feesMatrixData, null, 2);
        }

        // Sync legacy inputs in background
        if (feesMatrixData && feesMatrixData.rows) {
            feesMatrixData.rows.forEach(r => {
                const rawCls = (r.class || '').replace(/[\.\s\-]/g, '').toLowerCase();
                if (['s1', 's2', 's3', 's4', 's5', 's6'].includes(rawCls)) {
                    const tInp = document.getElementById(`hidden_fees_${rawCls}_tuition`);
                    const aInp = document.getElementById(`hidden_fees_${rawCls}_admission`);
                    const fInp = document.getElementById(`hidden_fees_${rawCls}_functional`);
                    if (tInp) tInp.value = (r.tuition_boarding || r.tuition_day || '').replace(/[^\d]/g, '');
                    if (aInp) aInp.value = (r.admission_fee || '').replace(/[^\d]/g, '');
                    if (fInp) fInp.value = (r.functional || '').replace(/[^\d]/g, '');
                }
            });
        }
    }

    function renderFeesMatrixPreview() {
        const previewBox = document.getElementById('feesMatrixLivePreview');
        if (!previewBox) return;

        let html = `<div class="mb-2"><span class="text-xs font-black text-slate-800 uppercase tracking-wide">${escapeHtml(feesMatrixData.title || 'Fees structure')}</span></div>`;
        html += '<table class="w-full text-xs text-left border-collapse min-w-[700px] bg-white rounded-lg border border-slate-300 overflow-hidden">';
        html += '<thead><tr class="bg-slate-100/90 border-b border-slate-300 text-[10.5px] font-bold text-slate-800 uppercase">';
        html += '<th class="p-2.5 border-r border-slate-300 text-center w-20">CLASS</th>';
        feesMatrixData.columns.forEach(col => {
            html += `<th class="p-2.5 border-r border-slate-300 text-center last:border-r-0">
                <div class="font-extrabold text-slate-900 leading-tight">${escapeHtml(col.label)}</div>
                ${col.sub ? `<div class="text-[9px] font-normal text-slate-500 lowercase mt-0.5">${escapeHtml(col.sub)}</div>` : ''}
            </th>`;
        });
        html += '</tr></thead><tbody class="divide-y divide-slate-200 font-mono text-xs">';
        feesMatrixData.rows.forEach(r => {
            html += `<tr class="hover:bg-brand-50/30 transition">`;
            html += `<td class="p-2.5 border-r border-slate-300 font-bold text-center text-slate-900 bg-slate-50 font-sans">${escapeHtml(r.class)}</td>`;
            feesMatrixData.columns.forEach(col => {
                const val = r[col.id] || '';
                html += `<td class="p-2.5 border-r border-slate-300 text-right pr-4 text-slate-700 last:border-r-0 font-medium tabular-nums whitespace-nowrap">${val ? escapeHtml(val) : '<div class="text-center font-sans text-slate-300 font-light">-</div>'}</td>`;
            });
            html += `</tr>`;
        });
        html += '</tbody></table>';
        previewBox.innerHTML = html;
    }

    document.addEventListener('DOMContentLoaded', function() {
        renderFeesMatrixEditor();
    });

    document.querySelectorAll('.currency-input').forEach(input => {
        if (input.value) input.value = formatCurrency(input.value);
        input.addEventListener('input', function() {
            let cursor = this.selectionStart;
            let originalLen = this.value.length;
            this.value = formatCurrency(this.value);
            let newLen = this.value.length;
            cursor = cursor + (newLen - originalLen);
            this.setSelectionRange(cursor, cursor);
            updateFeeTotals();
        });
    });

    // Dynamic Theme Color Pickers
    const primaryPicker = document.getElementById('primaryColorPicker');
    const primaryInput  = document.getElementById('primaryColorInput');
    const accentPicker  = document.getElementById('accentColorPicker');
    const accentInput   = document.getElementById('accentColorInput');

    if (primaryPicker && primaryInput) {
        primaryPicker.addEventListener('input', (e) => primaryInput.value = e.target.value.toUpperCase());
        primaryInput.addEventListener('input', (e) => {
            if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) primaryPicker.value = e.target.value;
        });
    }
    if (accentPicker && accentInput) {
        accentPicker.addEventListener('input', (e) => accentInput.value = e.target.value.toUpperCase());
        accentInput.addEventListener('input', (e) => {
            if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) accentPicker.value = e.target.value;
        });
    }

    // SMTP Test Runner
    function runSmtpTest() {
        const btn = document.getElementById('smtpTestBtn');
        const resBox = document.getElementById('smtpTestResult');
        const recipient = document.getElementById('testEmailRecipient').value.trim();

        if (!recipient) {
            alert('Please provide a test recipient email address.');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Connecting...';
        resBox.classList.add('hidden');
        resBox.className = 'p-3.5 rounded-xl text-xs font-semibold';

        const formData = new FormData();
        formData.append('smtp_host', document.getElementById('smtpHostInput').value);
        formData.append('smtp_port', document.getElementById('smtpPortInput').value);
        formData.append('smtp_encryption', document.getElementById('smtpEncInput').value);
        formData.append('smtp_username', document.getElementById('smtpUserInput').value);
        formData.append('smtp_password', document.getElementById('smtpPassInput').value);
        formData.append('smtp_from_email', document.getElementById('smtpFromEmailInput').value);
        formData.append('smtp_from_name', document.getElementById('smtpFromNameInput').value);
        formData.append('test_recipient', recipient);

        fetch('test_smtp.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-plug"></i> Test SMTP Connection';
            resBox.classList.remove('hidden');

            if (data.success) {
                resBox.classList.add('bg-emerald-50', 'border', 'border-emerald-200', 'text-emerald-800');
                resBox.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-600 mr-1.5"></i> ' + data.message;
            } else {
                resBox.classList.add('bg-rose-50', 'border', 'border-rose-200', 'text-rose-800');
                resBox.innerHTML = '<i class="fa-solid fa-circle-xmark text-rose-600 mr-1.5"></i> ' + data.message;
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-plug"></i> Test SMTP Connection';
            resBox.classList.remove('hidden');
            resBox.classList.add('bg-rose-50', 'border', 'border-rose-200', 'text-rose-800');
            resBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-rose-600 mr-1.5"></i> AJAX Network Error: ' + err.message;
        });
    }

    function resetDefaultCombinations() {
        const defaultObj = {
            "Sciences": {
                "PCM/ICT": "PCM/ICT (Physics, Chemistry, Mathematics & ICT)",
                "PCM/SMT": "PCM/SMT (Physics, Chemistry, Mathematics & Sub-Math)",
                "PCB/ICT": "PCB/ICT (Physics, Chemistry, Biology & ICT)",
                "PCB/SMT": "PCB/SMT (Physics, Chemistry, Biology & Sub-Math)",
                "BCM/ICT": "BCM/ICT (Biology, Chemistry, Mathematics & ICT)",
                "BCM/SMT": "BCM/SMT (Biology, Chemistry, Mathematics & Sub-Math)",
                "PEM/ICT": "PEM/ICT (Physics, Economics, Mathematics & ICT)",
                "PEM/SMT": "PEM/SMT (Physics, Economics, Mathematics & Sub-Math)",
                "MEG/ICT": "MEG/ICT (Mathematics, Economics, Geography & ICT)",
                "MEG/SMT": "MEG/SMT (Mathematics, Economics, Geography & Sub-Math)",
                "BCG/ICT": "BCG/ICT (Biology, Chemistry, Geography & ICT)",
                "MAG/ICT": "MAG/ICT (Mathematics, Agriculture, Geography & ICT)"
            },
            "Arts & Humanities": {
                "HEG/ICT": "HEG/ICT (History, Economics, Geography & ICT)",
                "HEG/SMT": "HEG/SMT (History, Economics, Geography & Sub-Math)",
                "HEL/ICT": "HEL/ICT (History, Economics, Literature & ICT)",
                "HEL/SMT": "HEL/SMT (History, Economics, Literature & Sub-Math)",
                "HLD/ICT": "HLD/ICT (History, Literature, Divinity & ICT)",
                "DEG/ICT": "DEG/ICT (Divinity, Economics, Geography & ICT)",
                "HED/ICT": "HED/ICT (History, Economics, Divinity & ICT)",
                "LEG/ICT": "LEG/ICT (Literature, Economics, Geography & ICT)",
                "GEA/ICT": "GEA/ICT (Geography, Entrepreneurship, Art & ICT)",
                "MEA/ICT": "MEA/ICT (Mathematics, Economics, Art & ICT)"
            }
        };
        const textarea = document.getElementById('aLevelCombsTextarea');
        if (textarea) {
            textarea.value = JSON.stringify(defaultObj, null, 4);
        }
    }

    // Profile Dropdown Hover Support
    const profileContainer = document.getElementById('profileDropdownBtn')?.parentElement;
    if (profileContainer) {
        let hideTimer;
        profileContainer.addEventListener('mouseenter', function() {
            clearTimeout(hideTimer);
            document.getElementById('profileDropdown')?.classList.remove('hidden');
        });
        profileContainer.addEventListener('mouseleave', function() {
            hideTimer = setTimeout(() => {
                document.getElementById('profileDropdown')?.classList.add('hidden');
            }, 200);
        });
    }

    // Student Number Format Preview
    function updateStudentNumberPreview() {
        const input = document.getElementById('studentNumberFormatInput');
        const badge = document.getElementById('studentNumberPreviewBadge');
        if (!input || !badge) return;
        let format = input.value || '{YY}{CLASS}{CODE}{SERIAL}';
        let sample = format
            .replace(/\{YY\}/g, '26')
            .replace(/\{YYYY\}/g, '2026')
            .replace(/\{CLASS\}/g, '05')
            .replace(/\{CLASS_NAME\}/g, 'S5')
            .replace(/\{CODE\}/g, '011')
            .replace(/\{SERIAL\}/g, '01')
            .replace(/\{SERIAL2\}/g, '01')
            .replace(/\{SERIAL3\}/g, '001')
            .replace(/\{SERIAL4\}/g, '0001')
            .replace(/\{ID\}/g, '1');
        badge.textContent = sample;
    }
    function setStudentNumberPreset(val) {
        const input = document.getElementById('studentNumberFormatInput');
        if (input) {
            input.value = val;
            updateStudentNumberPreview();
        }
    }

    // Profile Dropdown
    function toggleDropdown() {
        const drop = document.getElementById('profileDropdown');
        if (drop) drop.classList.toggle('hidden');
    }
    window.addEventListener('click', function(e) {
        const btn = document.getElementById('profileDropdownBtn');
        const drop = document.getElementById('profileDropdown');
        if (btn && drop && !btn.contains(e.target) && !drop.contains(e.target)) {
            drop.classList.add('hidden');
        }
    });

    // Floating Back to Top Button
    const bttBtn = document.getElementById('backToTopBtn');
    if (bttBtn) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 250) {
                bttBtn.classList.remove('opacity-0', 'pointer-events-none');
                bttBtn.classList.add('opacity-100', 'pointer-events-auto');
            } else {
                bttBtn.classList.add('opacity-0', 'pointer-events-none');
                bttBtn.classList.remove('opacity-100', 'pointer-events-auto');
            }
        });
        bttBtn.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
    </script>

    <!-- Floating Back-to-Top Button -->
    <button id="backToTopBtn" type="button" aria-label="Back to Top"
        class="fixed bottom-6 right-6 z-50 p-3.5 rounded-full text-white shadow-xl opacity-0 pointer-events-none transition-all duration-300 hover:scale-110 focus:outline-none"
        style="background-color: <?= $primary_color ?>;">
        <i class="fa-solid fa-arrow-up text-sm"></i>
    </button>
</body>
</html>
