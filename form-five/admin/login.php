<?php
session_start();
require '../config/db.php';

$error = '';
$MAX_ATTEMPTS = 5;
$LOCK_MINUTES = 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {

        // Check lock status
        if ($admin['lock_until'] && strtotime($admin['lock_until']) > time()) {
            $error = "Account locked. Try again later.";
        } 
        elseif (password_verify($password, $admin['password'])) {

            // Reset failed attempts on success
            $pdo->prepare("
                UPDATE admin_users 
                SET failed_attempts = 0, lock_until = NULL 
                WHERE id = ?
            ")->execute([$admin['id']]);

            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            
            // Log successful login
            logActivity($pdo, $admin['username'], "Login", "Successful session login");

            header("Location: dashboard.php");
            exit;

        } else {
            // Failed login attempt
            $attempts = $admin['failed_attempts'] + 1;
            $lockUntil = null;

            if ($attempts >= $MAX_ATTEMPTS) {
                $lockUntil = date("Y-m-d H:i:s", strtotime("+$LOCK_MINUTES minutes"));
            }

            $pdo->prepare("
                UPDATE admin_users 
                SET failed_attempts = ?, lock_until = ? 
                WHERE id = ?
            ")->execute([$attempts, $lockUntil, $admin['id']]);

            $error = "Invalid credentials ($attempts/$MAX_ATTEMPTS)";
        }

    } else {
        $error = "Invalid credentials";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Login | Admissions Portal</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../../css/style.css" rel="stylesheet">
</head>

<body>

<div class="login-container">
    <div class="login-card">

        <h4>Admin Login</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger text-center">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <input 
                type="text" 
                name="username" 
                class="form-control mb-3" 
                placeholder="Username" 
                required
            >

            <input 
                type="password" 
                name="password" 
                class="form-control mb-3" 
                placeholder="Password" 
                required
            >

            <button type="submit" class="btn btn-primary w-100">
                Login
            </button>
        </form>

        <div class="login-footer">
            © <?= date('Y') ?> Nova Cloud Edges · Admin Panel
        </div>

    </div>
</div>

</body>
</html>
