<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Application Submitted | St. Paul's Mutolere SS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../css/style.css" rel="stylesheet">
<style>
.success-card {
    background: var(--bg-card);
    max-width: 550px;
    margin: 80px auto;
    padding: 50px 40px;
    border-radius: var(--radius-lg);
    border: 1px solid rgba(12, 74, 36, 0.08);
    box-shadow: var(--shadow-xl);
    text-align: center;
    animation: fadeIn 0.6s ease-in-out;
}
.success-icon {
    font-size: 4.5rem;
    color: var(--primary-color);
    margin-bottom: 25px;
}
.success-card h3 {
    color: var(--primary-color);
    font-weight: 800;
    margin-bottom: 15px;
    letter-spacing: -0.5px;
}
.success-card p {
    color: var(--text-dark);
    font-size: 1.05rem;
    line-height: 1.6;
    margin-bottom: 30px;
}
.btn-home {
    background: linear-gradient(135deg, var(--gold-color), var(--gold-light));
    border: none;
    border-radius: var(--radius-full);
    padding: 12px 30px;
    font-weight: 700;
    color: white !important;
    box-shadow: var(--shadow-gold);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: var(--transition-normal);
}
.btn-home:hover {
    transform: translateY(-2px);
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    box-shadow: var(--shadow-md);
}
</style>
</head>
<body>

<div class="success-card">
    <div class="success-icon">
        <i class="fa-solid fa-circle-check"></i>
    </div>
    <h3>Application Submitted Successfully</h3>
    <p>
        Your application details have been received by the Admissions Office.<br>
        Please check your parent/guardian email inbox for confirmation details.
    </p>
    <a href="../index.php" class="btn btn-primary btn-home">
        <i class="fa-solid fa-arrow-left-long"></i> Back to Homepage
    </a>
</div>

</body>
</html>
