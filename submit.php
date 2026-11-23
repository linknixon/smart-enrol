<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require './config/db.php';
require './form-one/includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method");
}

// Ensure uploads folders exist
@mkdir('uploads/passports', 0777, true);
@mkdir('uploads/results', 0777, true);
@mkdir('uploads/parent_ids', 0777, true);

$class = $_POST['class_of_entry'] ?? '';
if (!in_array($class, ['S1', 'S2', 'S3', 'S4', 'S5', 'S6'])) {
    die("Invalid class of entry");
}

// Server-side intake deadline enforcement
$studentName = trim(($_POST['firstname'] ?? '') . ' ' . ($_POST['surname'] ?? ''));

// Cloudflare Turnstile Anti-Bot Verification
$cfEnabled = getSetting($pdo, 'cf_turnstile_enabled', '0');
$cfSecret  = getSetting($pdo, 'cf_turnstile_secret_key', '');
if ($cfEnabled === '1' && !empty($cfSecret)) {
    $cfToken = $_POST['cf-turnstile-response'] ?? '';
    if (empty($cfToken)) {
        logActivity($pdo, 'Public Portal', 'Submission Blocked (Turnstile Missing)', "Turnstile token missing for {$studentName}");
        die("Security Verification Failed: Cloudflare anti-bot verification was not completed. Please verify you are human.");
    }
    
    // Verify token with Cloudflare
    $verifyUrl = "https://challenges.cloudflare.com/turnstile/v0/siteverify";
    $postData = http_build_query([
        'secret' => $cfSecret,
        'response' => $cfToken,
        'remoteip' => getClientIp()
    ]);
    
    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'content' => $postData,
            'timeout' => 10
        ]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents($verifyUrl, false, $context);
    if ($response) {
        $result = json_decode($response, true);
        if (!$result || empty($result['success'])) {
            logActivity($pdo, 'Public Portal', 'Submission Blocked (Turnstile Failed)', "Turnstile verification failed for {$studentName}");
            die("Security Verification Failed: Anti-bot challenge could not be validated. Please refresh and try again.");
        }
    }
}

// Server-side intake deadline enforcement
$intakeStatus = getAdmissionStatus($pdo, $class);
if (!$intakeStatus['is_open']) {
    $classLabel = ($class === 'S1') ? 'Senior One (S1)' : (($class === 'S5') ? 'Senior Five (S5)' : "Transfers ({$class})");
    logActivity($pdo, 'Public Portal', 'Submission Blocked (Intake Closed)', "Class {$class} intake closed for candidate {$studentName}");
    if ($intakeStatus['status'] === 'closed') {
        die("Online admissions for {$classLabel} are currently closed. The application deadline passed on {$intakeStatus['formatted_end']}.");
    } else {
        die("Online admissions for {$classLabel} have not opened yet. Applications begin on {$intakeStatus['formatted_start']}.");
    }
}

// Enforce School Gender Classification
$schoolGender = getSchoolGenderType($pdo);
$applicantSex = trim($_POST['sex'] ?? '');
if ($schoolGender === 'boys' && $applicantSex !== 'Male') {
    logActivity($pdo, 'Public Portal', 'Submission Blocked (Gender Ineligible)', "Gender {$applicantSex} rejected for {$schoolGender} school for candidate {$studentName}");
    die("Ineligible Application: This institution is classified as a Boys-Only school. Only male candidates are eligible.");
} elseif ($schoolGender === 'girls' && $applicantSex !== 'Female') {
    logActivity($pdo, 'Public Portal', 'Submission Blocked (Gender Ineligible)', "Gender {$applicantSex} rejected for {$schoolGender} school for candidate {$studentName}");
    die("Ineligible Application: This institution is classified as a Girls-Only school. Only female candidates are eligible.");
}

// Validate Uganda NIN format (14 characters starting with CM, CF, RM, RF)
$parent_nin = strtoupper(trim($_POST['parent_nin'] ?? ''));
if (!preg_match('/^(CM|CF|RM|RF)[0-9A-Z]{12}$/', $parent_nin)) {
    logActivity($pdo, 'Public Portal', 'Submission Blocked (Invalid NIN)', "Invalid NIN '{$parent_nin}' for candidate {$studentName}");
    die("Invalid NIN: Please provide a valid Ugandan 14-character National Identification Number (e.g. CM89012345ABCD).");
}

// Validate Uganda Telephone Number
$parent_phone = trim($_POST['parent_phone'] ?? '');
if (!preg_match('/^(?:\+256|256|0)[37][0-9]{8}$/', $parent_phone)) {
    logActivity($pdo, 'Public Portal', 'Submission Blocked (Invalid Phone)', "Invalid phone '{$parent_phone}' for candidate {$studentName}");
    die("Invalid Phone Number: Please provide a valid Uganda phone number (e.g. 0772123456 or +256772123456).");
}

// 1. Process Passport Photo Upload
if (!isset($_FILES['passport']) || $_FILES['passport']['error'] !== UPLOAD_ERR_OK) {
    logActivity($pdo, 'Public Portal', 'Submission Blocked (Missing Passport)', "Passport photo upload missing for {$studentName}");
    die("Passport photo is required");
}
$passport = 'uploads/passports/' . time() . '_' . basename($_FILES['passport']['name']);
if (!move_uploaded_file($_FILES['passport']['tmp_name'], $passport)) {
    die("Failed to upload passport photo");
}
// Automatically correct camera orientation from phone EXIF data
if (function_exists('correctImageExifOrientation')) {
    correctImageExifOrientation($passport);
}
// Automatically normalize and whiten background for student passport photograph
if (function_exists('processPassportPhotoBackground')) {
    processPassportPhotoBackground($passport);
}

// 2. Process Result Slip/Transcript Upload
$resultSlipInputName = '';
if ($class === 'S1') {
    $resultSlipInputName = 'result_slip_s1';
} elseif ($class === 'S5' || $class === 'S6') {
    $resultSlipInputName = 'result_slip_s5';
} else {
    $resultSlipInputName = 'result_slip_transfer';
}

if (!isset($_FILES[$resultSlipInputName]) || $_FILES[$resultSlipInputName]['error'] !== UPLOAD_ERR_OK) {
    logActivity($pdo, 'Public Portal', 'Submission Blocked (Missing Academic Record)', "Academic attachment missing for {$studentName} ({$class})");
    die("Academic record attachment (official result slip or report card) is required");
}
$resultSlip = 'uploads/results/' . time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '', basename($_FILES[$resultSlipInputName]['name']));
if (!move_uploaded_file($_FILES[$resultSlipInputName]['tmp_name'], $resultSlip)) {
    die("Failed to upload academic record attachment");
}
if (function_exists('correctImageExifOrientation')) {
    correctImageExifOrientation($resultSlip);
}

// 2.B Process Senior Six (S6) Report Card Upload
$s6ReportFile = null;
if ($class === 'S6') {
    if (!isset($_FILES['s6_report_file']) || $_FILES['s6_report_file']['error'] !== UPLOAD_ERR_OK) {
        logActivity($pdo, 'Public Portal', 'Submission Blocked (Missing S5 Report)', "S5 report card missing for {$studentName}");
        die("Official Senior Five (S5) report card / academic transcript is required for Senior Six transfer applications.");
    }
    $allowedRepExts = ['pdf', 'png', 'jpg', 'jpeg'];
    $repExt = strtolower(pathinfo($_FILES['s6_report_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($repExt, $allowedRepExts)) {
        die("Invalid file format for Senior Five report card. Allowed formats: PDF, PNG, JPG, JPEG.");
    }
    $s6ReportFile = 'uploads/results/' . time() . '_s6report_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '', basename($_FILES['s6_report_file']['name']));
    if (!move_uploaded_file($_FILES['s6_report_file']['tmp_name'], $s6ReportFile)) {
        die("Failed to upload Senior Five report card");
    }
    if (function_exists('correctImageExifOrientation')) {
        correctImageExifOrientation($s6ReportFile);
    }
}

// 3. Process Parent / Guardian National ID Document Upload (PDF, PNG, JPG, JPEG)
if (!isset($_FILES['parent_id_file']) || $_FILES['parent_id_file']['error'] !== UPLOAD_ERR_OK) {
    logActivity($pdo, 'Public Portal', 'Submission Blocked (Missing Parent ID)', "Parent ID upload missing for {$studentName}");
    die("Parent/Guardian National ID document upload is required");
}
$allowedIdExts = ['pdf', 'png', 'jpg', 'jpeg'];
$idFileExt = strtolower(pathinfo($_FILES['parent_id_file']['name'], PATHINFO_EXTENSION));
if (!in_array($idFileExt, $allowedIdExts)) {
    die("Invalid file format for Parent National ID. Allowed formats: PDF, PNG, JPG, JPEG.");
}
$parentIdFile = 'uploads/parent_ids/' . time() . '_' . basename($_FILES['parent_id_file']['name']);
if (!move_uploaded_file($_FILES['parent_id_file']['tmp_name'], $parentIdFile)) {
    die("Failed to upload parent National ID document");
}
if (function_exists('correctImageExifOrientation')) {
    correctImageExifOrientation($parentIdFile);
}

// Calculate age from Date of Birth
$dob = $_POST['dob'] ?? '';
$age = 0;
if (!empty($dob)) {
    $age = date_diff(date_create($dob), date_create('today'))->y;
}
if ($age < 9) {
    logActivity($pdo, 'Public Portal', 'Submission Blocked (Age Ineligible)', "Applicant {$studentName} age ({$age}) is below minimum 9 years");
    die("Ineligible Application: Applicant must be at least 9 years of age to apply. Calculated age is {$age} years.");
}

// Define variables based on class of entry
$formerSchool    = '';
$indexNumber     = null;
$english         = null;
$mathematics     = null;
$sst             = null;
$science         = null;
$aggregates      = null;
$division        = null;
$combination     = null;
$transferReason  = null;
$uceProjectWork  = null;
$uceSubjectsJson = null;
$s6FormerSchool  = null;
$competencyScore = 0;

if ($class === 'S1') {
    $formerSchool = $_POST['former_school_s1'] ?? '';
    $indexNumber  = trim($_POST['index_number_s1'] ?? '');
    $english      = $_POST['english'] ?? '';
    $mathematics  = $_POST['mathematics'] ?? '';
    $sst          = $_POST['sst'] ?? '';
    $science      = $_POST['science'] ?? '';
    $aggregates   = isset($_POST['aggregates_s1']) ? (int)$_POST['aggregates_s1'] : 0;
    
    // Capture PLE Class Division
    $divisionRaw  = trim($_POST['division_s1'] ?? '');
    if (!empty($divisionRaw)) {
        if (preg_match('/(?:div(?:ision)?\s*)?([1-4u])/i', $divisionRaw, $dm)) {
            $division = 'Division ' . strtoupper($dm[1]);
        } else {
            $division = $divisionRaw;
        }
    } else {
        $division = ($aggregates <= 12) ? 'Division 1' : (($aggregates <= 23) ? 'Division 2' : (($aggregates <= 29) ? 'Division 3' : (($aggregates <= 34) ? 'Division 4' : 'Division U')));
    }

    // Server-side Passmark Cutoff Enforcement for S1
    $passmarkS1 = getPassMark($pdo, 'S1');
    if ($passmarkS1 === null || $passmarkS1 === '') {
        logActivity($pdo, 'Public Portal', 'Submission Blocked (Pass Mark Not Set)', "Admissions for S1 on hold - cutoff unconfigured");
        die("Admissions for Senior One (S1) are currently on hold because the cutoff pass mark has not been configured by administration.");
    }
    if ($aggregates > (int)$passmarkS1) {
        logActivity($pdo, 'Public Portal', 'Submission Blocked (Cutoff Exceeded)', "Candidate {$studentName} aggregates ({$aggregates}) exceeded cutoff ({$passmarkS1}) for S1");
        die("Application Ineligible: Your PLE aggregates ({$aggregates}) exceed the school's maximum admission pass mark of {$passmarkS1} aggregates for Senior One.");
    }
} elseif ($class === 'S5' || $class === 'S6') {
    $formerSchool   = trim($_POST['former_school_s5'] ?? '');
    $indexNumber    = strtoupper(trim($_POST['index_number_s5'] ?? ''));
    $division       = trim($_POST['division_s5'] ?? ''); // e.g. "Result 1"
    $uceProjectWork = strtoupper(trim($_POST['uce_project_work'] ?? '')); // e.g. "C"
    $combination    = trim($_POST['combination_s5'] ?? '');

    // Descriptors and Point Mappings according to UNEB NLSC Gazette
    $descriptors = [
        'A' => 'Exceptional',
        'B' => 'Outstanding',
        'C' => 'Satisfactory',
        'D' => 'Basic',
        'E' => 'Elementary'
    ];
    $pointsMap = ['A' => 5, 'B' => 4, 'C' => 3, 'D' => 2, 'E' => 1];
    $equivMap  = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5];

    $subjects = [
        'ENG' => ['name' => 'English Language', 'grade' => strtoupper(trim($_POST['uce_eng'] ?? ''))],
        'MAT' => ['name' => 'Mathematics', 'grade' => strtoupper(trim($_POST['uce_mat'] ?? ''))],
        'PHY' => ['name' => 'Physics', 'grade' => strtoupper(trim($_POST['uce_phy'] ?? ''))],
        'CHE' => ['name' => 'Chemistry', 'grade' => strtoupper(trim($_POST['uce_che'] ?? ''))],
        'BIO' => ['name' => 'Biology', 'grade' => strtoupper(trim($_POST['uce_bio'] ?? ''))],
        'HIS' => ['name' => 'History & Political Educ.', 'grade' => strtoupper(trim($_POST['uce_his'] ?? ''))],
        'GEO' => ['name' => 'Geography', 'grade' => strtoupper(trim($_POST['uce_geo'] ?? ''))],
    ];

    // Religious Education (CRE / IRE) is an Elective in NLSC (not compulsory)
    $creGrade = strtoupper(trim($_POST['uce_cre_ire'] ?? ''));
    if (!empty($creGrade)) {
        $subjects['CRE_IRE'] = [
            'name' => 'Religious Education (CRE/IRE)',
            'grade' => $creGrade
        ];
    }

    if (!empty($_POST['uce_elec1_name']) && !empty($_POST['uce_elec1_grade'])) {
        $subjects['ELEC1'] = [
            'name' => trim($_POST['uce_elec1_name']),
            'grade' => strtoupper(trim($_POST['uce_elec1_grade']))
        ];
    }
    if (!empty($_POST['uce_elec2_name']) && !empty($_POST['uce_elec2_grade'])) {
        $subjects['ELEC2'] = [
            'name' => trim($_POST['uce_elec2_name']),
            'grade' => strtoupper(trim($_POST['uce_elec2_grade']))
        ];
    }

    $counts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0];
    $equivList = [];

    foreach ($subjects as $k => &$subj) {
        $g = $subj['grade'];
        if (isset($pointsMap[$g])) {
            $counts[$g]++;
            $competencyScore += $pointsMap[$g];
            $equivList[] = $equivMap[$g];
            $subj['level'] = $descriptors[$g] ?? '';
        }
    }
    unset($subj);

    // Auto-detect A-Level Subject Combination based on performance if not specified
    if (empty($combination)) {
        $matPts = $pointsMap[$subjects['MAT']['grade'] ?? ''] ?? 0;
        $phyPts = $pointsMap[$subjects['PHY']['grade'] ?? ''] ?? 0;
        $chePts = $pointsMap[$subjects['CHE']['grade'] ?? ''] ?? 0;
        $bioPts = $pointsMap[$subjects['BIO']['grade'] ?? ''] ?? 0;
        $hisPts = $pointsMap[$subjects['HIS']['grade'] ?? ''] ?? 0;
        $geoPts = $pointsMap[$subjects['GEO']['grade'] ?? ''] ?? 0;

        if ($matPts >= 3 && $phyPts >= 3 && $chePts >= 3) {
            $combination = 'PCM/ICT';
        } elseif ($bioPts >= 3 && $chePts >= 3 && $matPts >= 3) {
            $combination = 'BCM/ICT';
        } elseif ($bioPts >= 3 && $chePts >= 3 && $phyPts >= 3) {
            $combination = 'PCB/ICT';
        } elseif ($matPts >= 3 && $geoPts >= 3) {
            $combination = 'MEG/ICT';
        } elseif ($hisPts >= 3 && $geoPts >= 3) {
            $combination = 'HEG/ICT';
        } elseif ($hisPts >= 3) {
            $combination = 'HEL/ICT';
        } else {
            $combination = 'HEG/ICT';
        }
    }

    // Compute best 8 equivalent aggregate (A=1, B=2, C=3, D=4, E=5)
    sort($equivList);
    $best8Equiv = array_sum(array_slice($equivList, 0, 8));
    $aggregates = $best8Equiv > 0 ? $best8Equiv : (isset($_POST['aggregates_s5']) ? (int)$_POST['aggregates_s5'] : 0);

    // S6 Transfer Specifics
    if ($class === 'S6') {
        $s6FormerSchool = trim($_POST['s6_former_school'] ?? '');
        $s6Passes       = trim($_POST['s6_principal_passes'] ?? '');
        $s6Reason       = trim($_POST['s6_transfer_reason'] ?? '');
        $transferReason = "S5 Passes: {$s6Passes} | Transfer Reason: {$s6Reason}";
        if (!empty($_POST['division_s6'])) {
            $division = trim($_POST['division_s6']);
        }
        if (isset($_POST['aggregates_s6']) && $_POST['aggregates_s6'] !== '') {
            $aggregates = (int)$_POST['aggregates_s6'];
        }
    }

    // Build JSON payload
    $payload = [
        'curriculum'        => 'NLSC',
        'result_level'      => $division,
        'project_work'      => $uceProjectWork,
        'competency_score'  => $competencyScore,
        'counts'            => $counts,
        'subjects'          => $subjects
    ];
    if ($class === 'S6') {
        $payload['s6_transfer'] = [
            'former_s5_school'    => $s6FormerSchool,
            's5_principal_passes' => $_POST['s6_principal_passes'] ?? '',
            'transfer_reason'     => $_POST['s6_transfer_reason'] ?? '',
            'report_file'         => $s6ReportFile
        ];
    }
    $uceSubjectsJson = json_encode($payload, JSON_UNESCAPED_UNICODE);

    // Server-side Cutoff Enforcement for S5 only (Transfers do NOT have cutoff)
    if ($class === 'S5') {
        $passmarkS5 = getPassMark($pdo, 'S5');
        if ($passmarkS5 === null || $passmarkS5 === '') {
            logActivity($pdo, 'Public Portal', 'Submission Blocked (Pass Mark Not Set)', "Admissions for S5 on hold - cutoff unconfigured");
            die("Admissions for Senior Five (S5) are currently on hold because the cutoff pass mark has not been configured by administration.");
        }
        if ($aggregates > (int)$passmarkS5) {
            logActivity($pdo, 'Public Portal', 'Submission Blocked (Cutoff Exceeded)', "Candidate {$studentName} aggregates ({$aggregates}) exceeded cutoff ({$passmarkS5}) for S5");
            die("Application Ineligible: Your calculated UCE aggregates ({$aggregates}) exceed the school's maximum admission pass mark of {$passmarkS5} aggregates for Senior Five.");
        }
    }
} else {
    // S2, S3, S4 (O-Level Transfers do NOT have cutoff points of admissions)
    $formerSchool   = $_POST['former_school_transfer'] ?? '';
    $division       = trim($_POST['division_transfer'] ?? ($_POST['last_class_attended'] ?? ''));
    $aggregates     = (isset($_POST['aggregates_transfer']) && $_POST['aggregates_transfer'] !== '') ? (int)$_POST['aggregates_transfer'] : null;
    $transferReason = $_POST['transfer_reason'] ?? '';
}

// Dynamic Academic Year & Learner Identification Number (LIN)
$academic_year = getSetting($pdo, 'academic_year', date('Y'));
$lin = strtoupper(trim($_POST['lin'] ?? '')) ?: null;

// Strict Duplicate Applicant Prevention (Blocks duplicate index number system-wide for the academic year; allows same parent for multiple children)
if (!empty($indexNumber)) {
    $dupCheck = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE index_number = ? AND (academic_year = ? OR academic_year IS NULL)");
    $dupCheck->execute([$indexNumber, $academic_year]);
    if ($dupCheck->fetchColumn() > 0) {
        logActivity($pdo, 'Public Portal', 'Submission Blocked (Duplicate Index Detected)', "Duplicate index '{$indexNumber}' for {$studentName} ({$academic_year})");
        die("Duplicate Application Detected: An application with UNEB Index Number '" . htmlspecialchars($indexNumber) . "' has already been registered in the system for the " . htmlspecialchars($academic_year) . " Academic Year.");
    }
}

if (!empty($lin)) {
    $dupLin = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE academic_year = ? AND lin = ?");
    $dupLin->execute([$academic_year, $lin]);
    if ($dupLin->fetchColumn() > 0) {
        logActivity($pdo, 'Public Portal', 'Submission Blocked (Duplicate LIN)', "Duplicate LIN '{$lin}' for {$studentName} ({$academic_year})");
        die("Duplicate Application Detected: An application with Learner Identification Number (LIN) '" . htmlspecialchars($lin) . "' has already been registered for the " . htmlspecialchars($academic_year) . " Academic Year.");
    }
}

// Duplicate child check based on candidate name and DOB (one parent email/phone is explicitly permitted to submit for multiple children)
$dupNameCheck = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE class_of_entry = ? AND LOWER(TRIM(surname)) = LOWER(TRIM(?)) AND LOWER(TRIM(firstname)) = LOWER(TRIM(?)) AND dob = ? AND academic_year = ?");
$dupNameCheck->execute([$class, $_POST['surname'], $_POST['firstname'], $dob, $academic_year]);
if ($dupNameCheck->fetchColumn() > 0) {
    logActivity($pdo, 'Public Portal', 'Submission Blocked (Duplicate Detected)', "Duplicate student '{$studentName}' (DOB {$dob}) in {$class} ({$academic_year})");
    die("Duplicate Application Detected: An application for candidate '" . htmlspecialchars($studentName) . "' with date of birth " . htmlspecialchars($dob) . " has already been registered in the system.");
}

// Residential Address Fields
$parent_village   = trim($_POST['parent_village'] ?? '');
$parent_parish    = trim($_POST['parent_parish'] ?? '');
$parent_subcounty = trim($_POST['parent_subcounty'] ?? '');
$parent_district  = trim($_POST['parent_district'] ?? '');
$parent_address   = trim($_POST['parent_address'] ?? '');
if (empty($parent_address)) {
    $parent_address = implode(', ', array_filter([$parent_village, $parent_parish, $parent_subcounty, $parent_district]));
}

// Insert Application Record
$stmt = $pdo->prepare("
INSERT INTO applications (
    surname, firstname, othernames, sex, dob, age, religion, class_of_entry, academic_year, lin, passport_photo,
    former_school, index_number, english, mathematics, sst, science,
    aggregates, division, combination, transfer_reason, result_slip,
    parent_name, parent_nin, parent_phone, parent_address, parent_email,
    parent_village, parent_parish, parent_subcounty, parent_district, parent_id_file,
    uce_project_work, uce_subjects_json, s6_former_school, s6_report_file
) VALUES (
    ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
)
");

$stmt->execute([
    $_POST['surname'],
    $_POST['firstname'],
    $_POST['othernames'] ?? '',
    $applicantSex,
    $dob,
    $age,
    $_POST['religion'] ?? '',
    $class,
    $academic_year,
    $lin,
    $passport,
    $formerSchool,
    $indexNumber,
    $english,
    $mathematics,
    $sst,
    $science,
    $aggregates,
    $division,
    $combination,
    $transferReason,
    $resultSlip,
    $_POST['parent_name'],
    $parent_nin,
    $parent_phone,
    $parent_address,
    $_POST['parent_email'],
    $parent_village,
    $parent_parish,
    $parent_subcounty,
    $parent_district,
    $parentIdFile,
    $uceProjectWork,
    $uceSubjectsJson,
    $s6FormerSchool,
    $s6ReportFile
]);

$newAppId = (int)$pdo->lastInsertId();

// AI Smart Auto-Evaluation & Auto-Admission Engine
$aiAdmissionResult = null;
if (function_exists('evaluateApplicantAdmissionAI')) {
    $aiAdmissionResult = evaluateApplicantAdmissionAI($pdo, $newAppId);
}

// Log Successful Application in Activity Logs
$studentLogName = trim($_POST['firstname'] . ' ' . $_POST['surname']);
logActivity($pdo, 'Public Portal', 'Application Submitted', "New application submitted for {$studentLogName} ({$class}) - UNEB Index: " . ($indexNumber ?: 'N/A') . ", Aggregates: " . ($aggregates ?: 'N/A'));

// -----------------------------
// SEND EMAIL TO APPLICANT (Elegant Template)
// -----------------------------
$school_name       = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_email      = getSetting($pdo, 'school_email', 'info@ncedges.com');
$school_phone      = getSetting($pdo, 'school_phone', '+256 790 001 631');
$admin_email       = getSetting($pdo, 'admin_email', 'info@ncedges.com');
$primary_color     = getPrimaryColor($pdo);
$accent_color      = getAccentColor($pdo);

$logo_source = getSchoolLogoSrc($pdo);
if (strpos($logo_source, 'http') === false) {
    $logo_source = getBaseUrl($pdo) . '/' . ltrim($logo_source, '/');
}

$adminDashboardUrl = getBaseUrl($pdo) . '/admin/dashboard.php';

$studentName = htmlspecialchars($_POST['firstname'] . ' ' . $_POST['surname']);
$parentName = htmlspecialchars($_POST['parent_name']);
$submissionDate = date('d-m-Y H:i:s');
$classEntry = "Senior " . substr($class, 1) . " (" . $class . ")";

$parentMessage = "
<!DOCTYPE html>
<html>
<head>
<meta charset='utf-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
<link rel='preconnect' href='https://fonts.googleapis.com'>
<link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
<link href='https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap' rel='stylesheet'>
<style>
body, div, p, span, h1, h2, h3, h4, th, td, a {
    font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
}
@media only screen and (max-width: 620px) {
    .outer-wrapper { padding: 8px 4px !important; }
    .email-container { width: 100% !important; max-width: 100% !important; border-radius: 8px !important; margin: 0 auto !important; }
    .email-body { padding: 18px 12px !important; font-size: 14.5px !important; line-height: 1.6 !important; }
    .email-header { padding: 22px 14px !important; }
    .email-header h2 { font-size: 17px !important; line-height: 1.3 !important; }
    .inner-box { padding: 12px 10px !important; margin: 14px 0 !important; border-radius: 8px !important; }
    .summary-table td { display: block !important; width: 100% !important; box-sizing: border-box !important; padding: 3px 0 !important; }
    .summary-table tr { display: block !important; border-bottom: 1px solid #edf2f7 !important; padding: 5px 0 !important; }
    .email-footer { padding: 16px 10px !important; }
}
</style>
</head>
<body style='margin: 0; padding: 0; background-color: #f1f5f9;'>
<div class='outer-wrapper' style=\"font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; padding: 20px 8px; font-size: 15px; line-height: 1.65; color: #1e293b;\">
    <div class='email-container' style='max-width: 620px; width: 100%; margin: 0 auto; background-color: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;'>
        <!-- Header -->
        <div class='email-header' style='background-color: {$primary_color}; padding: 26px 20px; text-align: center; border-bottom: 3px solid {$accent_color};'>
            <table role='presentation' border='0' cellpadding='0' cellspacing='0' align='center' style='margin: 0 auto 14px auto; text-align: center; border-collapse: collapse;'>
                <tr>
                    <td align='center' style='text-align: center; background: transparent; background-color: transparent;'>
                        <img src='{$logo_source}' alt='{$school_short_name} Logo' style='display: block; margin: 0 auto; max-height: 80px; max-width: 180px; height: auto; width: auto; background-color: transparent; border: 0; outline: none; object-fit: contain;'>
                    </td>
                </tr>
            </table>
            <h2 style='color: #ffffff; margin: 0; font-size: 20px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase;'>{$school_name}</h2>
            <p style='color: {$accent_color}; margin: 5px 0 0 0; font-size: 11.5px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;'>Online Admissions Portal</p>
        </div>
        <!-- Body -->
        <div class='email-body' style='padding: 26px 20px;'>
            <p style='margin-top: 0; font-weight: 700; color: {$primary_color}; font-size: 16px;'>Dear Parent / Guardian (<strong>$parentName</strong>),</p>
            <p>Thank you for submitting your child's application to <strong>{$school_name}</strong>. We are pleased to confirm that the application has been successfully received and is currently under review by our admissions committee.</p>
            
            <div class='inner-box' style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid {$primary_color}; padding: 16px 14px; border-radius: 10px; margin: 20px 0;'>
                <h4 style='margin-top: 0; color: {$primary_color}; font-size: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; font-weight: 700;'>Application Details Summary</h4>
                <table class='summary-table' style='width: 100%; font-size: 14px; border-collapse: collapse; table-layout: auto;'>
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; width: 140px; vertical-align: top;'>Student Name:</td>
                        <td style='padding: 8px 0; color: #0f172a; font-weight: 700;'>{$studentName}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; vertical-align: top;'>Class of Entry:</td>
                        <td style='padding: 8px 0; color: #0f172a;'>{$classEntry}</td>
                    </tr>";

if ($class === 'S1' || $class === 'S5' || $class === 'S6') {
    $parentMessage .= "
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; vertical-align: top;'>Index Number:</td>
                        <td style='padding: 8px 0; color: #0f172a; font-family: monospace; font-weight: 700;'>{$indexNumber}</td>
                    </tr>";
}

if ($class === 'S5' || $class === 'S6') {
    $parentMessage .= "
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; vertical-align: top;'>UCE NLSC Result:</td>
                        <td style='padding: 8px 0; color: #0f172a; font-weight: 700;'>{$division} (Project Work: Grade {$uceProjectWork})</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; vertical-align: top;'>A-Level Combination:</td>
                        <td style='padding: 8px 0; color: #0f172a; font-weight: 700;'>{$combination}</td>
                    </tr>";
}

$parentMessage .= "
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; vertical-align: top;'>Date Submitted:</td>
                        <td style='padding: 8px 0; color: #0f172a;'>{$submissionDate}</td>
                    </tr>
                </table>
            </div>

            <p>No further action is required from you at this time. Once the admissions committee completes the evaluation, a formal notification letter detailing the final decision will be sent to this email address.</p>
            <p>For any inquiries regarding your application, please reach out to the admissions office at <strong>{$school_email}</strong> or call <strong>{$school_phone}</strong>.</p>
            
            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 28px 0;'>
            
            <p style='margin-bottom: 2px; font-size: 13.5px; color: #64748b;'>Yours faithfully,</p>
            <p style='margin-top: 4px; font-weight: 700; color: {$primary_color}; font-size: 14.5px;'>Office of Admissions Registry<br><span style='color: #64748b; font-size: 12.5px; font-weight: normal;'>{$school_name}</span></p>
        </div>
        <!-- Footer -->
        <div style='background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;'>
            <p style='margin: 0; font-size: 11.5px; color: #94a3b8;'>This is an automated notification. Please do not reply directly to this email.</p>
            <p style='margin: 4px 0 0 0; font-size: 11.5px; color: #94a3b8;'>&copy; " . date('Y') . " {$school_name}. All rights reserved.</p>
        </div>
    </div>
</div>
";

// Only send standard pending confirmation if applicant was not already auto-admitted with official offer
if (empty($aiAdmissionResult['admitted'])) {
    sendEmail(
        $_POST['parent_email'],
        "Application Received - {$school_short_name}",
        $parentMessage
    );
}

// -----------------------------
// SEND ALERT EMAIL TO ADMINS (Elegant HTML)
// -----------------------------
$adminSubject = "New Application Alert: {$studentName} ({$class}) - {$school_short_name}";
$adminMessage = "
<!DOCTYPE html>
<html>
<head>
<meta charset='utf-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
<style>
body, div, p, span, h1, h2, h3, h4, th, td, a {
    font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
}
@media only screen and (max-width: 620px) {
    .outer-wrapper { padding: 8px 4px !important; }
    .email-container { width: 100% !important; max-width: 100% !important; border-radius: 8px !important; margin: 0 auto !important; }
    .email-body { padding: 18px 12px !important; font-size: 14.5px !important; line-height: 1.6 !important; }
    .email-header { padding: 22px 14px !important; }
    .email-header h2 { font-size: 17px !important; line-height: 1.3 !important; }
    .inner-box { padding: 12px 10px !important; margin: 14px 0 !important; border-radius: 8px !important; }
    .summary-table td { display: block !important; width: 100% !important; box-sizing: border-box !important; padding: 3px 0 !important; }
    .summary-table tr { display: block !important; border-bottom: 1px solid #edf2f7 !important; padding: 5px 0 !important; }
    .btn-action { display: block !important; width: 100% !important; box-sizing: border-box !important; text-align: center !important; padding: 13px 12px !important; font-size: 14px !important; }
    .email-footer { padding: 16px 10px !important; }
}
</style>
</head>
<body style='margin: 0; padding: 0; background-color: #f1f5f9;'>
<div class='outer-wrapper' style=\"font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; padding: 20px 8px; font-size: 15px; line-height: 1.65; color: #1e293b;\">
    <div class='email-container' style='max-width: 620px; width: 100%; margin: 0 auto; background-color: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;'>
        <!-- Header -->
        <div class='email-header' style='background-color: {$primary_color}; padding: 26px 20px; text-align: center; border-bottom: 3px solid {$accent_color};'>
            <table role='presentation' border='0' cellpadding='0' cellspacing='0' align='center' style='margin: 0 auto 14px auto; text-align: center; border-collapse: collapse;'>
                <tr>
                    <td align='center' style='text-align: center; background: transparent; background-color: transparent;'>
                        <img src='{$logo_source}' alt='{$school_short_name} Logo' style='display: block; margin: 0 auto; max-height: 80px; max-width: 180px; height: auto; width: auto; background-color: transparent; border: 0; outline: none; object-fit: contain;'>
                    </td>
                </tr>
            </table>
            <h2 style='color: #ffffff; margin: 0; font-size: 20px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase;'>{$school_name}</h2>
            <p style='color: {$accent_color}; margin: 5px 0 0 0; font-size: 11.5px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;'>Admissions Control Center</p>
        </div>
        <!-- Body -->
        <div class='email-body' style='padding: 26px 20px;'>
            <p style='margin-top: 0; font-weight: 700; color: {$primary_color}; font-size: 16px;'>Alert: New Admission Application Submitted</p>
            <p>A new application has been successfully submitted via the online admissions portal. Below is a summary of the applicant's details:</p>
            
            <div class='inner-box' style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid {$accent_color}; padding: 16px 14px; border-radius: 10px; margin: 20px 0;'>
                <h4 style='margin-top: 0; color: {$primary_color}; font-size: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; font-weight: 700;'>Student Credentials</h4>
                <table class='summary-table' style='width: 100%; font-size: 14px; border-collapse: collapse; table-layout: auto;'>
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; width: 140px; vertical-align: top;'>Student Name:</td>
                        <td style='padding: 8px 0; color: #0f172a; font-weight: 700;'>{$studentName}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; vertical-align: top;'>Gender:</td>
                        <td style='padding: 8px 0; color: #0f172a;'>{$_POST['sex']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; vertical-align: top;'>Class of Entry:</td>
                        <td style='padding: 8px 0; color: #0f172a;'>{$classEntry}</td>
                    </tr>";

if ($class === 'S1' || $class === 'S5' || $class === 'S6') {
    $adminMessage .= "
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; vertical-align: top;'>Index Number:</td>
                        <td style='padding: 8px 0; color: #0f172a; font-family: monospace; font-weight: 700;'>{$indexNumber}</td>
                    </tr>";
}

if ($class === 'S1') {
    $adminMessage .= "
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; vertical-align: top;'>PLE Aggregates:</td>
                        <td style='padding: 8px 0; color: #0f172a; font-weight: 700;'>{$aggregates} ({$division})</td>
                    </tr>";
} elseif ($class === 'S5' || $class === 'S6') {
    $adminMessage .= "
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; vertical-align: top;'>UCE NLSC Result:</td>
                        <td style='padding: 8px 0; color: #0f172a; font-weight: 700;'>{$division} (Project: Grade {$uceProjectWork})</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; vertical-align: top;'>Competency Score:</td>
                        <td style='padding: 8px 0; color: #0f172a; font-weight: 700;'>{$competencyScore} Points (Agg Equiv: {$aggregates})</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; vertical-align: top;'>Combination:</td>
                        <td style='padding: 8px 0; color: #0f172a; font-weight: 700;'>{$combination}</td>
                    </tr>";
    if ($class === 'S6' && !empty($s6FormerSchool)) {
        $adminMessage .= "
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; vertical-align: top;'>Former S5 School:</td>
                        <td style='padding: 8px 0; color: #0f172a;'>{$s6FormerSchool}</td>
                    </tr>";
    }
} else {
    $adminMessage .= "
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; vertical-align: top;'>Last Class:</td>
                        <td style='padding: 8px 0; color: #0f172a;'>{$division}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; vertical-align: top;'>Transfer Reason:</td>
                        <td style='padding: 8px 0; color: #0f172a;'>{$transferReason}</td>
                    </tr>";
}

$adminMessage .= "
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; vertical-align: top;'>Parent/Guardian:</td>
                        <td style='padding: 8px 0; color: #0f172a;'>{$parentName} ({$_POST['parent_phone']})</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; font-weight: 600; vertical-align: top;'>Date Submitted:</td>
                        <td style='padding: 8px 0; color: #0f172a;'>{$submissionDate}</td>
                    </tr>
                </table>
            </div>

            <div style='text-align: center; margin: 24px 0;'>
                <a href='{$adminDashboardUrl}' class='btn-action' style='background-color: {$primary_color}; color: #ffffff; padding: 14px 28px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 14.5px; display: inline-block; box-shadow: 0 4px 10px rgba(0,0,0,0.12);'>
                    Access Admissions Admin Panel
                </a>
            </div>
            
            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 28px 0;'>
            <p style='margin: 0; font-size: 12.5px; color: #64748b; text-align: center;'>Admissions Portal System Notification</p>
        </div>
    </div>
</div>
</body>
</html>
";

$notifyRecipientsRaw = getSetting($pdo, 'application_notification_emails', $admin_email);
$adminEmails = array_filter(array_map('trim', explode(',', $notifyRecipientsRaw)));
foreach ($adminEmails as $admin) {
    sendEmail(
        $admin,
        "New Application Alert: {$studentName} ({$class}) - {$school_short_name}",
        $adminMessage
    );
}

// Redirect on success with auto-admit flags
$admittedParam = !empty($aiAdmissionResult['admitted']) ? '&admitted=1' : '';
$combParam = !empty($aiAdmissionResult['combination']) ? '&comb=' . urlencode($aiAdmissionResult['combination']) : '';
header("Location: success.php?name=" . urlencode($studentName) . "&id=" . $newAppId . $admittedParam . $combParam);
exit;
