<?php ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>BU LearnSpace</title>
<link rel="stylesheet" href="style.css">

<style>

/* WHITE full background */
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: white;
}

/* Header text */
.header-box {
    text-align: center;
    padding: 40px 20px 10px;
}

.header-title {
    font-size: 46px;
    font-weight: 900;
    color: #0b66c3;  /* Only this is blue */
    margin-bottom: 5px;
}

.header-sub {
    font-size: 16px;
    color: #0b66c3; /* Also blue */
    opacity: 0.8;
}

/* 3 separate login boxes */
.login-grid {
    display: flex;
    justify-content: center;
    gap: 25px;
    margin-top: 40px;
    flex-wrap: wrap;
}

/* individual card */
.login-card {
    width: 260px;
    background: #ffffff;
    padding: 25px;
    border-radius: 14px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: 0.2s;
}

.login-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 18px rgba(0,0,0,0.18);
}

/* button inside card */
.login-btn {
    display: inline-block;
    margin-top: 10px;
    background: #0b66c3;
    color: white;
    text-decoration: none;
    padding: 12px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: bold;
    width: 100%;
}

.login-btn:hover {
    background: #094f99;
}

/* Footer */
.footer {
    text-align: center;
    margin: 40px 0 20px;
    color: #333;
    font-size: 14px;
}

</style>

</head>

<body>

    <div class="header-box">
        <div class="header-title">BU LearnSpace</div>
        <div class="header-sub">Your Digital Learning Platform for Bangladesh University</div>
    </div>

    <div class="login-grid">

        <!-- Admin Card -->
        <div class="login-card">
            <h3>Admin Panel</h3>
            <a href="admin/login.php" class="login-btn">Admin Login</a>
        </div>

        <!-- Teacher Card -->
        <div class="login-card">
            <h3>Teacher Panel</h3>
            <a href="teacher/login.php" class="login-btn">Teacher Login</a>
        </div>

        <!-- Student Card -->
        <div class="login-card">
            <h3>Student Panel</h3>
            <a href="student/login.php" class="login-btn">Student Login</a>
        </div>

    </div>

    <div class="footer">
        © <?php echo date('Y'); ?> Bangladesh University — BU LearnSpace
    </div>

</body>
</html>
