<?php ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BU LearnSpace</title>
<link rel="stylesheet" href="style.css">

<style>

* { box-sizing: border-box; }

html, body {
    height: 100%;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background-color: #141d2f;
    color: #e2e8f0;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ---------- Header ---------- */
.site-header {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 22px 24px;
    background: #141d2f;
    border-bottom: 1px solid #263449;
}

.site-title-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.site-logo {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    object-fit: cover;
    flex-shrink: 0;
}

.site-heading-text { line-height: 1.2; }

.site-title {
    font-size: 35px;
    line-height: 42px;
    font-weight: 900;
    color: #3b82f6;
    letter-spacing: 0.3px;
}

.site-subtitle {
    font-size: 15px;
    color: #94a3b8;
    margin-top: 2px;
}

/* ---------- Split screen ---------- */
.split-screen {
    flex: 1;
    display: flex;
    min-height: 0;
}

.split-left {
    flex: 1 1 58%;
    position: relative;
    background-image: url('assets/images/back.jpg');
    background-size: cover;
    background-position: center;
    min-height: 420px;
}

.split-left::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(to right, rgba(20, 29, 47, 0) 60%, #141d2f 100%);
}

.split-right {
    flex: 1 1 42%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 48px 24px;
    background-color: #141d2f;
    background-image:
        radial-gradient(at 85% 10%, rgba(139, 92, 246, 0.22) 0, transparent 50%),
        radial-gradient(at 90% 90%, rgba(59, 130, 246, 0.18) 0, transparent 50%);
}

.login-stack-wrap {
    width: 100%;
    max-width: 360px;
}

.login-stack-intro {
    text-align: center;
    margin-bottom: 26px;
}

.login-stack-intro h1 {
    font-size: 24px;
    margin: 0 0 6px;
    color: #f1f5f9;
}

.login-stack-intro p {
    font-size: 14px;
    color: #94a3b8;
    margin: 0;
}

/* 3 stacked login cards */
.login-stack {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.login-card {
    background: #1b2740;
    border: 1px solid #2a3a54;
    padding: 18px 22px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
    transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
}

.login-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.45);
    border-color: #3b4a63;
}

.login-card-text h3 {
    margin: 0 0 2px;
    color: #f1f5f9;
    font-size: 16px;
}

.login-card-text p {
    margin: 0;
    color: #94a3b8;
    font-size: 12.5px;
}

.login-btn {
    display: inline-block;
    flex-shrink: 0;
    background: linear-gradient(135deg, #2563eb, #3b82f6);
    color: white;
    text-decoration: none;
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: bold;
    white-space: nowrap;
}

.login-btn:hover {
    background: linear-gradient(135deg, #1d4ed8, #2563eb);
}

/* ---------- Footer ---------- */
.site-footer {
    text-align: center;
    padding: 16px;
    color: #64748b;
    font-size: 13px;
    background: #101828;
    border-top: 1px solid #263449;
}

/* ---------- Responsive ---------- */
@media (max-width: 900px) {
    .split-screen { flex-direction: column; }
    .split-left { flex: 0 0 220px; min-height: 220px; }
    .split-left::after { background: linear-gradient(to bottom, rgba(20, 29, 47, 0) 55%, #141d2f 100%); }
    .split-right { padding: 32px 20px; }
}

@media (max-width: 480px) {
    .site-header { text-align: center; }
    .login-card { flex-direction: column; align-items: stretch; text-align: center; }
    .login-btn { width: 100%; text-align: center; }
}

</style>

</head>

<body>

    <header class="site-header">
        <div class="site-heading-text">
            <div class="site-title-row">
                <div class="site-title">BU LearnSpace</div>
                <img src="assets/images/logo-bu.jpeg" alt="Bangladesh University Logo" class="site-logo">
            </div>
            <div class="site-subtitle">A Digital Academic Platform for Bangladesh University</div>
        </div>
    </header>

    <div class="split-screen">
        <div class="split-left" role="img" aria-label="Bangladesh University campus life"></div>

        <div class="split-right">
            <div class="login-stack-wrap">
                <div class="login-stack-intro">
                    <h1>Welcome back</h1>
                    <p>Choose your portal to continue</p>
                </div>

                <div class="login-stack">

                    <div class="login-card">
                        <div class="login-card-text">
                            <h3>Student Panel</h3>
                            <p>Materials, marks & assignments</p>
                        </div>
                        <a href="student/login.php" class="login-btn">Login</a>
                    </div>

                    <div class="login-card">
                        <div class="login-card-text">
                            <h3>Teacher Panel</h3>
                            <p>Manage classes & grading</p>
                        </div>
                        <a href="teacher/login.php" class="login-btn">Login</a>
                    </div>

                    <div class="login-card">
                        <div class="login-card-text">
                            <h3>Admin Panel</h3>
                            <p>Platform administration</p>
                        </div>
                        <a href="admin/login.php" class="login-btn">Login</a>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <footer class="site-footer">
        © <?php echo date('Y'); ?> Bangladesh University — BU LearnSpace
    </footer>

</body>
</html>
