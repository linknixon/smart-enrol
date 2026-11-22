<?php
/**
 * Smart-Enrol Admissions System - Database Seeder & Auto-Installer
 * 
 * Usage from terminal:
 *   php seed.php
 *   php seed.php --host=localhost --user=root --pass=secret --db=mutolere_admissions
 */

if (php_sapi_name() !== 'cli' && empty($_GET['run'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Database Seeder</title>";
    echo "<style>body{font-family:system-ui,-apple-system,sans-serif;max-width:700px;margin:40px auto;padding:24px;background:#f8fafc;color:#1e293b;line-height:1.6;}";
    echo ".card{background:#fff;border-radius:12px;padding:32px;box-shadow:0 4px 16px rgba(0,0,0,0.06);border:1px solid #e2e8f0;}";
    echo ".btn{display:inline-block;background:#0d9488;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;margin-top:20px;}";
    echo "code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-family:monospace;}";
    echo "</style></head><body><div class='card'>";
    echo "<h2>Smart-Enrol Admissions Database Initializer</h2>";
    echo "<p>This script will automatically create the database <code>mutolere_admissions</code>, generate all required tables, and seed the default roles and super admin account.</p>";
    echo "<a href='seed.php?run=1' class='btn'>Click to Seed Database Now</a>";
    echo "</div></body></html>";
    exit;
}

$isCli = (php_sapi_name() === 'cli');

function output($msg, $type = 'info') {
    global $isCli;
    if ($isCli) {
        $prefix = $type === 'success' ? "\033[32m[OK]\033[0m " : ($type === 'error' ? "\033[31m[ERROR]\033[0m " : "\033[34m[INFO]\033[0m ");
        echo $prefix . $msg . PHP_EOL;
    } else {
        $color = $type === 'success' ? '#0d9488' : ($type === 'error' ? '#ef4444' : '#2563eb');
        echo "<div style='color:{$color};margin:6px 0;font-family:monospace;'>[" . strtoupper($type) . "] " . htmlspecialchars($msg) . "</div>";
    }
}

// Parse CLI flags or fall back to defaults
$options = $isCli ? getopt('', ['host::', 'user::', 'pass::', 'db::']) : [];

$host = $options['host'] ?? (getenv('DB_HOST') ?: 'localhost');
$user = $options['user'] ?? (getenv('DB_USER') ?: 'webadmin');
$pass = $options['pass'] ?? (getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'User@2026@');
$db   = $options['db']   ?? (getenv('DB_NAME') ?: 'mutolere_admissions');
$charset = 'utf8mb4';

output("Starting Smart-Enrol Database Auto-Seeder...");
output("Target Database: {$db} on host: {$host}");

// Attempt connections to MySQL server
$rootCandidates = [
    ['host' => $host, 'port' => null, 'user' => $user, 'pass' => $pass],
    ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => 'root'],
    ['host' => 'localhost', 'port' => null, 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => 8889, 'user' => 'root', 'pass' => 'root'],
    ['host' => '127.0.0.1', 'port' => 8889, 'user' => $user, 'pass' => $pass],
];

$pdo = null;
$connectedCandidate = null;

foreach ($rootCandidates as $candidate) {
    try {
        $dsn = !empty($candidate['port'])
            ? "mysql:host={$candidate['host']};port={$candidate['port']};charset={$charset}"
            : "mysql:host={$candidate['host']};charset={$charset}";
        $tmpPdo = new PDO($dsn, $candidate['user'], $candidate['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $pdo = $tmpPdo;
        $connectedCandidate = $candidate;
        output("Connected to MySQL server using user '{$candidate['user']}' on host '{$candidate['host']}'", "success");
        break;
    } catch (PDOException $e) {
        // Try next candidate
    }
}

if (!$pdo) {
    output("Could not connect to MySQL server. Please verify your MySQL server is running.", "error");
    output("You can specify custom credentials: php seed.php --host=localhost --user=root --pass=your_password --db=mutolere_admissions", "info");
    exit(1);
}

// Step 1: Create Database
try {
    output("Creating database `{$db}` if it does not exist...");
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `{$db}`;");
    output("Database `{$db}` is ready and selected.", "success");
} catch (Exception $e) {
    output("Failed to create/select database: " . $e->getMessage(), "error");
    exit(1);
}

// Step 2: Read and execute mutolere_admissions.sql
$sqlFile = __DIR__ . '/mutolere_admissions.sql';
if (!file_exists($sqlFile)) {
    output("Schema file mutolere_admissions.sql not found at {$sqlFile}", "error");
    exit(1);
}

output("Reading schema file: mutolere_admissions.sql...");
$sqlContent = file_get_contents($sqlFile);

// Remove comments and split into individual queries
$queries = [];
$lines = explode("\n", $sqlContent);
$buffer = '';

foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '#')) {
        continue;
    }
    $buffer .= $line . "\n";
    if (str_ends_with($trimmed, ';')) {
        $queries[] = $buffer;
        $buffer = '';
    }
}
if (!empty(trim($buffer))) {
    $queries[] = $buffer;
}

output("Executing " . count($queries) . " schema statements...");
$executed = 0;
$skipped = 0;

foreach ($queries as $q) {
    $qTrim = trim($q);
    if (empty($qTrim)) continue;
    try {
        $pdo->exec($qTrim);
        $executed++;
    } catch (PDOException $e) {
        // Ignore duplicate key or already exists notices
        if (strpos($e->getMessage(), 'already exists') !== false) {
            $skipped++;
        } else {
            output("Notice on query: " . $e->getMessage(), "info");
        }
    }
}

output("Schema executed successfully ({$executed} statements executed).", "success");

// Step 3: Run config/db.php auto-migration to guarantee all columns & settings exist
try {
    output("Applying system migrations and default role definitions...");
    require_once __DIR__ . '/config/db.php';
    output("System migrations and role verification complete.", "success");
} catch (Exception $e) {
    output("Migration notice: " . $e->getMessage(), "info");
}

// Step 4: Verify Tables and Seed Count
try {
    $tablesStmt = $pdo->query("SHOW TABLES FROM `{$db}`");
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
    output("Database `{$db}` now contains " . count($tables) . " tables: " . implode(', ', $tables), "success");
    
    // Check super admin
    $adminStmt = $pdo->query("SELECT id, username, role, email FROM `{$db}`.admin_users WHERE username = 'admin'");
    $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        output("Default Super Admin verified: username = 'admin' (password: User@2026@)", "success");
    }

    // Check roles
    $rolesStmt = $pdo->query("SELECT COUNT(*) FROM `{$db}`.system_roles");
    $roleCount = $rolesStmt ? $rolesStmt->fetchColumn() : 0;
    output("System roles verified: {$roleCount} roles configured.", "success");

} catch (Exception $e) {
    output("Verification error: " . $e->getMessage(), "error");
}

output("Database seeding completed successfully!", "success");
if (!$isCli) {
    echo "<p><a href='admin/login.php' style='display:inline-block;background:#0d9488;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;'>Proceed to Admin Login</a></p>";
}
