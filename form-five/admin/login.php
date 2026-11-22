<?php
session_start();
require '../config/db.php';

$school_name       = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$logo_source       = getSchoolLogoSrc($pdo, '../../');

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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>S5 Admin Login | <?= htmlspecialchars($school_short_name) ?></title>
<!-- Tailwind CDN -->
<script src="https://cdn.tailwindcss.com" data-cfasync="false"></script>
<!-- Font Awesome & Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<?= renderThemeConfig($pdo) ?>
<style>
.bg-slideshow-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 0;
    overflow: hidden;
    background-color: #0f172a;
}
.bg-slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    opacity: 0;
    transition: opacity 1.5s ease-in-out;
}
.bg-slide.active {
    opacity: 0.35;
}
</style>
</head>
<body class="font-sans text-slate-800 min-h-screen flex items-center justify-center p-4 relative bg-slate-900">

<!-- Dynamic Background Slideshow -->
<div class="bg-slideshow-container">
    <div class="bg-slide active" style="background-image: url('../../images/campus.png');"></div>
    <div class="bg-slide" style="background-image: url('../../images/students.png');"></div>
    <div class="bg-slide" style="background-image: url('../../images/lab.png');"></div>
</div>

<div class="relative z-10 w-full max-w-md my-8">
    <div class="bg-white/95 backdrop-blur-md border border-slate-200/80 rounded-2xl shadow-2xl p-6 sm:p-8 space-y-6">
        
        <!-- Header & Logo -->
        <div class="text-center">
            <div class="inline-flex p-2 rounded-full bg-white shadow-md border-2 border-gold-500 mb-3">
                <img src="<?= htmlspecialchars($logo_source) ?>" alt="School Logo" class="w-16 h-16 object-contain">
            </div>
            <h2 class="text-xl sm:text-2xl font-extrabold text-brand-700 tracking-tight uppercase leading-tight"><?= htmlspecialchars($school_short_name) ?></h2>
            <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mt-1">Senior Five (S5) Admissions Control Login</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-800 p-3.5 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-500 text-base shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Username</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <i class="fa-solid fa-user"></i>
                    </span>
                    <input 
                        type="text" 
                        name="username" 
                        class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 focus:bg-white focus:ring-4 focus:ring-brand-700/10 font-medium transition" 
                        placeholder="Enter your username" 
                        required
                    >
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                    <input 
                        type="password" 
                        name="password" 
                        class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 focus:bg-white focus:ring-4 focus:ring-brand-700/10 font-medium transition" 
                        placeholder="Enter your password" 
                        required
                    >
                </div>
            </div>

            <button type="submit" class="w-full py-3 bg-brand-700 hover:bg-brand-800 text-white font-bold text-sm uppercase tracking-wider rounded-xl shadow-lg shadow-brand-700/25 transition duration-150 flex items-center justify-center gap-2 mt-2">
                <span>Sign In to S5 Control</span>
                <i class="fa-solid fa-arrow-right-to-bracket"></i>
            </button>
        </form>

        <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-400 gap-2">
            <a href="../index.php" class="text-brand-700 hover:text-brand-800 font-semibold inline-flex items-center gap-1.5 transition">
                <i class="fa-solid fa-arrow-left"></i>
                <span>S5 Application Form</span>
            </a>
            <span>© <?= date('Y') ?> <?= htmlspecialchars($school_short_name) ?></span>
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
