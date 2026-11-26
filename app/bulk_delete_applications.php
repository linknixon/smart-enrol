<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';

$isSuperAdmin = (($_SESSION['admin_role'] ?? '') === 'super_admin' || in_array('super_admin', $_SESSION['admin_roles'] ?? []));
if (!$isSuperAdmin) {
    header("Location: applications.php?error=" . urlencode("Permission Denied: Only Super Administrators have privilege to delete application entries."));
    exit;
}

$rawIds = $_POST['ids'] ?? ($_POST['id'] ?? ($_GET['id'] ?? []));
if (is_string($rawIds)) {
    $rawIds = explode(',', $rawIds);
}
if (!is_array($rawIds)) {
    $rawIds = [$rawIds];
}

$ids = [];
foreach ($rawIds as $val) {
    if (is_numeric($val) && (int)$val > 0) {
        $ids[] = (int)$val;
    }
}
$ids = array_values(array_unique($ids));

if (empty($ids)) {
    header("Location: applications.php?error=" . urlencode("No valid applications were selected for deletion."));
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
    $stmt = $pdo->prepare("SELECT id, firstname, surname, index_number, passport_photo, result_slip, parent_id_file, s6_report_file FROM applications WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $appsToDelete = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $uploadsDir = realpath(__DIR__ . '/../uploads');
    $deletedNames = [];

    foreach ($appsToDelete as $app) {
        $deletedNames[] = trim(($app['firstname'] ?? '') . ' ' . ($app['surname'] ?? '')) . ' (#' . $app['id'] . ')';

        // Safely purge uploaded dossier files from storage
        $filesToCheck = [
            $app['passport_photo'] ?? '',
            $app['result_slip'] ?? '',
            $app['parent_id_file'] ?? '',
            $app['s6_report_file'] ?? ''
        ];

        foreach ($filesToCheck as $f) {
            if (!empty($f) && is_string($f) && strpos($f, 'http') === false) {
                $cleaned = ltrim($f, '/');
                $fullPath = realpath(__DIR__ . '/../' . $cleaned);
                if ($fullPath && file_exists($fullPath) && is_file($fullPath)) {
                    // Strict directory-traversal safety check: must reside inside uploads directory
                    if ($uploadsDir && str_starts_with($fullPath, $uploadsDir)) {
                        @unlink($fullPath);
                    }
                }
            }
        }

        // Clean up corresponding auxiliary records in legacy intake tables if present
        if (!empty($app['index_number'])) {
            try {
                $pdo->prepare("DELETE FROM s1_applications WHERE index_number = ?")->execute([$app['index_number']]);
                $pdo->prepare("DELETE FROM s5_applications WHERE uce_index = ?")->execute([$app['index_number']]);
            } catch (Throwable $e) {}
        }
    }

    // Execute bulk deletion from unified applications table
    $delStmt = $pdo->prepare("DELETE FROM applications WHERE id IN ($placeholders)");
    $delStmt->execute($ids);
    $deletedCount = $delStmt->rowCount();

    // Log the action in system audit trail
    $summary = "Super Admin permanently deleted {$deletedCount} application dossier(s): " . implode(', ', array_slice($deletedNames, 0, 10));
    if (count($deletedNames) > 10) {
        $summary .= " ... and " . (count($deletedNames) - 10) . " more";
    }
    logActivity($pdo, $_SESSION['admin_username'] ?? 'Super Admin', "Delete Applications", $summary);

    header("Location: applications.php?msg=bulk_deleted&count=" . $deletedCount);
    exit;
} catch (Throwable $e) {
    error_log("Bulk delete applications error: " . $e->getMessage());
    header("Location: applications.php?error=" . urlencode("A database error occurred during deletion: " . $e->getMessage()));
    exit;
}
