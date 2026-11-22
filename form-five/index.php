<?php
require_once __DIR__ . '/../config/db.php';
header("Location: ../apply.php?class=S5", true, 302);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="refresh" content="0;url=../apply.php?class=S5">
<title>Redirecting to Senior Five Admissions...</title>
<script>window.location.replace("../apply.php?class=S5");</script>
<style>
body { font-family: 'Outfit', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f8fafc; color: #334155; }
.card { background: white; padding: 30px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: center; }
a { color: #0c4a24; font-weight: bold; text-decoration: none; }
</style>
</head>
<body>
<div class="card">
    <p>Redirecting to Senior Five (A-Level) Application Portal...</p>
    <p><a href="../apply.php?class=S5">Click here if not redirected automatically</a></p>
</div>
</body>
</html>
