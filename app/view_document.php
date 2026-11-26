<?php
// Enforce official database connection and domain-wide session initialization
require_once __DIR__ . '/../config/db.php';
initSession();

// Branding configurations
$school_name       = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$primary_color     = getPrimaryColor($pdo);
$logo_source       = getSchoolLogoSrc($pdo, '../');

// 1. Strict Authentication Check: Only logged-in administrators can access uploaded documents
$isLoggedIn = !empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_username']);

// Enforce inactivity timeout (2 hours)
$timeout_seconds = 7200;
if ($isLoggedIn && isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_seconds)) {
    session_unset();
    session_destroy();
    $isLoggedIn = false;
}

if ($isLoggedIn) {
    $_SESSION['last_activity'] = time();
} else {
    http_response_code(403);
    $currentUri = $_SERVER['REQUEST_URI'] ?? 'applications.php';
    $loginUrl = 'login.php?return_to=' . urlencode($currentUri);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Restricted · Protected Candidate Document</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', 'Outfit', system-ui, sans-serif; }
    </style>
    <?= renderThemeConfig($pdo) ?>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl border border-slate-200 p-6 sm:p-8 text-center space-y-5 animate-in fade-in duration-200">
        
        <!-- School Logo -->
        <div class="w-16 h-16 rounded-2xl bg-slate-50 border border-slate-200 p-2 mx-auto flex items-center justify-center shadow-xs">
            <img src="<?= htmlspecialchars($logo_source) ?>" alt="School Logo" class="max-w-full max-h-full object-contain">
        </div>

        <!-- Shield / Restricted Icon -->
        <div class="w-14 h-14 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center text-2xl mx-auto shadow-inner border border-rose-200">
            <i class="fa-solid fa-shield-halved"></i>
        </div>

        <div class="space-y-2">
            <span class="inline-block px-3 py-1 bg-rose-100 text-rose-800 text-[10.5px] font-extrabold uppercase rounded-full tracking-wider">
                Confidential Candidate Document
            </span>
            <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 tracking-tight">Access Restricted</h2>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                Candidate academic records (UNEB result slips) and parent identification documents are strictly confidential and protected by student data privacy policies.
            </p>
            <p class="text-xs text-slate-500 font-medium">
                Direct public access is prohibited. Please sign in as an authorized admissions officer to review this dossier.
            </p>
        </div>

        <div class="pt-2 space-y-2.5">
            <a href="<?= htmlspecialchars($loginUrl) ?>" class="w-full inline-flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-xs sm:text-sm font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-md">
                <i class="fa-solid fa-lock text-xs"></i> Sign In to View Document
            </a>
            <a href="../index.php" class="w-full inline-flex items-center justify-center py-2.5 px-4 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                Return to Admissions Portal
            </a>
        </div>

        <div class="pt-3 border-t border-slate-100 text-[11px] text-slate-400">
            <?= htmlspecialchars($school_name) ?> · Admissions Registry
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}

// 2. Parse and Normalize Requested File Parameter
$rawFolder = trim((string)($_GET['folder'] ?? ''));
$rawFile   = trim((string)($_GET['file'] ?? ($_GET['path'] ?? '')));

// Strip directory traversal sequences and backslashes
$cleanFolder = str_replace(['../', '..\\', './', '.\\'], '', $rawFolder);
$cleanFile   = str_replace(['../', '..\\', './', '.\\'], '', $rawFile);

// Remove leading and trailing slashes
$cleanFolder = trim($cleanFolder, "/\\");
$cleanFile   = trim($cleanFile, "/\\");

// If file path starts with "uploads/", strip it
if (str_starts_with($cleanFile, 'uploads/')) {
    $cleanFile = substr($cleanFile, strlen('uploads/'));
}

// Assemble normalized subPath
if (!empty($cleanFolder)) {
    // If cleanFile already begins with cleanFolder . '/', avoid duplication
    if (str_starts_with($cleanFile, $cleanFolder . '/')) {
        $subPath = $cleanFile;
    } else {
        $subPath = $cleanFolder . '/' . $cleanFile;
    }
} else {
    $subPath = $cleanFile;
}

// Security: reject null bytes and empty paths
if (empty($subPath) || strpos($subPath, "\0") !== false) {
    http_response_code(400);
    die("Bad Request: Invalid document reference.");
}

// Strict Directory Containment Verification
$baseUploads = realpath(__DIR__ . '/../uploads');
if (!$baseUploads || !is_dir($baseUploads)) {
    http_response_code(500);
    die("Server storage directory is not accessible.");
}

$fullPath = realpath($baseUploads . '/' . $subPath);

// Verify file exists, is regular file, and stays strictly inside uploads directory
if (!$fullPath || !is_file($fullPath) || !str_starts_with($fullPath, $baseUploads)) {
    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Not Found</title>
    <style>body{font-family:system-ui,-apple-system,sans-serif;max-width:550px;margin:80px auto;padding:24px;text-align:center;color:#334155;}</style>
</head>
<body>
    <h2 style="color:#0f172a;">Document File Not Found</h2>
    <p>The requested candidate document could not be located on the server. It may have been relocated, removed, or never uploaded.</p>
    <p><a href="applications.php" style="color:#0c4a24;font-weight:bold;">&larr; Return to Applications Registry</a></p>
</body>
</html>
    <?php
    exit;
}

// Verify that the requested file resides within authorized document folders
$relPathFromUploads = ltrim(substr($fullPath, strlen($baseUploads)), '/\\');
$topFolder = explode('/', str_replace('\\', '/', $relPathFromUploads))[0] ?? '';

$authorizedFolders = ['results', 'parent_ids', 'documents', 'passports'];
if (!in_array($topFolder, $authorizedFolders)) {
    http_response_code(403);
    die("Access Denied: Requested folder is not accessible.");
}

// 3. Determine Safe MIME Type
$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'gif'  => 'image/gif',
    'bmp'  => 'image/bmp',
    'txt'  => 'text/plain',
];

$mimeType = $mimeMap[$ext] ?? '';
if (empty($mimeType) && function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $detected = finfo_file($finfo, $fullPath);
        if (!empty($detected)) $mimeType = $detected;
        finfo_close($finfo);
    }
}
if (empty($mimeType)) {
    $mimeType = 'application/octet-stream';
}

// 4. Clear Output Buffers to prevent corruption of binary files / PDFs
while (ob_get_level()) {
    ob_end_clean();
}

// 5. Send Secure Streaming Headers
$cleanFileName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($fullPath));

http_response_code(200);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string)filesize($fullPath));
header('Content-Disposition: inline; filename="' . $cleanFileName . '"');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Stream file directly to client
readfile($fullPath);
exit;
