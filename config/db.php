<?php
// Enforce official Uganda / East Africa Time (EAT - UTC+3) system-wide
date_default_timezone_set('Africa/Kampala');

// Unified Session Initializer with domain-wide cookie and directory permissions fallback
if (!function_exists('initSession')) {
    function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

            // Fallback session save directory if system directory is not writable
            $sysSavePath = @session_save_path();
            if (empty($sysSavePath) || !@is_writable($sysSavePath)) {
                $customSess = __DIR__ . '/../uploads/sessions';
                if (!is_dir($customSess)) {
                    @mkdir($customSess, 0775, true);
                }
                if (@is_writable($customSess)) {
                    @session_save_path($customSess);
                }
            }

            // Always enforce cookie availability on the entire domain (path = /)
            @session_set_cookie_params([
                'lifetime' => 86400 * 7,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            @session_start();
        }
    }
}

// Ensure session is active
initSession();

$host = getenv('DB_HOST') ?: "localhost";
$db   = getenv('DB_NAME') ?: "mutolere_admissions";
$user = getenv('DB_USER') ?: "webadmin";
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "User@2026@";
$charset = "utf8mb4";

// Optional local overrides (ignored by Git)
if (file_exists(__DIR__ . '/local_db.php')) {
    include __DIR__ . '/local_db.php';
}

try {
$pdo = null;
$lastException = null;

// Candidates for PDO connection (standard local MySQL, MAMP port 8889, MAMP socket, root fallback)
$candidates = [
    // 1. Configured default (mutolere_admissions)
    ['host' => $host, 'port' => null, 'socket' => null, 'user' => $user, 'pass' => $pass, 'db' => $db],
    // 1b. Fallback with hyphenated DB name (mutolere-admissions)
    ['host' => $host, 'port' => null, 'socket' => null, 'user' => $user, 'pass' => $pass, 'db' => 'mutolere-admissions'],
    // 2. MAMP Port 8889 with configured credentials
    ['host' => '127.0.0.1', 'port' => 8889, 'socket' => null, 'user' => $user, 'pass' => $pass, 'db' => $db],
    ['host' => '127.0.0.1', 'port' => 8889, 'socket' => null, 'user' => $user, 'pass' => $pass, 'db' => 'mutolere-admissions'],
    // 3. MAMP Socket with configured credentials
    ['host' => null, 'port' => null, 'socket' => '/Applications/MAMP/tmp/mysql/mysql.sock', 'user' => $user, 'pass' => $pass, 'db' => $db],
    // 4. MAMP Port 8889 with default MAMP root/root
    ['host' => '127.0.0.1', 'port' => 8889, 'socket' => null, 'user' => 'root', 'pass' => 'root', 'db' => $db],
    ['host' => '127.0.0.1', 'port' => 8889, 'socket' => null, 'user' => 'root', 'pass' => 'root', 'db' => 'mutolere-admissions'],
    // 5. MAMP Socket with default MAMP root/root
    ['host' => null, 'port' => null, 'socket' => '/Applications/MAMP/tmp/mysql/mysql.sock', 'user' => 'root', 'pass' => 'root', 'db' => $db],
    // 6. Standard Linux / 3306 with root / root or empty pass or socket
    ['host' => '127.0.0.1', 'port' => 3306, 'socket' => null, 'user' => 'root', 'pass' => 'root', 'db' => $db],
    ['host' => '127.0.0.1', 'port' => 3306, 'socket' => null, 'user' => 'root', 'pass' => '', 'db' => $db],
    ['host' => 'localhost', 'port' => null, 'socket' => null, 'user' => 'root', 'pass' => '', 'db' => $db],
    ['host' => 'localhost', 'port' => null, 'socket' => null, 'user' => 'root', 'pass' => 'root', 'db' => $db],
];

foreach ($candidates as $c) {
    try {
        if (!empty($c['socket']) && file_exists($c['socket'])) {
            $dsn = "mysql:unix_socket={$c['socket']};dbname={$c['db']};charset={$charset}";
        } elseif (!empty($c['port'])) {
            $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['db']};charset={$charset}";
        } else {
            $dsn = "mysql:host={$c['host']};dbname={$c['db']};charset={$charset}";
        }

        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        break; // Connected successfully
    } catch (PDOException $e) {
        $lastException = $e;
        // If the database doesn't exist, attempt to auto-create it
        if ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
            try {
                if (!empty($c['socket']) && file_exists($c['socket'])) {
                    $adminDsn = "mysql:unix_socket={$c['socket']};charset={$charset}";
                } elseif (!empty($c['port'])) {
                    $adminDsn = "mysql:host={$c['host']};port={$c['port']};charset={$charset}";
                } else {
                    $adminDsn = "mysql:host={$c['host']};charset={$charset}";
                }
                $tmpPdo = new PDO($adminDsn, $c['user'], $c['pass']);
                $tmpPdo->exec("CREATE DATABASE IF NOT EXISTS `{$c['db']}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                // Retry connecting to created DB
                $pdo = new PDO($dsn, $c['user'], $c['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                break;
            } catch (Exception $e2) {
                // Continue to next candidate
            }
        }
    }
}

if (!$pdo) {
    if (php_sapi_name() === 'cli') {
        throw ($lastException ?: new Exception("Could not connect to MySQL database"));
    }
    http_response_code(500);
    $errMsg = $lastException ? $lastException->getMessage() : 'Could not connect to MySQL database';
    echo "<!DOCTYPE html><html><head><title>Database Configuration Required</title>";
    echo "<style>body{font-family:system-ui,-apple-system,sans-serif;max-width:650px;margin:60px auto;padding:24px;background:#f8fafc;color:#1e293b;}";
    echo ".card{background:#fff;border-radius:12px;padding:32px;box-shadow:0 4px 16px rgba(0,0,0,0.06);border:1px solid #e2e8f0;}";
    echo ".alert{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 16px;border-radius:8px;font-family:monospace;font-size:13px;margin:16px 0;word-break:break-all;}";
    echo "pre{background:#f1f5f9;padding:14px;border-radius:8px;overflow-x:auto;font-size:13px;border:1px solid #e2e8f0;}";
    echo "</style></head><body><div class='card'>";
    echo "<h2 style='color:#0f172a;margin-top:0;'>Database Connection Required</h2>";
    echo "<p>The admissions portal could not connect to MySQL with the default credentials.</p>";
    echo "<div class='alert'><strong>MySQL Notice:</strong> " . htmlspecialchars($errMsg) . "</div>";
    echo "<p>Please create <code>config/local_db.php</code> with your server's database credentials:</p>";
    echo "<pre>&lt;?php\n\$host = 'localhost';\n\$db   = 'your_database_name';\n\$user = 'your_mysql_username';\n\$pass = 'your_mysql_password';</pre>";
    echo "</div></body></html>";
    exit;
}

try {
    // Synchronize database session timezone with East Africa Time (EAT / UTC+3)
    $pdo->exec("SET time_zone = '+03:00'");
} catch (Exception $e) {}

    // 1. Automatically create admin_logs table if it doesn't exist
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS admin_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        action VARCHAR(255) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Automatically create admin_users table if it doesn't exist
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        firstname VARCHAR(100) DEFAULT '',
        lastname VARCHAR(100) DEFAULT '',
        email VARCHAR(100) DEFAULT '',
        username VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        must_change_password TINYINT DEFAULT 0,
        role VARCHAR(50) NOT NULL DEFAULT 'admin',
        roles_json TEXT DEFAULT NULL,
        mfa_enabled TINYINT DEFAULT 0,
        mfa_code VARCHAR(20) DEFAULT NULL,
        mfa_expires_at DATETIME DEFAULT NULL,
        mfa_temp_token VARCHAR(100) DEFAULT NULL,
        mfa_grace_until DATETIME DEFAULT NULL,
        mfa_secret VARCHAR(100) DEFAULT NULL,
        reset_token VARCHAR(100) DEFAULT NULL,
        reset_expires_at DATETIME DEFAULT NULL,
        failed_attempts INT DEFAULT 0,
        lock_until DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2.5 Automatically create s1_applications table if it doesn't exist
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS s1_applications (
      id INT AUTO_INCREMENT PRIMARY KEY,
      surname VARCHAR(100) NOT NULL,
      firstname VARCHAR(100) NOT NULL,
      othernames VARCHAR(100) DEFAULT NULL,
      sex VARCHAR(10) NOT NULL,
      dob DATE NOT NULL,
      age INT NOT NULL,
      religion VARCHAR(50) NOT NULL,
      passport_photo VARCHAR(255) NOT NULL,
      former_school VARCHAR(150) NOT NULL,
      index_number VARCHAR(50) NOT NULL UNIQUE,
      english VARCHAR(10) NOT NULL,
      mathematics VARCHAR(10) NOT NULL,
      sst VARCHAR(10) NOT NULL,
      science VARCHAR(10) NOT NULL,
      aggregates INT NOT NULL,
      division VARCHAR(20) NOT NULL,
      result_slip VARCHAR(255) NOT NULL,
      parent_name VARCHAR(150) NOT NULL,
      parent_nin VARCHAR(50) NOT NULL,
      parent_phone VARCHAR(50) NOT NULL,
      parent_address VARCHAR(255) NOT NULL,
      parent_email VARCHAR(100) NOT NULL,
      application_status VARCHAR(20) DEFAULT 'pending',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2.6 Automatically create s5_applications table if it doesn't exist
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS s5_applications (
      id INT AUTO_INCREMENT PRIMARY KEY,
      surname VARCHAR(100) NOT NULL,
      firstname VARCHAR(100) NOT NULL,
      othernames VARCHAR(100) DEFAULT NULL,
      sex VARCHAR(10) NOT NULL,
      dob DATE NOT NULL,
      age INT NOT NULL,
      passport_photo VARCHAR(255) NOT NULL,
      former_school VARCHAR(150) NOT NULL,
      uce_index VARCHAR(50) NOT NULL UNIQUE,
      aggregates INT NOT NULL,
      division VARCHAR(20) NOT NULL,
      combination VARCHAR(100) NOT NULL,
      result_slip VARCHAR(255) NOT NULL,
      parent_name VARCHAR(150) NOT NULL,
      parent_nin VARCHAR(50) NOT NULL,
      parent_phone VARCHAR(50) NOT NULL,
      parent_address VARCHAR(255) NOT NULL,
      parent_email VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2.7 Automatically create unified applications table if it doesn't exist
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS applications (
      id INT AUTO_INCREMENT PRIMARY KEY,
      surname VARCHAR(100) NOT NULL,
      firstname VARCHAR(100) NOT NULL,
      othernames VARCHAR(100) DEFAULT NULL,
      sex VARCHAR(10) NOT NULL,
      dob DATE NOT NULL,
      age INT NOT NULL,
      religion VARCHAR(50) DEFAULT '',
      class_of_entry VARCHAR(10) NOT NULL,
      academic_year VARCHAR(20) DEFAULT NULL,
      lin VARCHAR(50) DEFAULT NULL,
      passport_photo VARCHAR(255) DEFAULT NULL,
      former_school VARCHAR(150) NOT NULL,
      index_number VARCHAR(50) DEFAULT NULL,
      english VARCHAR(10) DEFAULT NULL,
      mathematics VARCHAR(10) DEFAULT NULL,
      sst VARCHAR(10) DEFAULT NULL,
      science VARCHAR(10) DEFAULT NULL,
      aggregates INT DEFAULT NULL,
      division VARCHAR(20) DEFAULT NULL,
      combination VARCHAR(100) DEFAULT NULL,
      transfer_reason TEXT DEFAULT NULL,
      result_slip VARCHAR(255) NOT NULL,
      parent_name VARCHAR(150) NOT NULL,
      parent_nin VARCHAR(50) NOT NULL,
      parent_phone VARCHAR(50) NOT NULL,
      parent_address VARCHAR(255) NOT NULL,
      parent_email VARCHAR(100) NOT NULL,
      application_status VARCHAR(20) DEFAULT 'pending',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 3. Auto-migration alter queries for existing tables
    $colsToCheck = [
        'firstname'            => "ALTER TABLE admin_users ADD firstname VARCHAR(100) DEFAULT '' AFTER id",
        'lastname'             => "ALTER TABLE admin_users ADD lastname VARCHAR(100) DEFAULT '' AFTER firstname",
        'email'                => "ALTER TABLE admin_users ADD email VARCHAR(100) DEFAULT '' AFTER lastname",
        'roles_json'           => "ALTER TABLE admin_users ADD roles_json TEXT DEFAULT NULL AFTER role",
        'must_change_password' => "ALTER TABLE admin_users ADD must_change_password TINYINT DEFAULT 0 AFTER password",
        'mfa_enabled'          => "ALTER TABLE admin_users ADD mfa_enabled TINYINT DEFAULT 0 AFTER roles_json",
        'mfa_code'             => "ALTER TABLE admin_users ADD mfa_code VARCHAR(20) DEFAULT NULL AFTER mfa_enabled",
        'mfa_expires_at'       => "ALTER TABLE admin_users ADD mfa_expires_at DATETIME DEFAULT NULL AFTER mfa_code",
        'mfa_temp_token'       => "ALTER TABLE admin_users ADD mfa_temp_token VARCHAR(100) DEFAULT NULL AFTER mfa_expires_at",
        'mfa_grace_until'      => "ALTER TABLE admin_users ADD mfa_grace_until DATETIME DEFAULT NULL AFTER mfa_temp_token",
        'mfa_secret'           => "ALTER TABLE admin_users ADD mfa_secret VARCHAR(100) DEFAULT NULL AFTER mfa_grace_until",
        'reset_token'          => "ALTER TABLE admin_users ADD reset_token VARCHAR(100) DEFAULT NULL AFTER mfa_secret",
        'reset_expires_at'     => "ALTER TABLE admin_users ADD reset_expires_at DATETIME DEFAULT NULL AFTER reset_token",
        'failed_attempts'      => "ALTER TABLE admin_users ADD failed_attempts INT DEFAULT 0",
        'lock_until'           => "ALTER TABLE admin_users ADD lock_until DATETIME DEFAULT NULL",
    ];

    foreach ($colsToCheck as $colName => $alterQuery) {
        try {
            $checkCol = $pdo->query("SHOW COLUMNS FROM admin_users LIKE '$colName'");
            if ($checkCol && $checkCol->rowCount() === 0) {
                $pdo->exec($alterQuery);
            }
        } catch (Exception $e) {}
    }

    // Auto-create and migrate system_roles table
    try {
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_key VARCHAR(50) NOT NULL UNIQUE,
            role_name VARCHAR(100) NOT NULL,
            description TEXT DEFAULT NULL,
            can_manage_settings TINYINT DEFAULT 0,
            can_manage_admission_policies TINYINT DEFAULT 0,
            can_admit_students TINYINT DEFAULT 0,
            can_reject_students TINYINT DEFAULT 0,
            can_view_reports TINYINT DEFAULT 1,
            can_export_data TINYINT DEFAULT 0,
            can_manage_users TINYINT DEFAULT 0,
            can_manage_roles TINYINT DEFAULT 0,
            can_take_application_actions TINYINT DEFAULT 0,
            can_view_logs TINYINT DEFAULT 0,
            is_system TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $chkRoleCol = $pdo->query("SHOW COLUMNS FROM system_roles LIKE 'can_take_application_actions'");
        if ($chkRoleCol && $chkRoleCol->rowCount() === 0) {
            $pdo->exec("ALTER TABLE system_roles ADD can_take_application_actions TINYINT DEFAULT 1 AFTER can_manage_roles");
        }

        // Seed default roles if system_roles is empty
        $chkRoleCount = $pdo->query("SELECT COUNT(*) FROM system_roles");
        if ($chkRoleCount && $chkRoleCount->fetchColumn() == 0) {
            $defaultRoles = [
                ['super_admin', 'Super Administrator', 'Full root access to all system modules, configurations, settings, security, and roles.', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
                ['admin', 'System Administrator', 'Administrative control over admissions, user accounts, system configuration, and data exports.', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
                ['admissions_registry', 'Admissions Registry', 'Manages student dossiers, processes intake decisions (admit/reject), and tracks student admissions.', 0, 1, 1, 1, 1, 1, 0, 0, 1, 0, 1],
                ['bursar', 'Bursar & Finance Office', 'Monitors admitted student rosters, financial reporting, and exports fee assessment lists.', 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 1],
                ['viewer', 'Read-Only Observer', 'Strict read-only oversight across application rosters and statistical summaries.', 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1],
                ['ict_admin', 'ICT Officer', 'Manages IT configurations, user management, and system auditing.', 1, 0, 0, 0, 1, 1, 1, 0, 0, 1, 1]
            ];
            $insRole = $pdo->prepare("INSERT INTO system_roles (role_key, role_name, description, can_manage_settings, can_manage_admission_policies, can_admit_students, can_reject_students, can_view_reports, can_export_data, can_manage_users, can_manage_roles, can_take_application_actions, can_view_logs, is_system) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($defaultRoles as $r) {
                $insRole->execute($r);
            }
        }
    } catch (Exception $e) {}

    // Adjust S5 combination size if it is limited to VARCHAR(20)
    $checkComb = $pdo->query("SHOW COLUMNS FROM s5_applications LIKE 'combination'");
    $combMeta = $checkComb->fetch(PDO::FETCH_ASSOC);
    if ($combMeta && strpos($combMeta['Type'], 'varchar(20)') !== false) {
        $pdo->exec("ALTER TABLE s5_applications MODIFY combination VARCHAR(100) NOT NULL");
    }

    // Ensure required columns exist across applications, s1_applications, and s5_applications
    foreach (['applications', 's1_applications', 's5_applications'] as $t) {
        try {
            $checkTbl = $pdo->query("SHOW TABLES LIKE '$t'");
            if ($checkTbl && $checkTbl->rowCount() > 0) {
                // Ensure application_status exists
                $checkStatus = $pdo->query("SHOW COLUMNS FROM $t LIKE 'application_status'");
                if ($checkStatus && $checkStatus->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE $t ADD application_status VARCHAR(50) DEFAULT 'pending'");
                }
                // Ensure admitted_by exists
                $checkAdmittedBy = $pdo->query("SHOW COLUMNS FROM $t LIKE 'admitted_by'");
                if ($checkAdmittedBy && $checkAdmittedBy->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE $t ADD admitted_by VARCHAR(150) DEFAULT NULL");
                }
                // Ensure created_at exists
                $checkCreatedAt = $pdo->query("SHOW COLUMNS FROM $t LIKE 'created_at'");
                if ($checkCreatedAt && $checkCreatedAt->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE $t ADD created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                }
                // Ensure admitted_at exists
                $checkAdmittedAt = $pdo->query("SHOW COLUMNS FROM $t LIKE 'admitted_at'");
                if ($checkAdmittedAt && $checkAdmittedAt->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE $t ADD admitted_at DATETIME DEFAULT NULL");
                }
                // Ensure academic year, LIN, and extended subfields exist
                $addressFields = [
                    'academic_year'             => "VARCHAR(20) DEFAULT NULL",
                    'lin'                       => "VARCHAR(50) DEFAULT NULL",
                    'parent_village'            => "VARCHAR(100) DEFAULT NULL",
                    'parent_parish'             => "VARCHAR(100) DEFAULT NULL",
                    'parent_subcounty'          => "VARCHAR(100) DEFAULT NULL",
                    'parent_district'           => "VARCHAR(100) DEFAULT NULL",
                    'parent_id_file'            => "VARCHAR(255) DEFAULT NULL",
                    'uce_project_work'          => "VARCHAR(50) DEFAULT NULL",
                    'uce_subjects_json'         => "TEXT DEFAULT NULL",
                    's6_former_school'          => "VARCHAR(150) DEFAULT NULL",
                    's6_report_file'            => "VARCHAR(255) DEFAULT NULL",
                    'is_auto_admitted'          => "TINYINT DEFAULT 0",
                    'auto_admit_review_status'  => "VARCHAR(50) DEFAULT NULL",
                    'reviewed_by'               => "VARCHAR(150) DEFAULT NULL",
                    'reviewed_at'               => "DATETIME DEFAULT NULL"
                ];
                foreach ($addressFields as $fld => $fldDef) {
                    $chk = $pdo->query("SHOW COLUMNS FROM $t LIKE '$fld'");
                    if ($chk && $chk->rowCount() === 0) {
                        $pdo->exec("ALTER TABLE $t ADD $fld $fldDef");
                    }
                }
                // Allow passport_photo and parent_nin to be NULL for schools skipping online photo / parent ID
                try {
                    $pdo->exec("ALTER TABLE $t MODIFY passport_photo VARCHAR(255) DEFAULT NULL");
                    $pdo->exec("ALTER TABLE $t MODIFY parent_nin VARCHAR(50) DEFAULT NULL");
                } catch (Exception $e) {}
            }
        } catch (Exception $e) {}
    }

    // Auto-backfill academic_year for existing records based on active academic_year setting
    try {
        $curYear = getSetting($pdo, 'academic_year', date('Y'));
        foreach (['applications', 's1_applications', 's5_applications'] as $t) {
            $checkTbl = $pdo->query("SHOW TABLES LIKE '$t'");
            if ($checkTbl && $checkTbl->rowCount() > 0) {
                $checkYearCol = $pdo->query("SHOW COLUMNS FROM $t LIKE 'academic_year'");
                if ($checkYearCol && $checkYearCol->rowCount() > 0) {
                    $pdo->exec("UPDATE $t SET academic_year = '$curYear' WHERE academic_year IS NULL OR academic_year = ''");
                }
            }
        }
    } catch (Exception $e) {}

    // Auto-backfill admitted_by and admitted_at for historical records
    try {
        $pdo->exec("
            UPDATE applications a 
            JOIN admin_logs l ON l.action LIKE '%Admit%' AND l.details LIKE CONCAT('%', a.firstname, '%', a.surname, '%')
            SET a.admitted_by = l.username, a.admitted_at = l.created_at
            WHERE a.application_status = 'admitted' AND (a.admitted_by IS NULL OR a.admitted_by = '')
        ");
        $pdo->exec("UPDATE applications SET admitted_by = 'Administrator' WHERE application_status = 'admitted' AND (admitted_by IS NULL OR admitted_by = '')");
        $pdo->exec("UPDATE applications SET admitted_at = created_at WHERE application_status = 'admitted' AND admitted_at IS NULL");
        $pdo->exec("UPDATE s1_applications SET admitted_at = created_at WHERE application_status = 'admitted' AND admitted_at IS NULL");
        $pdo->exec("UPDATE s5_applications SET admitted_at = created_at WHERE application_status = 'admitted' AND admitted_at IS NULL");
        $pdo->exec("UPDATE applications SET is_auto_admitted = 1, auto_admit_review_status = 'needs_review' WHERE (admitted_by LIKE '%AI%' OR admitted_by LIKE '%Auto%') AND (is_auto_admitted IS NULL OR is_auto_admitted = 0)");
    } catch (Exception $e) {}

    // 4. Seed default super_admin account if empty
    $checkUsers = $pdo->query("SELECT COUNT(*) FROM admin_users");
    if ($checkUsers->fetchColumn() == 0) {
        $hashed = password_hash('User@2026@', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO admin_users (firstname, lastname, email, username, password, role) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute(['System', 'Administrator', 'admin@mutolere.sc.ug', 'admin', $hashed, 'super_admin']);
    }

    // 5. Automatically create system_settings table if it doesn't exist
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 6. Auto-seed settings keys
    $defaultSettings = [
        'school_name'           => 'St. Paul\'s Mutolere Secondary School',
        'school_short_name'     => 'St. Paul\'s Mutolere SS',
        'school_motto'          => 'Education for Service',
        'school_logo'           => 'https://www.ncedges.com/wp-content/uploads/2026/02/mutosp-logo.png',
        'school_address'        => 'P.O. Box 12, Kisoro - Uganda',
        'school_phone'          => '+256 790 001 631',
        'school_email'          => 'info@ncedges.com',
        'school_website'        => 'www.mutolere.sc.ug',
        'portal_base_url'       => '',
        'head_teacher_name'     => 'MR. MBONYE CHARLES',
        'head_teacher_title'    => 'Head Teacher',
        'school_code'           => '011',
        'academic_year'         => '2026',
        'school_gender_type'    => 'mixed', // 'mixed' | 'boys' | 'girls'
        'primary_color'         => '#0c4a24',
        'accent_color'          => '#b58d2f',
        'admin_email'           => 'info@ncedges.com,cmbonye65@gmail.com,mutoleresp@gmail.com,musareona146@gmail.com',
        'admission_s1_start_date'    => '2026-01-05',
        'admission_s1_end_date'      => '2026-12-31',
        'admission_s5_start_date'    => '2026-01-10',
        'admission_s5_end_date'      => '2026-12-31',
        'admission_trans_start_date' => '2026-01-15',
        'admission_trans_end_date'   => '2026-12-31',
        'admission_s1_passmark'      => '28',
        'admission_s5_passmark'      => '42',
        'reporting_date_s1'          => 'Monday, 2nd February 2026',
        'reporting_date_s5'          => 'Monday, 9th February 2026',
        'reporting_date_trans'       => 'Monday, 2nd February 2026',
        'school_requirements'        => "1. School Uniform (Available at School Bursar's Office)\n2. Black leather shoes and white socks / stockings\n3. 10 Counter books (4-quire) and mathematical set\n4. Ream of photocopy paper (Rotatrim / Double A)\n5. 1 Hoe with handle and 1 Slasher\n6. Standard 2.5-inch Mattress, 2 pairs of bedsheets, and a blanket\n7. Metallic suitcase / trunk with sturdy padlock\n8. Passport photos (4 copies) and original PLE / UCE result slip",
        'doc_school_rules'           => '',
        'doc_admission_circular'     => '',
        'doc_medical_form'           => '',
        'admission_letter_custom_note' => 'Please note: This provisional admission offer is subject to physical verification of original UNEB result slips and birth certificate upon arrival on the reporting date. Fees once paid are non-refundable.',
        // O-Level Classes (S1 - S4)
        'fees_s1_tuition'       => '550000',
        'fees_s1_admission'     => '100000',
        'fees_s1_functional'    => '150000',
        'fees_s2_tuition'       => '550000',
        'fees_s2_admission'     => '100000',
        'fees_s2_functional'    => '150000',
        'fees_s3_tuition'       => '560000',
        'fees_s3_admission'     => '100000',
        'fees_s3_functional'    => '150000',
        'fees_s4_tuition'       => '580000',
        'fees_s4_admission'     => '100000',
        'fees_s4_functional'    => '150000',
        // A-Level Classes (S5 - S6)
        'fees_s5_tuition'       => '600000',
        'fees_s5_admission'     => '120000',
        'fees_s5_functional'    => '180000',
        'fees_s6_tuition'       => '620000',
        'fees_s6_admission'     => '120000',
        'fees_s6_functional'    => '180000',
        'fees_trans_tuition'    => '570000',
        'fees_trans_admission'  => '110000',
        'fees_trans_functional' => '170000',
        'fees_structure_matrix' => '',
        // SMTP Gateway Settings
        'smtp_enabled'          => '1',
        'smtp_host'             => 'mail-gw.ncedges.com',
        'smtp_port'             => '587',
        'smtp_encryption'       => 'tls',
        'smtp_auth'             => '1',
        'smtp_username'         => 'admissions-mutoleress@ncedges.com',
        'smtp_password'         => 'Uganda@23',
        'smtp_from_email'       => 'admissions-mutoleress@ncedges.com',
        'smtp_from_name'        => 'Admissions Office',
        // Head Teacher E-Signature
        'head_teacher_signature' => '',
    ];

    $checkSet = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
    $insertSet = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaultSettings as $k => $v) {
        $checkSet->execute([$k]);
        if ($checkSet->fetchColumn() == 0) {
            $insertSet->execute([$k, $v]);
        }
    }

    // Auto-clean any legacy multi-byte en-dash or mojibake in system_settings
    try {
        $pdo->exec("UPDATE system_settings SET setting_value = REPLACE(REPLACE(setting_value, '–', '-'), 'â€“', '-') WHERE setting_value LIKE '%–%' OR setting_value LIKE '%â€“%'");
    } catch (Exception $e) {}

} catch (Exception $e) {
    if (php_sapi_name() === 'cli' && !isset($_SERVER['HTTP_HOST'])) {
        $pdo = null;
    } else {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Extract True Client Machine IP Address across Cloudflare, Reverse Proxies, and Direct Connections
if (!function_exists('getClientIp')) {
    function getClientIp() {
        // Priority list of headers populated by Cloudflare, reverse proxies, and load balancers
        $headers = [
            'HTTP_CF_CONNECTING_IP',   // Cloudflare verified client machine IP
            'HTTP_X_REAL_IP',          // Nginx / HAProxy / Caddy reverse proxy client IP
            'HTTP_CLIENT_IP',          // Direct client IP header
            'HTTP_X_FORWARDED_FOR',    // Standard proxy forwarded list (client, proxy1, proxy2...)
            'HTTP_X_FORWARDED',        // Proxy header
            'HTTP_X_CLUSTER_CLIENT_IP',// Cluster load balancer client IP
            'HTTP_FORWARDED_FOR',      // Alternative forwarded
            'HTTP_FORWARDED',          // RFC 7239 standard Forwarded
            'REMOTE_ADDR'              // Direct socket connection fallback
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // If comma-separated, the first IP is the original user client machine
                $rawList = explode(',', (string)$_SERVER[$header]);
                foreach ($rawList as $rawIp) {
                    $ip = trim($rawIp);
                    // Handle IPv4 with port (e.g. 192.168.1.10:8080)
                    if (strpos($ip, ':') !== false && substr_count($ip, ':') === 1) {
                        $ip = explode(':', $ip)[0];
                    }
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

// Activity Logging Helper Function (Captures Real Client Machine IP)
if (!function_exists('logActivity')) {
    function logActivity($pdo, $username, $action, $details = '') {
        $ip = getClientIp();
        try {
            $stmt = $pdo->prepare("INSERT INTO admin_logs (username, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $action, $details, $ip]);
        } catch (Exception $e) {
            // Fail silently so database log glitches do not block operations
        }
    }
}

// System Settings Helper Function
if (!function_exists('getSetting')) {
    function getSetting($pdo, $key, $default = '', $reload = false) {
        if (!$pdo) {
            return $default;
        }
        static $settings = null;
        if ($settings === null || $reload) {
            try {
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
                $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Exception $e) {
                $settings = [];
            }
        }
        return (isset($settings[$key]) && $settings[$key] !== '') ? $settings[$key] : $default;
    }
}

// Retrieve all settings as associative key-value array
if (!function_exists('getAllSettings')) {
    function getAllSettings($pdo, $reload = false) {
        if (!$pdo) {
            return [];
        }
        static $allSettings = null;
        if ($allSettings === null || $reload) {
            try {
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
                $allSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Exception $e) {
                $allSettings = [];
            }
        }
        return $allSettings ?: [];
    }
}

// Portal Base URL Helper (e.g. "https://example.com" without trailing slash)
if (!function_exists('getBaseUrl')) {
    function getBaseUrl($pdo = null) {
        if ($pdo) {
            $customUrl = trim(getSetting($pdo, 'portal_base_url', ''));
            if (!empty($customUrl)) {
                return rtrim($customUrl, '/');
            }
        }
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return rtrim($protocol . $host, '/');
    }
}

// Dynamic Academic Year Helper (picks intake year from configured admission dates or setting)
if (!function_exists('getAcademicYear')) {
    function getAcademicYear($pdo = null, $class = null) {
        if ($pdo) {
            $yearSetting = getSetting($pdo, 'academic_year', '');
            if (!empty($yearSetting) && is_numeric($yearSetting) && (int)$yearSetting > 2000) {
                return trim((string)$yearSetting);
            }
            $dateKey = 'admission_s1_start_date';
            if ($class) {
                $norm = strtoupper(trim((string)$class));
                if (in_array($norm, ['S5', 'SENIOR FIVE', '5', '05'])) {
                    $dateKey = 'admission_s5_start_date';
                } elseif (!in_array($norm, ['S1', 'SENIOR ONE', '1', '01'])) {
                    $dateKey = 'admission_trans_start_date';
                }
            }
            $intakeDate = getSetting($pdo, $dateKey, '');
            if (!empty($intakeDate)) {
                $parsedYear = date('Y', strtotime($intakeDate));
                if ($parsedYear && (int)$parsedYear > 2000) {
                    return $parsedYear;
                }
            }
            // Fallback to S1 intake date if specified category has no date
            $s1Date = getSetting($pdo, 'admission_s1_start_date', '');
            if (!empty($s1Date)) {
                $parsedYear = date('Y', strtotime($s1Date));
                if ($parsedYear && (int)$parsedYear > 2000) {
                    return $parsedYear;
                }
            }
            if (!empty($yearSetting)) {
                return $yearSetting;
            }
        }
        return date('Y');
    }
}

// Academic Year Short 2-digit format (e.g. "26" or "27" for student numbers)
if (!function_exists('getAcademicYearShort')) {
    function getAcademicYearShort($pdo = null, $class = null) {
        $year = getAcademicYear($pdo, $class);
        return substr(trim($year), -2);
    }
}

// Admission Intake Status & Deadline Checker
if (!function_exists('getAdmissionStatus')) {
    function getAdmissionStatus($pdo, $class = 'S1') {
        $norm = strtoupper(trim((string)$class));
        if ($norm === 'S1' || $norm === 'SENIOR ONE' || $norm === '01') {
            $cat = 's1';
            $label = 'Senior One (S1)';
        } elseif ($norm === 'S5' || $norm === 'SENIOR FIVE' || $norm === '05') {
            $cat = 's5';
            $label = 'Senior Five (S5)';
        } else {
            $cat = 'trans';
            $label = 'Transfers (S2-S6)';
        }

        $startDateStr = $pdo ? getSetting($pdo, "admission_{$cat}_start_date", '') : '';
        $endDateStr   = $pdo ? getSetting($pdo, "admission_{$cat}_end_date", '') : '';

        $currentYear = date('Y');
        if (empty($startDateStr)) {
            $startDateStr = $currentYear . '-01-01';
        }
        if (empty($endDateStr)) {
            $endDateStr = $currentYear . '-12-31';
        }

        $startTime = strtotime($startDateStr . ' 00:00:00');
        $endTime   = strtotime($endDateStr . ' 23:59:59');
        $now       = time();

        $isOpen = false;
        $status = 'open';
        $badgeText = '';

        if ($now < $startTime) {
            $isOpen = false;
            $status = 'upcoming';
            $badgeText = 'Opens on ' . date('M j, Y', $startTime);
        } elseif ($now > $endTime) {
            $isOpen = false;
            $status = 'closed';
            $badgeText = 'Admissions Closed';
        } else {
            $isOpen = true;
            $status = 'open';
            $badgeText = 'Admissions Open (Deadline: ' . date('M j, Y', $endTime) . ')';
        }

        return [
            'category'        => $cat,
            'class_label'     => $label,
            'is_open'         => $isOpen,
            'status'          => $status, // 'open' | 'closed' | 'upcoming'
            'badge_text'      => $badgeText,
            'start_date'      => $startDateStr,
            'end_date'        => $endDateStr,
            'formatted_start' => date('M j, Y', $startTime),
            'formatted_end'   => date('M j, Y', $endTime),
            'year'            => date('Y', $startTime)
        ];
    }
}

// Primary & Accent Brand Color Helpers
if (!function_exists('getPrimaryColor')) {
    function getPrimaryColor($pdo = null) {
        return $pdo ? getSetting($pdo, 'primary_color', '#0c4a24') : '#0c4a24';
    }
}

if (!function_exists('getAccentColor')) {
    function getAccentColor($pdo = null) {
        return $pdo ? getSetting($pdo, 'accent_color', '#b58d2f') : '#b58d2f';
    }
}

// Official Fees Structure Matrix Helpers (Dynamic & Flexible Table Format)
if (!function_exists('getDefaultFeesStructureMatrix')) {
    function getDefaultFeesStructureMatrix($academicYear = '2026') {
        return [
            'title' => "Fees structure for " . $academicYear,
            'columns' => [
                ['id' => 'admission_fee',    'label' => 'ADMISSION FEE',           'sub' => '(paid once)'],
                ['id' => 'uniforms',         'label' => 'UNIFORMS',                'sub' => '(paid once)'],
                ['id' => 'vocational',       'label' => 'VOCATIONAL SKILLS',       'sub' => '(termly)'],
                ['id' => 'tuition_boarding', 'label' => 'TUITION AND BOARDING',   'sub' => '(termly)'],
                ['id' => 'tuition_day',      'label' => 'TUITION FOR DAY SCHOLARS','sub' => '(termly)'],
                ['id' => 'functional',       'label' => 'FUNCTIONAL FEES',         'sub' => '(termly)'],
            ],
            'rows' => [
                [
                    'class' => 'S.1',
                    'admission_fee' => '100,000/=',
                    'uniforms' => '400,000/=',
                    'vocational' => '100,000/=',
                    'tuition_boarding' => '1,265,000/=',
                    'tuition_day' => '750,000/=',
                    'functional' => '150,000/=',
                ],
                [
                    'class' => 'S.2',
                    'admission_fee' => '100,000/=',
                    'uniforms' => '400,000/=',
                    'vocational' => '100,000/=',
                    'tuition_boarding' => '1,265,000/=',
                    'tuition_day' => '750,000/=',
                    'functional' => '100,000/=',
                ],
                [
                    'class' => 'S.3',
                    'admission_fee' => '100,000/=',
                    'uniforms' => '400,000/=',
                    'vocational' => '100,000/=',
                    'tuition_boarding' => '1,265,000/=',
                    'tuition_day' => '750,000/=',
                    'functional' => '100,000/=',
                ],
                [
                    'class' => 'S.4',
                    'admission_fee' => '100,000/=',
                    'uniforms' => '400,000/=',
                    'vocational' => '',
                    'tuition_boarding' => '2,065,000/=',
                    'tuition_day' => '750,000/=',
                    'functional' => '100,000/=',
                ],
                [
                    'class' => 'S.5',
                    'admission_fee' => '100,000/=',
                    'uniforms' => '400,000/=',
                    'vocational' => '100,000/=',
                    'tuition_boarding' => '1,265,000/=',
                    'tuition_day' => '750,000/=',
                    'functional' => '150,000/=',
                ],
                [
                    'class' => 'S.6',
                    'admission_fee' => '100,000/=',
                    'uniforms' => '400,000/=',
                    'vocational' => '',
                    'tuition_boarding' => '1,965,000/=',
                    'tuition_day' => '750,000/=',
                    'functional' => '100,000/=',
                ],
            ]
        ];
    }
}

if (!function_exists('getFeesStructureMatrix')) {
    function getFeesStructureMatrix($pdo = null) {
        $year = $pdo ? getAcademicYear($pdo) : date('Y');
        if (empty($year)) $year = '2027';
        if ($pdo) {
            $raw = getSetting($pdo, 'fees_structure_matrix', '');
            if (!empty($raw)) {
                $decoded = is_array($raw) ? $raw : json_decode($raw, true);
                if (is_array($decoded) && !empty($decoded['columns']) && !empty($decoded['rows'])) {
                    // Dynamically capture academic year from the database
                    $decoded['title'] = "Fees structure for " . $year;
                    return $decoded;
                }
            }
        }
        return getDefaultFeesStructureMatrix($year);
    }
}

// Render dynamic brand theme CSS variables and Tailwind configuration
if (!function_exists('renderThemeConfig')) {
    function renderThemeConfig($pdo = null) {
        $primary = htmlspecialchars(getPrimaryColor($pdo));
        $accent  = htmlspecialchars(getAccentColor($pdo));
        return "
        <script data-cfasync=\"false\">
        tailwind = window.tailwind || {};
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            500: '{$primary}',
                            600: '{$primary}',
                            700: '{$primary}',
                            800: '{$primary}',
                            900: '#031b0b',
                        },
                        gold: {
                            400: '{$accent}',
                            500: '{$accent}',
                            600: '{$accent}',
                            700: '{$accent}',
                        }
                    }
                }
            }
        };
        </script>
        <style>
        :root {
            --primary-color: {$primary};
            --primary-light: {$primary};
            --primary-dark: {$primary};
            --gold-color: {$accent};
            --gold-light: {$accent};
            --gold-dark: {$accent};
        }
        .btn-brand {
            background-color: var(--primary-color) !important;
            color: #ffffff !important;
        }
        .btn-brand:hover {
            opacity: 0.92;
        }
        .btn-gold {
            background-color: var(--gold-color) !important;
            color: #ffffff !important;
        }
        .btn-gold:hover {
            opacity: 0.92;
        }
        .text-brand {
            color: var(--primary-color) !important;
        }
        .text-gold {
            color: var(--gold-color) !important;
        }
        .border-brand {
            border-color: var(--primary-color) !important;
        }
        .border-gold {
            border-color: var(--gold-color) !important;
        }
        .bg-brand {
            background-color: var(--primary-color) !important;
        }
        .bg-gold {
            background-color: var(--gold-color) !important;
        }
        /* Uniform, modern select dropdown styling with SVG arrow */
        select, .form-select-custom {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background-image: url('data:image/svg+xml;utf8,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'2\' stroke=\'%23475569\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M19.5 8.25l-7.5 7.5-7.5-7.5\'/%3E%3C/svg%3E') !important;
            background-repeat: no-repeat !important;
            background-position: right 0.75rem center !important;
            background-size: 1rem !important;
            padding-right: 2.5rem !important;
            font-family: 'Outfit', sans-serif !important;
        }

        /* Modern styled calendar date picker */
        input[type='date'] {
            position: relative;
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 0.625rem;
            padding: 0.45rem 0.75rem;
            font-size: 0.8125rem;
            color: #1e293b;
            font-weight: 500;
            outline: none;
            transition: all 0.15s ease-in-out;
            cursor: pointer;
            font-family: 'Outfit', sans-serif !important;
        }
        input[type='date']:hover {
            border-color: #94a3b8;
        }
        input[type='date']:focus {
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 3px rgba(12, 74, 36, 0.15);
        }
        input[type='date']::-webkit-calendar-picker-indicator {
            cursor: pointer;
            border-radius: 0.375rem;
            margin-right: -2px;
            opacity: 0.75;
            padding: 2px 4px;
            filter: invert(0.2) sepia(0.8) hue-rotate(90deg);
            transition: all 0.15s;
        }
        input[type='date']::-webkit-calendar-picker-indicator:hover {
            opacity: 1;
            transform: scale(1.1);
        }
        </style>
        ";
    }
}

// Global Currency Formatter: Thousand separator every 3 digits
if (!function_exists('formatMoney')) {
    function formatMoney($amount) {
        if ($amount === null || $amount === '') return '0';
        $clean = preg_replace('/[^\d\.]/', '', (string)$amount);
        return number_format((float)$clean);
    }
}

// Convert Hex Color to RGB Array (e.g. for TCPDF)
if (!function_exists('hex2rgb')) {
    function hex2rgb($hex, $default = [12, 74, 36]) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 6) {
            return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        } elseif (strlen($hex) === 3) {
            return [hexdec(str_repeat(substr($hex, 0, 1), 2)), hexdec(str_repeat(substr($hex, 1, 1), 2)), hexdec(str_repeat(substr($hex, 2, 1), 2))];
        }
        return $default;
    }
}

// School Logo source for HTML/web display
if (!function_exists('getSchoolLogoSrc')) {
    function getSchoolLogoSrc($pdo = null, $relativePrefix = '') {
        $baseDir = realpath(__DIR__ . '/..');
        $logo = $pdo ? getSetting($pdo, 'school_logo', '') : '';

        // 1. If explicitly set to a local relative path that exists
        if (!empty($logo) && strpos($logo, 'http') !== 0) {
            $fullPath = $baseDir . '/' . ltrim($logo, '/');
            if (file_exists($fullPath)) {
                return $relativePrefix . ltrim($logo, '/') . '?v=' . filemtime($fullPath);
            }
        }

        // 2. Check for local custom logo in images/
        $customLocal = 'images/logo_custom.png';
        $fullCustom = $baseDir . '/' . $customLocal;
        if (file_exists($fullCustom)) {
            return $relativePrefix . $customLocal . '?v=' . filemtime($fullCustom);
        }

        // 3. If explicitly set to an absolute URL
        if (!empty($logo) && (strpos($logo, 'http://') === 0 || strpos($logo, 'https://') === 0)) {
            return $logo;
        }

        // 4. Fallback to default local logo
        $defaultLocal = 'images/logo_default.png';
        if (file_exists($baseDir . '/' . $defaultLocal)) {
            return $relativePrefix . $defaultLocal . '?v=' . filemtime($baseDir . '/' . $defaultLocal);
        }

        return $relativePrefix . 'images/logo_custom.png';
    }
}

// School Gender Classification Helper ('mixed' | 'boys' | 'girls')
if (!function_exists('getSchoolGenderType')) {
    function getSchoolGenderType($pdo = null) {
        return strtolower(trim(getSetting($pdo, 'school_gender_type', 'mixed')));
    }
}

// Admissions Pass Mark (Cutoff Aggregates) Helper
if (!function_exists('getPassMark')) {
    function getPassMark($pdo, $class = 'S1') {
        $norm = strtoupper(trim((string)$class));
        if ($norm === 'S1' || $norm === 'SENIOR ONE' || $norm === '01') {
            $val = getSetting($pdo, 'admission_s1_passmark', '28');
            return ($val !== '' && is_numeric($val)) ? (int)$val : null;
        } elseif ($norm === 'S5' || $norm === 'SENIOR FIVE' || $norm === '05') {
            $val = getSetting($pdo, 'admission_s5_passmark', '42');
            return ($val !== '' && is_numeric($val)) ? (int)$val : null;
        }
        // Transfers (S2, S3, S4, S6) do not have cut off points
        return null;
    }
}

// Reporting Date Helper
if (!function_exists('getReportingDate')) {
    function getReportingDate($pdo, $class = 'S1') {
        $norm = strtoupper(trim((string)$class));
        if ($norm === 'S1' || $norm === 'SENIOR ONE' || $norm === '01') {
            $val = getSetting($pdo, 'reporting_date_s1', '2026-02-02');
        } elseif ($norm === 'S5' || $norm === 'SENIOR FIVE' || $norm === '05') {
            $val = getSetting($pdo, 'reporting_date_s5', '2026-02-09');
        } else {
            $val = getSetting($pdo, 'reporting_date_trans', '2026-02-02');
        }
        $valTrim = trim((string)$val);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valTrim)) {
            $ts = strtotime($valTrim);
            if ($ts !== false) {
                return date('l, jS F Y', $ts);
            }
        }
        return $val;
    }
}

// Resolve Candidate Passport Photo Server Filesystem Path
if (!function_exists('getStudentPhotoFilePath')) {
    function getStudentPhotoFilePath($photoPath, $rootDir = null) {
        if (empty($photoPath)) return null;
        if (!$rootDir) {
            $rootDir = realpath(__DIR__ . '/..');
        }
        $clean = ltrim(trim($photoPath), './');
        $filename = basename($clean);

        $candidates = [
            $rootDir . '/' . $clean,
            $rootDir . '/uploads/passports/' . $filename,
            $rootDir . '/uploads/passports/' . urldecode($filename),
            $rootDir . '/form-one/uploads/passports/' . $filename,
            $rootDir . '/form-five/uploads/passports/' . $filename,
            $rootDir . '/form-one/' . $clean,
            $rootDir . '/form-five/' . $clean,
        ];

        $found = null;
        foreach ($candidates as $cand) {
            if (file_exists($cand) && is_file($cand)) {
                $found = $cand;
                break;
            }
        }

        if ($found) {
            // TCPDF does not support .webp natively. Auto-convert to cached .jpg if webp
            $ext = strtolower(pathinfo($found, PATHINFO_EXTENSION));
            if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
                $jpgCached = $found . '.jpg';
                if (!file_exists($jpgCached) || filemtime($jpgCached) < filemtime($found)) {
                    $img = @imagecreatefromwebp($found);
                    if ($img) {
                        @imagejpeg($img, $jpgCached, 90);
                    }
                }
                if (file_exists($jpgCached)) {
                    return $jpgCached;
                }
            }
            return $found;
        }
        return null;
    }
}

// Ensure TCPDF temp path is writable
if (!defined('K_PATH_CACHE')) {
    define('K_PATH_CACHE', '/tmp/');
}

// Generate/Retrieve Circular Student Passport Photo for TCPDF
if (!function_exists('getCircularStudentPhotoFilePath')) {
    function getCircularStudentPhotoFilePath($photoPath, $rootDir = null) {
        if (!$rootDir) {
            $rootDir = realpath(__DIR__ . '/..');
        }
        $cacheDir = $rootDir . '/uploads/passports/circle_cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $orig = getStudentPhotoFilePath($photoPath, $rootDir);
        if (!$orig || !file_exists($orig)) {
            // Generate clean circular silhouette avatar placeholder
            $placeholderFile = $cacheDir . '/placeholder_circle.png';
            if (!file_exists($placeholderFile)) {
                $pSize = 300;
                $pDst = imagecreatetruecolor($pSize, $pSize);
                imagealphablending($pDst, true);
                imagesavealpha($pDst, true);
                $trans = imagecolorallocatealpha($pDst, 0, 0, 0, 127);
                imagefill($pDst, 0, 0, $trans);

                $pRadius = $pSize / 2;
                $pBg = imagecolorallocate($pDst, 241, 245, 249);
                $pBorder = imagecolorallocate($pDst, 148, 163, 184);

                imagefilledellipse($pDst, (int)$pRadius, (int)$pRadius, $pSize - 4, $pSize - 4, $pBg);
                imageellipse($pDst, (int)$pRadius, (int)$pRadius, $pSize - 4, $pSize - 4, $pBorder);

                $pHead = imagecolorallocate($pDst, 203, 213, 225);
                imagefilledellipse($pDst, (int)$pRadius, (int)($pRadius * 0.75), 70, 70, $pHead);
                imagefilledarc($pDst, (int)$pRadius, (int)($pRadius * 1.55), 140, 120, 180, 360, $pHead, IMG_ARC_PIE);

                @imagepng($pDst, $placeholderFile, 6);
            }
            return file_exists($placeholderFile) ? $placeholderFile : null;
        }

        $cacheFile = $cacheDir . '/circle_' . md5($orig . filemtime($orig)) . '.png';
        if (file_exists($cacheFile)) {
            return $cacheFile;
        }

        // Use GD to crop to circle with transparency
        $info = @getimagesize($orig);
        if (!$info) return $orig;
        $mime = $info['mime'] ?? '';
        $src = null;
        if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
            $src = @imagecreatefromjpeg($orig);
        } elseif ($mime === 'image/png') {
            $src = @imagecreatefrompng($orig);
        } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $src = @imagecreatefromwebp($orig);
        } elseif ($mime === 'image/gif') {
            $src = @imagecreatefromgif($orig);
        }
        if (!$src) return $orig;

        $sw = imagesx($src);
        $sh = imagesy($src);
        $size = min($sw, $sh);
        $srcX = (int)(($sw - $size) / 2);
        $srcY = (int)(($sh - $size) / 2);

        $targetSize = 350;
        $dst = imagecreatetruecolor($targetSize, $targetSize);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);

        $square = imagecreatetruecolor($targetSize, $targetSize);
        imagecopyresampled($square, $src, 0, 0, $srcX, $srcY, $targetSize, $targetSize, $size, $size);

        $radius = $targetSize / 2;
        $borderWidth = 3;
        $borderColor = [100, 116, 139]; // Slate-500 subtle border

        for ($x = 0; $x < $targetSize; $x++) {
            for ($y = 0; $y < $targetSize; $y++) {
                $dx = $x - $radius + 0.5;
                $dy = $y - $radius + 0.5;
                $dist = sqrt($dx * $dx + $dy * $dy);

                if ($dist <= $radius - $borderWidth) {
                    $c = imagecolorat($square, $x, $y);
                    imagesetpixel($dst, $x, $y, $c);
                } elseif ($dist <= $radius) {
                    $alpha = 0;
                    if ($dist > $radius - 1) {
                        $alpha = (int)(($dist - ($radius - 1)) * 127);
                    }
                    $bCol = imagecolorallocatealpha($dst, $borderColor[0], $borderColor[1], $borderColor[2], min(127, max(0, $alpha)));
                    imagesetpixel($dst, $x, $y, $bCol);
                }
            }
        }

        @imagepng($dst, $cacheFile, 6);

        return file_exists($cacheFile) ? $cacheFile : $orig;
    }
}

// Resolve Candidate Passport Photo Web URL
if (!function_exists('getStudentPhotoUrl')) {
    function getStudentPhotoUrl($photoPath, $prefix = '') {
        $filePath = getStudentPhotoFilePath($photoPath);
        if ($filePath) {
            $rootDir = realpath(__DIR__ . '/..');
            $rel = str_replace($rootDir . '/', '', $filePath);
            return $prefix . $rel;
        }
        // Fallback placeholder
        return "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23cbd5e1'><circle cx='12' cy='8' r='4'/><path d='M4 20c0-4 4-7 8-7s8 3 8 7'/></svg>";
    }
}

// School Logo local filesystem path or URL for PDF generation (TCPDF / FPDF)
if (!function_exists('getSchoolLogoFilePath')) {
    function getSchoolLogoFilePath($pdo = null, $rootDir = null) {
        if (!$rootDir) {
            $rootDir = realpath(__DIR__ . '/..');
        }
        $logo = $pdo ? getSetting($pdo, 'school_logo', '') : '';

        // 1. If explicit local file path exists, ALWAYS prioritize it over remote URLs
        if (!empty($logo) && strpos($logo, 'http') !== 0) {
            $fullPath = rtrim($rootDir, '/') . '/' . ltrim($logo, '/');
            if (file_exists($fullPath) && is_file($fullPath)) {
                return $fullPath;
            }
        }

        // 2. Prioritize local uploaded/custom logo files
        $localCandidates = [
            rtrim($rootDir, '/') . '/images/logo_custom.png',
            rtrim($rootDir, '/') . '/images/logo_default.png',
            rtrim($rootDir, '/') . '/images/logo_custom.jpg',
        ];
        foreach ($localCandidates as $cand) {
            if (file_exists($cand) && is_file($cand)) {
                return $cand;
            }
        }

        // 3. If explicit HTTP/HTTPS URL, return it
        if (!empty($logo) && (strpos($logo, 'http://') === 0 || strpos($logo, 'https://') === 0)) {
            return $logo;
        }

        return 'https://www.ncedges.com/wp-content/uploads/2026/02/mutosp-logo.png';
    }
}

// School Gender Classification Helper ('mixed' | 'boys' | 'girls')
if (!function_exists('getSchoolGenderType')) {
    function getSchoolGenderType($pdo = null) {
        return strtolower(trim(getSetting($pdo, 'school_gender_type', 'mixed')));
    }
}

// Admissions Pass Mark (Cutoff Aggregates) Helper
// User requirement: Transfers should NOT have cutoff points of admissions
if (!function_exists('getPassMark')) {
    function getPassMark($pdo, $class = 'S1') {
        $norm = strtoupper(trim((string)$class));
        if ($norm === 'S1' || $norm === 'SENIOR ONE' || $norm === '01') {
            $val = getSetting($pdo, 'admission_s1_passmark', '28');
            return ($val !== '' && is_numeric($val)) ? (int)$val : null;
        } elseif ($norm === 'S5' || $norm === 'SENIOR FIVE' || $norm === '05') {
            $val = getSetting($pdo, 'admission_s5_passmark', '42');
            return ($val !== '' && is_numeric($val)) ? (int)$val : null;
        }
        // Transfers (S2, S3, S4, S6) have NO cutoff pass mark
        return null;
    }
}

// Reporting Date Helper
if (!function_exists('getReportingDate')) {
    function getReportingDate($pdo, $class = 'S1') {
        $norm = strtoupper(trim((string)$class));
        if ($norm === 'S1' || $norm === 'SENIOR ONE' || $norm === '01') {
            $val = getSetting($pdo, 'reporting_date_s1', '2026-02-02');
        } elseif ($norm === 'S5' || $norm === 'SENIOR FIVE' || $norm === '05') {
            $val = getSetting($pdo, 'reporting_date_s5', '2026-02-09');
        } else {
            $val = getSetting($pdo, 'reporting_date_trans', '2026-02-02');
        }
        $valTrim = trim((string)$val);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valTrim)) {
            $ts = strtotime($valTrim);
            if ($ts !== false) {
                return date('l, jS F Y', $ts);
            }
        }
        return $val;
    }
}

// Resolve Candidate Passport Photo Server Filesystem Path
if (!function_exists('getStudentPhotoFilePath')) {
    function getStudentPhotoFilePath($photoPath) {
        if (empty($photoPath)) return null;
        $rootDir = realpath(__DIR__ . '/..');
        $clean = ltrim(trim($photoPath), './');
        $filename = basename($clean);

        $candidates = [
            $rootDir . '/' . $clean,
            $rootDir . '/uploads/passports/' . $filename,
            $rootDir . '/form-one/uploads/passports/' . $filename,
            $rootDir . '/form-five/uploads/passports/' . $filename,
            $rootDir . '/form-one/' . $clean,
            $rootDir . '/form-five/' . $clean,
        ];

        foreach ($candidates as $cand) {
            if (file_exists($cand) && is_file($cand)) {
                return $cand;
            }
        }
        return null;
    }
}

// Resolve Candidate Passport Photo Web URL
if (!function_exists('getStudentPhotoUrl')) {
    function getStudentPhotoUrl($photoPath, $prefix = '') {
        $filePath = getStudentPhotoFilePath($photoPath);
        if ($filePath) {
            $rootDir = realpath(__DIR__ . '/..');
            $rel = str_replace($rootDir . '/', '', $filePath);
            return $prefix . $rel;
        }
        return "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23cbd5e1'><circle cx='12' cy='8' r='4'/><path d='M4 20c0-4 4-7 8-7s8 3 8 7'/></svg>";
    }
}

// Resolve Head Teacher E-Signature File Path (for PDF)
if (!function_exists('getSchoolSignatureFilePath')) {
    function getSchoolSignatureFilePath($pdo = null, $baseDir = null) {
        $root = realpath(__DIR__ . '/..');
        if ($baseDir === null) {
            $baseDir = $root;
        } else {
            $baseDir = realpath($baseDir) ?: rtrim($baseDir, '/\\');
        }
        $sig = $pdo ? getSetting($pdo, 'head_teacher_signature', '') : '';
        if (!empty($sig) && strpos($sig, 'http') !== 0) {
            $clean = ltrim($sig, '/');
            if (file_exists($baseDir . '/' . $clean) && is_file($baseDir . '/' . $clean)) {
                return realpath($baseDir . '/' . $clean);
            }
            if ($root && file_exists($root . '/' . $clean) && is_file($root . '/' . $clean)) {
                return realpath($root . '/' . $clean);
            }
        }
        $checkDirs = array_unique(array_filter([$baseDir, $root]));
        foreach ($checkDirs as $dir) {
            $candidates = [
                $dir . '/images/signature_headteacher_1795179377.jpeg',
                $dir . '/images/signature_headteacher.png',
                $dir . '/images/signature_headteacher.jpeg',
                $dir . '/images/signature_headteacher.jpg',
                $dir . '/uploads/signatures/signature_headteacher.png',
                $dir . '/uploads/signatures/signature_headteacher.jpeg',
                $dir . '/uploads/signatures/signature_headteacher.jpg'
            ];
            foreach ($candidates as $cand) {
                if (file_exists($cand) && is_file($cand)) {
                    return realpath($cand) ?: $cand;
                }
            }
        }
        return null;
    }
}

// Resolve Head Teacher E-Signature URL (for Web UI)
if (!function_exists('getSchoolSignatureUrl')) {
    function getSchoolSignatureUrl($pdo = null, $relativePrefix = '') {
        $filePath = getSchoolSignatureFilePath($pdo);
        if ($filePath) {
            $baseDir = realpath(__DIR__ . '/..');
            $rel = str_replace($baseDir . '/', '', $filePath);
            return $relativePrefix . $rel . '?v=' . filemtime($filePath);
        }
        return null;
    }
}

// Formats School Website with proper protocol
if (!function_exists('getSchoolWebsiteUrl')) {
    function getSchoolWebsiteUrl($pdo = null) {
        $web = $pdo ? trim((string)getSetting($pdo, 'school_website', '')) : '';
        if (empty($web)) return '#';
        if (strpos($web, 'http://') === 0 || strpos($web, 'https://') === 0) {
            return $web;
        }
        return 'https://' . ltrim($web, '/');
    }
}

// Password Strength Validation Rule
if (!function_exists('validatePasswordStrength')) {
    function validatePasswordStrength($password) {
        if (strlen($password) < 8) {
            return "Password must be at least 8 characters long.";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return "Password must include at least one uppercase letter (A-Z).";
        }
        if (!preg_match('/[a-z]/', $password)) {
            return "Password must include at least one lowercase letter (a-z).";
        }
        if (!preg_match('/[0-9]/', $password)) {
            return "Password must include at least one number (0-9).";
        }
        if (!preg_match('/[\W_]/', $password)) {
            return "Password must include at least one special character or symbol (!@#$%^&*).";
        }
        return true;
    }
}

// Get Admin First Name for Profile Bar
if (!function_exists('getAdminDisplayName')) {
    function getAdminDisplayName($pdo, $username) {
        if (!empty($_SESSION['admin_firstname']) && (!empty($_SESSION['admin_username']) && $_SESSION['admin_username'] === $username)) {
            return $_SESSION['admin_firstname'];
        }
        if ($pdo && !empty($username)) {
            try {
                $stmt = $pdo->prepare("SELECT firstname FROM admin_users WHERE username = ?");
                $stmt->execute([$username]);
                $fn = $stmt->fetchColumn();
                if (!empty($fn)) {
                    $_SESSION['admin_firstname'] = trim($fn);
                    return $_SESSION['admin_firstname'];
                }
            } catch (Exception $e) {}
        }
        return !empty($username) ? $username : 'Staff';
    }
}

// Roles & Permissions System
if (!function_exists('getAllRoles')) {
    function getAllRoles($pdo) {
        try {
            $stmt = $pdo->query("SELECT * FROM system_roles ORDER BY id ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [
                ['role_key' => 'super_admin', 'role_name' => 'Super Administrator', 'description' => 'Full System Access', 'is_system' => 1],
                ['role_key' => 'admissions_manager', 'role_name' => 'Admissions & Policies Manager', 'description' => 'Policies, Fees, Letters & Admissions', 'is_system' => 1],
                ['role_key' => 'admin', 'role_name' => 'Administrator', 'description' => 'Admissions & Reporting', 'is_system' => 1],
                ['role_key' => 'admissions_registry', 'role_name' => 'Admissions Registry', 'description' => 'Admissions Registry', 'is_system' => 1],
            ];
        }
    }
}

if (!function_exists('getRolePermissions')) {
    function getRolePermissions($pdo, $roleKey) {
        $roleKey = strtolower(trim((string)$roleKey));
        if ($roleKey === 'super_admin' || $roleKey === 'superadmin') {
            return [
                'can_manage_settings'           => 1,
                'can_manage_admission_policies' => 1,
                'can_admit_students'            => 1,
                'can_reject_students'           => 1,
                'can_view_reports'              => 1,
                'can_export_data'               => 1,
                'can_manage_users'              => 1,
                'can_manage_roles'              => 1,
                'can_take_application_actions'  => 1,
                'can_view_logs'                 => 1,
            ];
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM system_roles WHERE role_key = ?");
            $stmt->execute([$roleKey]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                return [
                    'can_manage_settings'           => (int)$r['can_manage_settings'],
                    'can_manage_admission_policies' => (int)($r['can_manage_admission_policies'] ?? 0),
                    'can_admit_students'            => (int)$r['can_admit_students'],
                    'can_reject_students'           => (int)$r['can_reject_students'],
                    'can_view_reports'              => (int)$r['can_view_reports'],
                    'can_export_data'               => (int)$r['can_export_data'],
                    'can_manage_users'              => (int)$r['can_manage_users'],
                    'can_manage_roles'              => (int)($r['can_manage_roles'] ?? 0),
                    'can_take_application_actions'  => (int)($r['can_take_application_actions'] ?? 0),
                    'can_view_logs'                 => (int)$r['can_view_logs'],
                ];
            }
        } catch (Exception $e) {}

        // Fallbacks for legacy/default roles (CRUDAS Matrix)
        if ($roleKey === 'admissions_manager') {
            return [
                'can_manage_settings'           => 0,
                'can_manage_admission_policies' => 1,
                'can_admit_students'            => 1,
                'can_reject_students'           => 1,
                'can_view_reports'              => 1,
                'can_export_data'               => 1,
                'can_manage_users'              => 0,
                'can_manage_roles'              => 0,
                'can_take_application_actions'  => 1,
                'can_view_logs'                 => 1,
            ];
        }
        if ($roleKey === 'admin') {
            return [
                'can_manage_settings'           => 0,
                'can_manage_admission_policies' => 0,
                'can_admit_students'            => 1,
                'can_reject_students'           => 1,
                'can_view_reports'              => 1,
                'can_export_data'               => 1,
                'can_manage_users'              => 1,
                'can_manage_roles'              => 0,
                'can_take_application_actions'  => 1,
                'can_view_logs'                 => 1,
            ];
        }
        if ($roleKey === 'admissions_registry' || $roleKey === 'secretary') {
            return [
                'can_manage_settings'           => 0,
                'can_manage_admission_policies' => 0,
                'can_admit_students'            => 1,
                'can_reject_students'           => 1,
                'can_view_reports'              => 1,
                'can_export_data'               => 1,
                'can_manage_users'              => 0,
                'can_manage_roles'              => 0,
                'can_take_application_actions'  => 1,
                'can_view_logs'                 => 0,
            ];
        }
        if ($roleKey === 'bursar') {
            return [
                'can_manage_settings'           => 0,
                'can_manage_admission_policies' => 0,
                'can_admit_students'            => 0,
                'can_reject_students'           => 0,
                'can_view_reports'              => 1,
                'can_export_data'               => 1,
                'can_manage_users'              => 0,
                'can_manage_roles'              => 0,
                'can_take_application_actions'  => 0,
                'can_view_logs'                 => 0,
            ];
        }
        if ($roleKey === 'viewer') {
            return [
                'can_manage_settings'           => 0,
                'can_manage_admission_policies' => 0,
                'can_admit_students'            => 0,
                'can_reject_students'           => 0,
                'can_view_reports'              => 1,
                'can_export_data'               => 0,
                'can_manage_users'              => 0,
                'can_manage_roles'              => 0,
                'can_take_application_actions'  => 0,
                'can_view_logs'                 => 0,
            ];
        }
        if ($roleKey === 'ict_admin') {
            return [
                'can_manage_settings'           => 0,
                'can_manage_admission_policies' => 0,
                'can_admit_students'            => 0,
                'can_reject_students'           => 0,
                'can_view_reports'              => 1,
                'can_export_data'               => 1,
                'can_manage_users'              => 1,
                'can_manage_roles'              => 0,
                'can_take_application_actions'  => 0,
                'can_view_logs'                 => 0,
            ];
        }
        // Strict zero fallback for any unknown or non-configured role
        return [
            'can_manage_settings'           => 0,
            'can_manage_admission_policies' => 0,
            'can_admit_students'            => 0,
            'can_reject_students'           => 0,
            'can_view_reports'              => 0,
            'can_export_data'               => 0,
            'can_manage_users'              => 0,
            'can_manage_roles'              => 0,
            'can_take_application_actions'  => 0,
            'can_view_logs'                 => 0,
        ];
    }
}

if (!function_exists('hasPermission')) {
    function hasPermission($pdo, $roleKey, $permission) {
        $rolesToTest = [];

        // If array passed
        if (is_array($roleKey)) {
            $rolesToTest = array_merge($rolesToTest, $roleKey);
        } elseif (is_string($roleKey)) {
            $trimmed = trim($roleKey);
            if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $rolesToTest = array_merge($rolesToTest, $decoded);
                }
            } elseif (strpos($trimmed, ',') !== false) {
                $parts = array_map('trim', explode(',', $trimmed));
                $rolesToTest = array_merge($rolesToTest, $parts);
            } elseif ($trimmed !== '') {
                $rolesToTest[] = $trimmed;
            }
        }

        // Also merge any assigned multi-roles from active session
        if (!empty($_SESSION['admin_roles']) && is_array($_SESSION['admin_roles'])) {
            $rolesToTest = array_merge($rolesToTest, $_SESSION['admin_roles']);
        }
        if (!empty($_SESSION['admin_role']) && !in_array($_SESSION['admin_role'], $rolesToTest)) {
            $rolesToTest[] = $_SESSION['admin_role'];
        }

        $rolesToTest = array_unique(array_filter($rolesToTest));

        foreach ($rolesToTest as $rk) {
            $perms = getRolePermissions($pdo, $rk);
            if (!empty($perms[$permission])) {
                return true;
            }
        }
        return false;
    }
}

// Format Official Institutional Admission Number (Format Student Number: 270509801)
if (!function_exists('getAdmissionNumber')) {
    function getAdmissionNumber($app, $pdo = null) {
        if ($pdo === null && isset($GLOBALS['pdo'])) {
            $pdo = $GLOBALS['pdo'];
        }

        if (is_numeric($app)) {
            $id = (int)$app;
            $class = 'S1';
            $fullYear = date('Y');
            $year = date('y');
        } elseif (is_array($app)) {
            $id = (int)($app['id'] ?? 0);
            $class = strtoupper($app['class_of_entry'] ?? 'S1');
            $rawYear = !empty($app['academic_year']) ? (string)$app['academic_year'] : date('Y');
            if (preg_match('/\b(20\d{2})\b/', $rawYear, $ym)) {
                $fullYear = $ym[1];
                $year = substr($fullYear, 2, 2);
            } else {
                $year = preg_match('/\b(20)?(\d{2})\b/', $rawYear, $ym2) ? $ym2[2] : date('y');
                $fullYear = '20' . $year;
            }
        } else {
            return 'N/A';
        }

        $classMap = [
            'S1' => '01',
            'S2' => '02',
            'S3' => '03',
            'S4' => '04',
            'S5' => '05',
            'S6' => '06',
        ];
        $classCode = $classMap[$class] ?? '01';

        // School Center Code: default '098'
        $schoolCode = '098';
        $format = '{YY}{CLASS}{CODE}{SERIAL}';
        if ($pdo) {
            $sc = getSetting($pdo, 'school_code', '098');
            if (!empty($sc)) {
                $digits = preg_replace('/\D/', '', $sc);
                if (!empty($digits)) {
                    $schoolCode = str_pad(substr($digits, 0, 3), 3, '0', STR_PAD_LEFT);
                }
            }
            $format = getSetting($pdo, 'student_number_format', '{YY}{CLASS}{CODE}{SERIAL}');
        }

        // Serial variations
        $serial2 = str_pad(max(1, $id % 100), 2, '0', STR_PAD_LEFT);
        $serial3 = str_pad(max(1, $id % 1000), 3, '0', STR_PAD_LEFT);
        $serial4 = str_pad(max(1, $id), 4, '0', STR_PAD_LEFT);

        $replacements = [
            '{YY}'         => $year,
            '{YYYY}'       => $fullYear,
            '{CLASS}'      => $classCode,
            '{CLASS_NAME}' => $class,
            '{CODE}'       => $schoolCode,
            '{SERIAL}'     => $serial2,
            '{SERIAL2}'    => $serial2,
            '{SERIAL3}'    => $serial3,
            '{SERIAL4}'    => $serial4,
            '{ID}'         => (string)$id,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $format);
    }
}

// Cloudflare Turnstile Verification Helper
if (!function_exists('verifyTurnstileToken')) {
    function verifyTurnstileToken($pdo, $token) {
        $cfEnabled = getSetting($pdo, 'cf_turnstile_enabled', '0');
        $cfSecret  = getSetting($pdo, 'cf_turnstile_secret_key', '');
        if ($cfEnabled !== '1' || empty($cfSecret)) {
            return true; // Not enabled or secret not set, pass through
        }
        if (empty($token)) {
            return false;
        }
        $verifyUrl = "https://challenges.cloudflare.com/turnstile/v0/siteverify";
        $postData = http_build_query([
            'secret'   => $cfSecret,
            'response' => $token,
            'remoteip' => getClientIp()
        ]);
        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'timeout' => 8
            ]
        ];
        $context = stream_context_create($opts);
        $result = @file_get_contents($verifyUrl, false, $context);
        if ($result !== false) {
            $resp = json_decode($result, true);
            return !empty($resp['success']);
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($verifyUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $cRes = curl_exec($ch);
            curl_close($ch);
            if ($cRes !== false) {
                $cJson = json_decode($cRes, true);
                return !empty($cJson['success']);
            }
        }
        return false;
    }
}

// Cloudflare Turnstile HTML Widget Renderer
if (!function_exists('renderTurnstileWidget')) {
    function renderTurnstileWidget($pdo) {
        $cfEnabled = getSetting($pdo, 'cf_turnstile_enabled', '0');
        $siteKey   = getSetting($pdo, 'cf_turnstile_site_key', '');
        if ($cfEnabled === '1' && !empty($siteKey)) {
            return '
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            <div class="cf-turnstile my-2.5 flex justify-center" data-sitekey="' . htmlspecialchars($siteKey) . '" data-theme="light"></div>';
        }
        return '';
    }
}

// -------------------------------------------------------------
// Auto-correct photo orientation from smartphone camera EXIF metadata
// -------------------------------------------------------------
if (!function_exists('correctImageExifOrientation')) {
    function correctImageExifOrientation($filePath) {
        if (!file_exists($filePath) || !is_file($filePath)) return false;
        if (!extension_loaded('gd')) return false;

        $info = @getimagesize($filePath);
        if (!$info) return false;

        $mime = $info['mime'] ?? '';
        if ($mime !== 'image/jpeg' && $mime !== 'image/jpg') {
            return false;
        }

        if (!function_exists('exif_read_data')) {
            return false;
        }

        $exif = @exif_read_data($filePath);
        if (!$exif || empty($exif['Orientation'])) {
            return false;
        }

        $orientation = (int)$exif['Orientation'];
        if ($orientation <= 1) {
            return false;
        }

        $image = @imagecreatefromjpeg($filePath);
        if (!$image) return false;

        $rotated = false;
        switch ($orientation) {
            case 2:
                if (function_exists('imageflip')) {
                    imageflip($image, IMG_FLIP_HORIZONTAL);
                    $rotated = true;
                }
                break;
            case 3: // 180 degrees (upside down)
                $rotatedImg = @imagerotate($image, 180, 0);
                if ($rotatedImg) {
                    $image = $rotatedImg;
                    $rotated = true;
                }
                break;
            case 4:
                if (function_exists('imageflip')) {
                    imageflip($image, IMG_FLIP_VERTICAL);
                    $rotated = true;
                }
                break;
            case 5:
                if (function_exists('imageflip')) {
                    imageflip($image, IMG_FLIP_VERTICAL);
                    $rotatedImg = @imagerotate($image, -90, 0);
                    if ($rotatedImg) {
                        $image = $rotatedImg;
                        $rotated = true;
                    }
                }
                break;
            case 6: // 90 CW (Standard phone portrait orientation)
                $rotatedImg = @imagerotate($image, -90, 0);
                if ($rotatedImg) {
                    $image = $rotatedImg;
                    $rotated = true;
                }
                break;
            case 7:
                if (function_exists('imageflip')) {
                    imageflip($image, IMG_FLIP_HORIZONTAL);
                    $rotatedImg = @imagerotate($image, -90, 0);
                    if ($rotatedImg) {
                        $image = $rotatedImg;
                        $rotated = true;
                    }
                }
                break;
            case 8: // 90 CCW (Inverted phone portrait orientation)
                $rotatedImg = @imagerotate($image, 90, 0);
                if ($rotatedImg) {
                    $image = $rotatedImg;
                    $rotated = true;
                }
                break;
        }

        if ($rotated && $image) {
            @imagejpeg($image, $filePath, 95);
            return true;
        }
        return false;
    }
}

// -------------------------------------------------------------
// Automatic Passport Photo Background Processing (Pure White Canvas)
// -------------------------------------------------------------
if (!function_exists('processPassportPhotoBackground')) {
    function processPassportPhotoBackground($filePath) {
        if (!file_exists($filePath) || !is_file($filePath)) return false;
        if (!extension_loaded('gd')) return false;

        // Auto-orient mobile phone photos first before processing background
        if (function_exists('correctImageExifOrientation')) {
            correctImageExifOrientation($filePath);
        }

        $info = @getimagesize($filePath);
        if (!$info) return false;

        $mime = $info['mime'] ?? '';
        $srcImg = null;
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $srcImg = @imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $srcImg = @imagecreatefrompng($filePath);
                break;
            case 'image/webp':
                $srcImg = @imagecreatefromwebp($filePath);
                break;
            default:
                return false;
        }
        if (!$srcImg) return false;

        $w = imagesx($srcImg);
        $h = imagesy($srcImg);
        if ($w <= 0 || $h <= 0) return false;

        // Create a new true-color canvas with solid pure white background (#FFFFFF)
        $whiteImg = imagecreatetruecolor($w, $h);
        $whiteColor = imagecolorallocate($whiteImg, 255, 255, 255);
        imagefilledrectangle($whiteImg, 0, 0, $w, $h, $whiteColor);

        // Copy source image over white background (composites alpha transparency onto solid white)
        imagecopy($whiteImg, $srcImg, 0, 0, 0, 0, $w, $h);

        // Sample corner backdrop pixels (top-left, top-right)
        $samplePoints = [[2, 2], [min($w - 3, 10), 2], [2, min($h - 3, 10)], [max(0, $w - 3), 2]];
        $bgR = 0; $bgG = 0; $bgB = 0;
        $validSamples = 0;
        foreach ($samplePoints as $pt) {
            if ($pt[0] >= 0 && $pt[0] < $w && $pt[1] >= 0 && $pt[1] < $h) {
                $rgb = imagecolorat($srcImg, $pt[0], $pt[1]);
                $bgR += ($rgb >> 16) & 0xFF;
                $bgG += ($rgb >> 8) & 0xFF;
                $bgB += $rgb & 0xFF;
                $validSamples++;
            }
        }
        if ($validSamples > 0) {
            $avgR = $bgR / $validSamples;
            $avgG = $bgG / $validSamples;
            $avgB = $bgB / $validSamples;
            $brightness = ($avgR * 299 + $avgG * 587 + $avgB * 114) / 1000;

            // If background is light (grey, cream, off-white, light blue studio backdrop), whiten border/backdrop pixels
            if ($brightness > 140) {
                $topLimit = min($h, intval($h * 0.40));
                for ($y = 0; $y < $topLimit; $y++) {
                    for ($x = 0; $x < $w; $x++) {
                        if ($y < 25 || $x < intval($w * 0.18) || $x > intval($w * 0.82)) {
                            $rgb = imagecolorat($srcImg, $x, $y);
                            $r = ($rgb >> 16) & 0xFF;
                            $g = ($rgb >> 8) & 0xFF;
                            $b = $rgb & 0xFF;
                            $dist = sqrt(pow($r - $avgR, 2) + pow($g - $avgG, 2) + pow($b - $avgB, 2));
                            if ($dist < 48 || ($r > 200 && $g > 200 && $b > 200)) {
                                imagesetpixel($whiteImg, $x, $y, $whiteColor);
                            }
                        }
                    }
                }
            }
        }

        // Save back with crisp, solid white background
        if ($mime === 'image/png') {
            imagepng($whiteImg, $filePath, 8);
        } else {
            imagejpeg($whiteImg, $filePath, 92);
        }
        return true;
    }
}

// -------------------------------------------------------------
// Official Provisional Admission Offer Dispatcher
// -------------------------------------------------------------
if (!function_exists('dispatchAdmissionOfferNotification')) {
    function dispatchAdmissionOfferNotification($pdo, $appId) {
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $mailerPath = __DIR__ . '/../form-one/includes/mailer.php';
            if (file_exists($mailerPath)) require_once $mailerPath;
        }
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
        $stmt->execute([$appId]);
        $applicant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$applicant || empty($applicant['parent_email'])) return false;

        $school_name       = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
        $school_short_name = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
        $academic_year     = getAcademicYear($pdo, $applicant['class_of_entry'] ?? 'S1');
        $pdfLink           = getBaseUrl($pdo) . "/app/admission_offer.php?id=" . $appId;
        $reportingDate     = getReportingDate($pdo, $applicant['class_of_entry'] ?? 'S1');
        $primary_color     = getPrimaryColor($pdo);
        $accent_color      = getAccentColor($pdo);
        $baseUrl           = getBaseUrl($pdo);

        $logo_source = getSchoolLogoSrc($pdo);
        if (strpos($logo_source, 'http') === false) {
            $logo_source = getBaseUrl($pdo) . '/' . ltrim($logo_source, '/');
        }

        $docRules    = getSetting($pdo, 'doc_school_rules', '');
        $docCircular = getSetting($pdo, 'doc_admission_circular', '');
        $docMedForm  = getSetting($pdo, 'doc_medical_form', '');

        $className = "Senior " . substr($applicant['class_of_entry'], 1);
        $parentName = htmlspecialchars($applicant['parent_name']);
        $studentName = htmlspecialchars($applicant['firstname'] . ' ' . $applicant['surname']);

        $enclosuresHtml = '';
        if (!empty($docRules) || !empty($docCircular) || !empty($docMedForm)) {
            $enclosuresHtml .= "
            <div class='inner-box' style='background-color: #f1f5f9; padding: 14px 14px; border-radius: 10px; margin: 18px 0;'>
                <h4 style='margin-top: 0; color: {$primary_color}; font-size: 13.5px; font-weight: bold;'>Official School Enclosures & Attachments:</h4>
                <ul style='margin: 6px 0 0 0; padding-left: 20px; font-size: 13px; color: #334155;'>";
            if (!empty($docRules)) {
                $enclosuresHtml .= "<li style='margin-bottom: 6px;'><a href='{$baseUrl}/{$docRules}' target='_blank' style='color: {$primary_color}; font-weight: bold;'>Download School Rules & Regulations (PDF)</a></li>";
            }
            if (!empty($docCircular)) {
                $enclosuresHtml .= "<li style='margin-bottom: 6px;'><a href='{$baseUrl}/{$docCircular}' target='_blank' style='color: {$primary_color}; font-weight: bold;'>Download Term I Admissions Circular (PDF)</a></li>";
            }
            if (!empty($docMedForm)) {
                $enclosuresHtml .= "<li style='margin-bottom: 6px;'><a href='{$baseUrl}/{$docMedForm}' target='_blank' style='color: {$primary_color}; font-weight: bold;'>Download Student Medical Examination Form (PDF)</a></li>";
            }
            $enclosuresHtml .= "</ul></div>";
        }

        $reportingNotice = "";
        if (!empty($reportingDate)) {
            $reportingNotice = "<div class='inner-box' style='background-color: #ecfdf5; border-left: 4px solid {$primary_color}; padding: 12px 14px; border-radius: 8px; font-size: 13.5px; margin: 16px 0;'>
                <strong>Official Reporting Date:</strong> <span style='color: {$primary_color}; font-weight: bold;'>{$reportingDate}</span><br>
                <span style='color: #b45309; font-weight: bold;'>* Please bring a printed copy of your admission letter on reporting date.</span>
            </div>";
        }

        $combNotice = "";
        if (!empty($applicant['combination'])) {
            $combNotice = "<div class='inner-box' style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid {$accent_color}; padding: 12px 14px; border-radius: 8px; font-size: 13.5px; margin: 16px 0;'>
                <strong>Approved A-Level Combination:</strong> <span style='color: {$primary_color}; font-weight: bold;'>" . htmlspecialchars($applicant['combination']) . "</span>
            </div>";
        }

        $message = "
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
    .btn-action { display: block !important; width: 100% !important; box-sizing: border-box !important; text-align: center !important; padding: 13px 12px !important; font-size: 14px !important; }
    .email-footer { padding: 16px 10px !important; }
}
</style>
</head>
<body style='margin: 0; padding: 0; background-color: #f1f5f9;'>
<div class='outer-wrapper' style=\"font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; padding: 20px 8px; font-size: 15px; line-height: 1.65; color: #1e293b;\">
    <div class='email-container' style='max-width: 620px; width: 100%; margin: 0 auto; background-color: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;'>
        <div class='email-header' style='background-color: {$primary_color}; padding: 26px 20px; text-align: center; border-bottom: 3px solid {$accent_color};'>
            <table role='presentation' border='0' cellpadding='0' cellspacing='0' align='center' style='margin: 0 auto 14px auto; text-align: center; border-collapse: collapse;'>
                <tr>
                    <td align='center' style='text-align: center; background: transparent; background-color: transparent;'>
                        <img src='{$logo_source}' alt='{$school_short_name} Logo' style='display: block; margin: 0 auto; max-height: 80px; max-width: 180px; height: auto; width: auto; background-color: transparent; border: 0; outline: none; object-fit: contain;'>
                    </td>
                </tr>
            </table>
            <h2 style='color: #ffffff; margin: 0; font-size: 20px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase;'>{$school_name}</h2>
            <p style='color: {$accent_color}; margin: 5px 0 0 0; font-size: 11.5px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;'>Office of Admissions Registry</p>
        </div>
        <div class='email-body' style='padding: 26px 20px;'>
            <div style='display: inline-block; padding: 5px 14px; background-color: #ecfdf5; color: #065f46; font-size: 11.5px; font-weight: 700; text-transform: uppercase; border-radius: 9999px; letter-spacing: 0.5px; margin-bottom: 14px; border: 1px solid #a7f3d0;'>
                Official Provisional Admission Offer
            </div>
            <h3 style='color: {$primary_color}; margin-top: 0; font-size: 20px; font-weight: 800; line-height: 1.3;'>Provisional Offer of Admission</h3>
            <p style='margin-top: 12px;'>Dear Parent / Guardian (<strong>$parentName</strong>),</p>
            <p>We are delighted to inform you that your child <strong>$studentName</strong> has been evaluated and <strong>OFFICIALLY ADMITTED</strong> to <strong>$className</strong> at <strong>{$school_name}</strong> for the <strong>{$academic_year} Academic Year</strong>.</p>
            {$combNotice}
            {$reportingNotice}
            <p style='margin-top: 20px;'>Please click the button below to download and print the official Provisional Admission Offer Letter (PDF):</p>
            <div style='text-align: center; margin: 24px 0;'>
                <a href='{$pdfLink}' class='btn-action' style='background-color: {$primary_color}; color: #ffffff; padding: 14px 28px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 14.5px; display: inline-block; box-shadow: 0 4px 12px rgba(12,74,36,0.22);'>
                    Download Admission Offer Letter (PDF)
                </a>
            </div>
            <p style='font-size: 13px; color: #64748b; line-height: 1.5;'>Or copy this direct link into your browser:<br>
            <a href='{$pdfLink}' style='color: {$accent_color}; word-break: break-all; font-weight: 600;'>{$pdfLink}</a></p>
            {$enclosuresHtml}
            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;'>
            <p style='margin-bottom: 4px;'>We look forward to welcoming $studentName to our school family.</p>
            <p style='margin-top: 12px; margin-bottom: 2px; font-size: 13.5px; color: #64748b;'>Yours faithfully,</p>
            <p style='margin-top: 4px; font-weight: 700; color: {$primary_color}; font-size: 14.5px;'>Office of Admissions Registry<br><span style='color: #64748b; font-size: 12.5px; font-weight: normal;'>{$school_name}</span></p>
        </div>
        <div class='email-footer' style='background-color: #f8fafc; padding: 18px; text-align: center; border-top: 1px solid #e2e8f0;'>
            <p style='margin: 0; font-size: 11.5px; color: #94a3b8;'>This is an official automated notification. Please do not reply directly to this email.</p>
            <p style='margin: 4px 0 0 0; font-size: 11.5px; color: #94a3b8;'>&copy; " . date('Y') . " {$school_name}. All rights reserved.</p>
        </div>
    </div>
</div>
</body>
</html>";
        if (function_exists('sendEmail')) {
            sendEmail($applicant['parent_email'], "$className Admission Offer - {$academic_year} - {$school_name}", $message);
        }
        return true;
    }
}

// -------------------------------------------------------------
// AI Academic Document Authenticity & Fake/Test Document Inspector
// -------------------------------------------------------------
if (!function_exists('extractTextFromPdfStream')) {
    function extractTextFromPdfStream($pdfPath) {
        if (!file_exists($pdfPath) || !is_file($pdfPath)) return '';
        $content = @file_get_contents($pdfPath);
        if (!$content) return '';

        $extractedText = '';

        // 1. Direct text stream objects (e.g. (Text) Tj or [ (Text1) 10 (Text2) ] TJ)
        if (preg_match_all('/\((.*?)\)\s*T[jJ]/s', $content, $matches)) {
            $extractedText .= ' ' . implode(' ', $matches[1]);
        }

        // 2. Decompress FlateDecode streams if present
        if (function_exists('gzuncompress')) {
            if (preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $content, $streamMatches)) {
                foreach ($streamMatches[1] as $stream) {
                    $uncompressed = @gzuncompress($stream);
                    if ($uncompressed) {
                        if (preg_match_all('/\((.*?)\)\s*T[jJ]/s', $uncompressed, $subMatches)) {
                            $extractedText .= ' ' . implode(' ', $subMatches[1]);
                        }
                        if (preg_match_all('/[a-zA-Z]{3,}/', $uncompressed, $wordMatches)) {
                            $extractedText .= ' ' . implode(' ', $wordMatches[0]);
                        }
                    }
                }
            }
        }

        // 3. Fallback raw ASCII text sequences
        if (preg_match_all('/[a-zA-Z0-9\/\-\.]{3,}/', $content, $rawWords)) {
            $extractedText .= ' ' . implode(' ', $rawWords[0]);
        }

        return strtolower($extractedText);
    }
}

if (!function_exists('verifyAcademicDocumentAuthenticity')) {
    function verifyAcademicDocumentAuthenticity($docPath, $app = []) {
        if (empty($docPath) || !file_exists($docPath) || !is_file($docPath)) {
            return [
                'is_authentic' => false,
                'status' => 'missing',
                'reason' => 'Academic credential file not found on server.'
            ];
        }

        $fileSize = filesize($docPath);
        if ($fileSize < 15360) { // < 15KB is too small for a legitimate multi-grade UNEB transcript or official scan
            return [
                'is_authentic' => false,
                'status' => 'too_small',
                'reason' => "Document file size ({$fileSize} bytes) is below authentic academic credential threshold (minimum 15KB)."
            ];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $docPath);
        finfo_close($finfo);

        $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowedMimes)) {
            return [
                'is_authentic' => false,
                'status' => 'invalid_mime',
                'reason' => "Unsupported file mime type: {$mime}."
            ];
        }

        // Strict Negative Non-Academic Keywords (Invoices, Receipts, CVs, Resumes, Dummy/Sample/Test files)
        $nonAcademicKeywords = [
            'invoice', 'tax invoice', 'receipt', 'payment voucher', 'bill of lading',
            'purchase order', 'salary slip', 'payslip', 'payroll', 'curriculum vitae', 
            'resume', 'lorem ipsum', 'dummy text', 'test document', 'test pdf', 
            'sample document', 'sample pdf', 'blank page', 'hotel booking', 'boarding pass',
            'flight ticket', 'supermarket', 'grocery', 'restaurant menu', 'test doc'
        ];

        // Strict Positive Ugandan Academic & Institutional Markers
        $academicInstitutionalMarkers = [
            'uganda national examinations board', 'uneb', 'ministry of education and sports',
            'primary leaving examination', 'ple', 'uganda certificate of education', 'uce',
            'uganda advanced certificate', 'uace', 'statement of results', 'result slip',
            'pass slip', 'continuous assessment', 'new lower secondary curriculum', 'nlsc',
            'competency', 'project work', 'learner identification number', 'candidate',
            'index no', 'centre no', 'aggregates', 'division', 'distinction', 'credit',
            'principal pass', 'report card', 'secondary school', 'primary school', 'headteacher',
            'term 1', 'term 2', 'term 3', 'academic year', 'examinations board'
        ];

        $docBasename = strtolower(basename($docPath));
        foreach ($nonAcademicKeywords as $badKw) {
            if (stripos($docBasename, $badKw) !== false) {
                return [
                    'is_authentic' => false,
                    'status' => 'non_academic_filename',
                    'reason' => "File name contains non-academic descriptor ('{$badKw}')."
                ];
            }
        }

        if ($mime === 'application/pdf') {
            $fp = @fopen($docPath, 'rb');
            $header = $fp ? fread($fp, 5) : '';
            if ($fp) fclose($fp);
            if ($header !== '%PDF-') {
                return [
                    'is_authentic' => false,
                    'status' => 'corrupted_pdf',
                    'reason' => 'Corrupted or invalid PDF header structure.'
                ];
            }

            $extractedText = extractTextFromPdfStream($docPath);

            // Check for negative non-academic indicators in extracted PDF text
            foreach ($nonAcademicKeywords as $badKw) {
                if (stripos($extractedText, $badKw) !== false) {
                    return [
                        'is_authentic' => false,
                        'status' => 'non_academic_content',
                        'reason' => "PDF document content indicates non-academic data ('{$badKw}')."
                    ];
                }
            }

            // Count positive academic institutional markers
            $foundMarkers = [];
            foreach ($academicInstitutionalMarkers as $marker) {
                if (stripos($extractedText, $marker) !== false) {
                    $foundMarkers[] = $marker;
                }
            }

            // Cross-reference applicant candidate credentials
            $candidateMatches = [];
            $surname = strtolower(trim($app['surname'] ?? ''));
            $indexNo = strtolower(trim($app['index_number'] ?? ''));
            $formerSchool = strtolower(trim($app['former_school'] ?? ''));

            if (!empty($indexNo) && stripos($extractedText, $indexNo) !== false) {
                $candidateMatches[] = "Index: {$indexNo}";
            }
            if (!empty($surname) && strlen($surname) >= 3 && stripos($extractedText, $surname) !== false) {
                $candidateMatches[] = "Surname: {$surname}";
            }
            if (!empty($formerSchool) && strlen($formerSchool) >= 4 && stripos($extractedText, $formerSchool) !== false) {
                $candidateMatches[] = "School: {$formerSchool}";
            }

            // Decision: Authentic if at least 2 institutional markers found, OR 1 strong marker + candidate match
            $isAuthentic = (count($foundMarkers) >= 2) || (count($foundMarkers) >= 1 && count($candidateMatches) >= 1);

            // Scanned image PDF fallback: if text extraction yielded very few words (image-only scan PDF),
            // check if file size is substantial (>= 35KB) and no negative keywords were found
            if (!$isAuthentic && strlen(trim($extractedText)) < 80 && $fileSize >= 35840) {
                $rawContent = @file_get_contents($docPath);
                if (stripos($rawContent, '/XObject') !== false && stripos($rawContent, '/Subtype /Image') !== false) {
                    $isAuthentic = true;
                    $foundMarkers[] = 'scanned_document_xobject';
                }
            }

            if (!$isAuthentic) {
                return [
                    'is_authentic' => false,
                    'status' => 'lacks_academic_credentials',
                    'reason' => 'PDF document lacks verified UNEB institutional markers, examination board credentials, or applicant data.',
                    'markers_found' => $foundMarkers
                ];
            }

            return [
                'is_authentic' => true,
                'status' => 'verified_pdf',
                'markers' => $foundMarkers,
                'candidate_matches' => $candidateMatches
            ];

        } else {
            // Image scan (JPEG, PNG, WEBP)
            $imgInfo = @getimagesize($docPath);
            if (!$imgInfo || $imgInfo[0] < 500 || $imgInfo[1] < 500) {
                return [
                    'is_authentic' => false,
                    'status' => 'low_resolution_image',
                    'reason' => "Result slip image dimensions (" . ($imgInfo ? "{$imgInfo[0]}x{$imgInfo[1]}" : 'unknown') . ") are below readable academic document standard (minimum 500x500)."
                ];
            }

            $w = $imgInfo[0];
            $h = $imgInfo[1];
            $aspectRatio = $w / $h;
            // Legitimate document scans have aspect ratios between 0.5 (portrait A4/letter) and 2.0 (landscape)
            if ($aspectRatio < 0.5 || $aspectRatio > 2.0) {
                return [
                    'is_authentic' => false,
                    'status' => 'abnormal_aspect_ratio',
                    'reason' => "Image aspect ratio (" . round($aspectRatio, 2) . ") does not match standard academic document format."
                ];
            }

            // Image variance test using GD to detect blank/flat/solid test images
            if (extension_loaded('gd')) {
                $sampleImg = null;
                if ($mime === 'image/jpeg') $sampleImg = @imagecreatefromjpeg($docPath);
                elseif ($mime === 'image/png') $sampleImg = @imagecreatefrompng($docPath);
                elseif ($mime === 'image/webp') $sampleImg = @imagecreatefromwebp($docPath);

                if ($sampleImg) {
                    $luminances = [];
                    for ($x = 10; $x < $w; $x += max(1, (int)($w / 5))) {
                        for ($y = 10; $y < $h; $y += max(1, (int)($h / 5))) {
                            $rgb = imagecolorat($sampleImg, $x, $y);
                            $r = ($rgb >> 16) & 0xFF;
                            $g = ($rgb >> 8) & 0xFF;
                            $b = $rgb & 0xFF;
                            $luminances[] = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                        }
                    }

                    if (!empty($luminances)) {
                        $mean = array_sum($luminances) / count($luminances);
                        $variance = 0.0;
                        foreach ($luminances as $lum) {
                            $variance += pow($lum - $mean, 2);
                        }
                        $stdDev = sqrt($variance / count($luminances));

                        if ($stdDev < 6.0) {
                            return [
                                'is_authentic' => false,
                                'status' => 'blank_or_solid_image',
                                'reason' => "Uploaded image lacks textual content variance (detected as blank, solid, or dummy graphic)."
                            ];
                        }
                    }
                }
            }

            return [
                'is_authentic' => true,
                'status' => 'verified_image',
                'dimensions' => "{$w}x{$h}"
            ];
        }
    }
}

// -------------------------------------------------------------
// Admissions Review & Verification Helper (Manual Admissions Mode)
// -------------------------------------------------------------
if (!function_exists('evaluateApplicantAdmissionAI')) {
    function evaluateApplicantAdmissionAI($pdo, $appId) {
        // System auto-admissions are disabled per institutional policy; all applications require manual committee admission
        return ['status' => 'manual_review_required', 'admitted' => false];
    }
}
