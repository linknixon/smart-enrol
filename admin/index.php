<?php
// Seamless 301 Permanent Redirect from legacy admin/ to app/
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$qs = $_SERVER['QUERY_STRING'] ?? '';
$targetFile = basename($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php');
if ($targetFile === 'index.php') {
    $targetFile = 'login.php';
}

$dest = '../app/' . $targetFile;
if (!empty($qs)) {
    $dest .= '?' . $qs;
}

header("Location: " . $dest, true, 301);
exit;
