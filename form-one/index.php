<?php
require_once __DIR__ . '/../config/db.php';
header("Location: ../apply.php?class=S1", true, 302);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="refresh" content="0;url=../apply.php?class=S1">
<title>Redirecting to Senior One Admissions...</title>
<script>window.location.replace("../apply.php?class=S1");</script>
<style>
body { font-family: 'Outfit', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f8fafc; color: #334155; }
.card { background: white; padding: 30px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: center; }
a { color: #0c4a24; font-weight: bold; text-decoration: none; }
</style>
</head>
<body>
<div class="card">
    <p>Redirecting to Senior One Application Portal...</p>
    <p><a href="../apply.php?class=S1">Click here if not redirected automatically</a></p>
</div>
</body>
</html>
