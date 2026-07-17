<?php
$host = "localhost";
$db   = "mutolere-admissions";
$user = "webadmin";
$pass = "User@2026@";
$charset = "utf8mb4";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=$charset",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

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
      passport_photo VARCHAR(255) NOT NULL,
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
        'must_change_password' => "ALTER TABLE admin_users ADD must_change_password TINYINT DEFAULT 0 AFTER password"
    ];

    foreach ($colsToCheck as $colName => $alterQuery) {
        $checkCol = $pdo->query("SHOW COLUMNS FROM admin_users LIKE '$colName'");
        if ($checkCol->rowCount() === 0) {
            $pdo->exec($alterQuery);
        }
    }

    // Adjust S5 combination size if it is limited to VARCHAR(20)
    $checkComb = $pdo->query("SHOW COLUMNS FROM s5_applications LIKE 'combination'");
    $combMeta = $checkComb->fetch(PDO::FETCH_ASSOC);
    if ($combMeta && strpos($combMeta['Type'], 'varchar(20)') !== false) {
        $pdo->exec("ALTER TABLE s5_applications MODIFY combination VARCHAR(100) NOT NULL");
    }

    // 4. Seed default super_admin account if empty
    $checkUsers = $pdo->query("SELECT COUNT(*) FROM admin_users");
    if ($checkUsers->fetchColumn() == 0) {
        $hashed = password_hash('admin', PASSWORD_DEFAULT);
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
        'school_logo'           => 'https://www.ncedges.com/wp-content/uploads/2026/02/mutosp-logo.png',
        'admin_email'           => 'info@ncedges.com,cmbonye65@gmail.com,mutoleresp@gmail.com,musareona146@gmail.com',
        'school_address'        => 'P.O. Box 12, Kisoro – Uganda',
        'school_phone'          => '+256 790 001 631',
        'school_email'          => 'info@ncedges.com',
        'school_website'        => 'www.mutolere.sc.ug',
        'fees_s1_tuition'       => '550000',
        'fees_s1_admission'     => '100000',
        'fees_s1_functional'    => '150000',
        'fees_s5_tuition'       => '600000',
        'fees_s5_admission'     => '120000',
        'fees_s5_functional'    => '180000',
        'fees_trans_tuition'    => '570000',
        'fees_trans_admission'  => '110000',
        'fees_trans_functional' => '170000',
    ];

    $checkSet = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
    $insertSet = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaultSettings as $k => $v) {
        $checkSet->execute([$k]);
        if ($checkSet->fetchColumn() == 0) {
            $insertSet->execute([$k, $v]);
        }
    }

} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Activity Logging Helper Function
if (!function_exists('logActivity')) {
    function logActivity($pdo, $username, $action, $details = '') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
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
    function getSetting($pdo, $key, $default = '') {
        static $settings = null;
        if ($settings === null) {
            try {
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
                $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Exception $e) {
                $settings = [];
            }
        }
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
}
