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
            
            // Log activity
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

            logActivity($pdo, $username, "Login Failed", "Attempts: $attempts");
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | Admissions Control Panel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../css/style.css" rel="stylesheet">
</head>
<body>

<!-- Dynamic Background Slideshow -->
<div class="bg-slideshow-container">
    <div class="bg-slide active" style="background-image: url('../images/campus.png');"></div>
    <div class="bg-slide" style="background-image: url('../images/students.png');"></div>
    <div class="bg-slide" style="background-image: url('../images/lab.png');"></div>
</div>

<div class="login-container">
    <div class="login-card">
        <div class="text-center mb-3">
            <img src="https://www.ncedges.com/wp-content/uploads/2026/02/mutosp-logo.png" alt="School Logo" style="width: 60px; height: 60px; background: white; padding: 3px; border-radius: 50%;">
        </div>
        
        <h4 class="text-center text-success font-bold mb-1">MUTOLERE SS</h4>
        <p class="text-center text-slate-500 text-xs mb-4 uppercase tracking-widest">Admissions Control Login</p>

        <?php if ($error): ?>
            <div class="alert alert-danger text-center text-xs py-2">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['changed'])): ?>
            <div class="alert alert-success text-center text-xs py-2">
                Password updated successfully. Please log in.
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

            <button type="submit" class="btn btn-primary w-100 py-2 font-bold uppercase tracking-wider text-xs">
                Log In <i class="fa-solid fa-right-to-bracket ms-1"></i>
            </button>
        </form>

        <div class="login-footer mt-4 text-center text-slate-400 text-xs">
            © <?= date('Y') ?> St. Paul's Mutolere SS · Admissions Office
        </div>

    </div>
</div>

<script>
// Background Slideshow
let activeSlideIndex = 0;
const slides = document.querySelectorAll('.bg-slide');
setInterval(() => {
    if (slides.length > 0) {
        slides[activeSlideIndex].classList.remove('active');
        activeSlideIndex = (activeSlideIndex + 1) % slides.length;
        slides[activeSlideIndex].classList.add('active');
    }
}, 5000);
</script>

</body>
</html>
