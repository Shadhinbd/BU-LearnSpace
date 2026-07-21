<?php
session_start();

// Prevent the browser from caching this protected page (fixes broken
// back/forward navigation between login and dashboard)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require '../db.php';

$msg = "";
$depts = $mysqli->query("SELECT * FROM departments ORDER BY name");

/* =========================
   DELETE USER
========================= */
if (isset($_POST['delete'])) {
    $id = (int)$_POST['id'];

    $stmt = $mysqli->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $msg = "User deleted!";
}

/* =========================
   UPDATE USER
========================= */
if (isset($_POST['update_user'])) {
    $id = (int)$_POST['user_id'];
    $name = trim($_POST['edit_name'] ?? '');
    $email = trim($_POST['edit_email'] ?? '');
    $role = $_POST['edit_role'] ?? 'student';
    $department_id = (int)($_POST['edit_department_id'] ?? 0);
    $batch = trim($_POST['edit_batch'] ?? '');
    if ($role !== 'student') {
        $batch = '';
    }

    if ($name === '' || $email === '' || !in_array($role, ['teacher', 'student'], true) || ($role === 'teacher' && $department_id <= 0) || ($role === 'student' && ($department_id <= 0 || $batch === ''))) {
        $msg = "Please provide valid user details.";
    } else {
        $check = $mysqli->prepare("SELECT id FROM users WHERE email=? AND id<>?");
        $check->bind_param("si", $email, $id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $msg = "Email already exists!";
        } else {
            $stmt = $mysqli->prepare("UPDATE users SET name=?, email=?, role=?, department_id=?, batch=? WHERE id=?");
            $stmt->bind_param("sssisi", $name, $email, $role, $department_id, $batch, $id);
            $stmt->execute();
            $msg = "User updated!";
        }
    }
}

/* =========================
   CREATE USER
========================= */
if (isset($_POST['name']) && isset($_POST['email']) && isset($_POST['password'])) {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    $department_id = (int)($_POST['department_id'] ?? 0);
    $batch = trim($_POST['batch'] ?? '');
    if ($role !== 'student') {
        $batch = '';
    }

    if (!in_array($role, ['teacher', 'student'], true) || ($role === 'teacher' && $department_id <= 0) || ($role === 'student' && ($department_id <= 0 || $batch === ''))) {
        $msg = "Please provide valid user details.";
    } else {
        $check = $mysqli->prepare("SELECT id FROM users WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $msg = "Email already exists!";
        } else {
            $pass = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $mysqli->prepare(
                "INSERT INTO users (name,email,password,role,department_id,batch)
                 VALUES (?,?,?,?,?,?)"
            );

            $stmt->bind_param(
                "ssssis",
                $name,
                $email,
                $pass,
                $role,
                $department_id,
                $batch
            );

            $stmt->execute();

            $msg = "User Created!";
        }
    }
}

/* =========================
   USER SEARCH + ROLE FILTER + PAGINATION
========================= */

$user_search = trim($_GET['user_search'] ?? '');
$user_role = $_GET['role_filter'] ?? 'all';

if (!in_array($user_role, ['all', 'teacher', 'student'], true)) {
    $user_role = 'all';
}

$user_limit = (int)($_GET['user_limit'] ?? 20);

if (!in_array($user_limit, [10, 20, 50], true)) {
    $user_limit = 20;
}

$user_page = max(1, (int)($_GET['user_page'] ?? 1));
$user_offset = ($user_page - 1) * $user_limit;

$user_like = "%" . $user_search . "%";

$countSql = "SELECT COUNT(*) FROM users WHERE (name LIKE ? OR email LIKE ?)";
$countTypes = "ss";
$countParams = [$user_like, $user_like];

if ($user_role === 'teacher' || $user_role === 'student') {
    $countSql .= " AND role=?";
    $countTypes .= "s";
    $countParams[] = $user_role;
}

$countStmt = $mysqli->prepare($countSql);
$countRefs = [&$countTypes];
foreach ($countParams as $key => $value) {
    $countRefs[] = &$countParams[$key];
}
call_user_func_array([$countStmt, 'bind_param'], $countRefs);
$countStmt->execute();
$countStmt->bind_result($user_total);
$countStmt->fetch();
$countStmt->close();

$user_total_pages = max(1, (int)ceil($user_total / $user_limit));

$edit_user_id = max(0, (int)($_GET['edit_id'] ?? 0));
$edit_user = null;

if ($edit_user_id > 0) {
    $editStmt = $mysqli->prepare("SELECT id, name, email, role, department_id, batch FROM users WHERE id=?");
    $editStmt->bind_param("i", $edit_user_id);
    $editStmt->execute();
    $editResult = $editStmt->get_result();
    $edit_user = $editResult->fetch_assoc();
}

$listSql = "SELECT u.id, u.name, u.email, u.role, u.department_id, u.batch, d.name as department_name FROM users u LEFT JOIN departments d ON d.id=u.department_id WHERE (u.name LIKE ? OR u.email LIKE ?)";
$listTypes = "ss";
$listParams = [$user_like, $user_like];

if ($user_role === 'teacher' || $user_role === 'student') {
    $listSql .= " AND u.role=?";
    $listTypes .= "s";
    $listParams[] = $user_role;
}

$listSql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$listTypes .= "ii";
$listParams[] = $user_limit;
$listParams[] = $user_offset;

$listStmt = $mysqli->prepare($listSql);
$listRefs = [&$listTypes];
foreach ($listParams as $key => $value) {
    $listRefs[] = &$listParams[$key];
}
call_user_func_array([$listStmt, 'bind_param'], $listRefs);
$listStmt->execute();
$users = $listStmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>

<link rel="stylesheet" href="../style.css">

<style>

.create-user-form {
    display:flex;
    flex-direction:column;
    gap:12px;
}

.create-user-form input,
.create-user-form select {
    width:100%;
    padding:12px 13px;
    border:1px solid var(--border-light);
    border-radius:12px;
    background:var(--bg-input);
    color:var(--gray-900);
    box-sizing:border-box;
}

.create-user-form button.primary-button {
    width:auto;
    min-width:160px;
    align-self:flex-start;
    margin-top:4px;
}

.user-create-card {
    border:1px solid var(--border);
    border-radius:18px;
    background:linear-gradient(180deg, var(--bg-elevated) 0%, var(--bg-surface) 100%);
    box-shadow:var(--shadow-md);
    padding:18px;
}

.user-create-card h3 {
    margin-bottom:6px;
    color:var(--gray-900);
}

.user-create-card .small {
    color:var(--gray-500);
    margin-top:0;
    margin-bottom:12px;
}

.user-filter-card {
    border:1px solid var(--border);
    border-radius:18px;
    background:linear-gradient(180deg, var(--bg-elevated) 0%, var(--bg-surface) 100%);
    box-shadow:var(--shadow-md);
    padding:18px;
}

.user-toolbar {
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    align-items:flex-end;
}

.user-toolbar .field {
    flex:1;
    min-width:180px;
}

.user-toolbar label {
    display:block;
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:0.16em;
    color:var(--gray-500);
    margin-bottom:6px;
}

.user-toolbar input,
.user-toolbar select {
    width:100%;
    padding:10px 12px;
    border:1px solid var(--border-light);
    border-radius:10px;
    background:var(--bg-input);
    color:var(--gray-900);
    box-sizing:border-box;
}

.filter-btn,
.reset-btn {
    border-radius:10px;
    padding:10px 14px;
    font-weight:600;
}

.reset-btn {
    background:var(--gray-100);
    color:var(--gray-700);
    text-decoration:none;
    display:inline-block;
}

.reset-btn:hover {
    background:var(--gray-200);
}

.user-table-wrap {
    border:1px solid var(--border);
    border-radius:14px;
    overflow:hidden;
    background:var(--bg-elevated);
    margin-top:14px;
    box-shadow:var(--shadow);
}

.user-header {
    display:flex;
    flex-direction:column;
    gap:4px;
    margin-bottom:8px;
}

.user-subtitle {
    margin:0;
    line-height:1.45;
    color:var(--gray-500);
}

.edit-user-card {
    border:1px solid rgba(59, 130, 246, 0.3);
    border-radius:14px;
    background:var(--bg-surface);
    padding:14px;
    margin-bottom:14px;
}

.edit-user-card h4 {
    margin-bottom:4px;
}

.edit-user-grid {
    display:grid;
    grid-template-columns:repeat(3, 1fr);
    gap:10px;
    align-items:end;
}

.edit-user-grid .field {
    display:flex;
    flex-direction:column;
    gap:6px;
}

.edit-user-grid label {
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:0.14em;
    color:var(--gray-500);
}

.edit-actions {
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
}

.edit-actions .primary-button,
.edit-actions .reset-btn {
    width:auto;
    margin-top:0;
}

.user-table {
    width:100%;
    border-collapse:collapse;
    table-layout:fixed;
}

.user-table th,
.user-table td {
    padding:10px 12px;
    border-bottom:1px solid var(--border);
    text-align:left;
    vertical-align:middle;
    word-break:break-word;
    font-size:14px;
    color:var(--gray-700);
}

.user-table thead th {
    background:var(--bg-surface);
    font-size:11px;
    letter-spacing:0.16em;
    text-transform:uppercase;
    color:var(--gray-500);
}

.user-table th:last-child,
.user-table td:last-child {
    text-align:right;
}

.user-table th:nth-child(1),
.user-table td:nth-child(1) { width: 20%; }

.user-table th:nth-child(2),
.user-table td:nth-child(2) { width: 24%; }

.user-table th:nth-child(3),
.user-table td:nth-child(3) { width: 10%; }

.user-table th:nth-child(4),
.user-table td:nth-child(4) { width: 14%; }

.user-table th:nth-child(5),
.user-table td:nth-child(5) { width: 10%; }

.user-table th:nth-child(6),
.user-table td:nth-child(6) { width: 22%; }
.user-email {
    color:var(--gray-600);
}

.role-badge {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border-radius:999px;
    padding:6px 10px;
    font-size:12px;
    font-weight:700;
    text-transform:capitalize;
}

.role-teacher {
    background:rgba(59, 130, 246, 0.15);
    color:#60a5fa;
}

.role-student {
    background:rgba(34, 197, 94, 0.15);
    color:#4ade80;
}

.action-group {
    display:flex;
    align-items:center;
    justify-content:flex-end;
    flex-wrap:wrap;
    gap:6px;
}

.edit-btn {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border-radius:8px;
    padding:7px 10px;
    border:1px solid rgba(59, 130, 246, 0.3);
    background:rgba(59, 130, 246, 0.12);
    color:#60a5fa;
    text-decoration:none;
    font-weight:600;
    font-size:13px;
}

.edit-btn:hover {
    background:rgba(59, 130, 246, 0.22);
}

.delete-btn {
    background:#ef4444;
    color:#fff;
    border-radius:8px;
    padding:7px 10px;
    border:none;
    cursor:pointer;
    font-weight:600;
    font-size:13px;
}

.delete-btn:hover {
    background:#dc2626;
}

.page-strip {
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:12px;
    margin-top:14px;
}

.page-hero-card {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:18px;
    padding:24px 28px;
    border-radius:22px;
    background:linear-gradient(135deg, var(--bg-elevated) 0%, var(--bg-surface) 100%);
    border:1px solid var(--border);
    box-shadow:var(--shadow-md);
    margin-bottom:24px;
}

.page-hero-card h2 {
    margin:6px 0 6px;
    font-size:28px;
    color:var(--gray-900);
}

.page-hero-card .lead {
    margin:0;
    font-size:15px;
    color:var(--gray-500);
    max-width:620px;
}

.header-actions {
    display:flex;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}

.header-badge {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:8px 12px;
    border-radius:999px;
    background:rgba(59, 130, 246, 0.15);
    color:#60a5fa;
    font-size:13px;
    font-weight:700;
}

.logout-button {
    border:none;
    border-radius:999px;
    padding:9px 16px;
    background:linear-gradient(135deg, #0f6df2 0%, #2563eb 100%);
    color:#fff;
    font-weight:700;
    cursor:pointer;
    box-shadow:0 10px 22px rgba(37, 99, 235, 0.24);
}

.logout-button:hover {
    transform:translateY(-1px);
    box-shadow:0 12px 24px rgba(37, 99, 235, 0.28);
}

.count-info {
    color:var(--gray-500);
    font-size:13px;
}

.page-controls {
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:8px;
}

.page-controls label {
    color:var(--gray-600);
    font-size:13px;
}

.page-controls select {
    min-width:90px;
    padding:8px 10px;
    border:1px solid var(--border-light);
    border-radius:8px;
    background:var(--bg-input);
    color:var(--gray-900);
}

.page-controls button {
    padding:8px 12px;
    border-radius:8px;
    border:none;
    background:var(--primary);
    color:#fff;
    cursor:pointer;
    font-weight:600;
}

.page-controls button:hover:not(:disabled) {
    background:var(--primary-dark);
}

.page-controls button:disabled {
    opacity:0.55;
    cursor:not-allowed;
}

.empty-state {
    text-align:center;
    color:var(--gray-500);
    padding:18px;
}

@media(max-width:768px) {
    .user-toolbar {
        align-items:stretch;
    }

    .page-strip {
        align-items:flex-start;
    }
}

</style>

</head>
<body>

<div class="container dashboard-shell">

  <section class="page-hero-card">
    <div>
      <p class="eyebrow">Admin Panel</p>
      <h2>Admin Dashboard</h2>
      <p class="lead">Manage teachers, students, and account access from one clean workspace.</p>
    </div>
    <div class="header-actions">
      <span class="header-badge">Admin</span>
      <form class="logout-form" action="logout.php" method="post">
        <button type="submit" class="logout-button">Logout</button>
      </form>
    </div>
  </section>

<?php if ($msg != ""): ?>
<div class="status-banner success">
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- CREATE USER -->

<div class="card user-create-card">
<?php if ($edit_user): ?>
<h3>Edit User</h3>
<p class="small">Update the selected account details below.</p>
<form method="post" class="create-user-form">
    <input type="hidden" name="user_id" value="<?= (int)$edit_user['id'] ?>">
    <input
        type="text"
        name="edit_name"
        placeholder="Name"
        value="<?= htmlspecialchars($edit_user['name']) ?>"
        required>

    <input
        type="email"
        name="edit_email"
        placeholder="Email"
        value="<?= htmlspecialchars($edit_user['email']) ?>"
        required>

    <label for="edit_role" style="font-size:12px; color:var(--gray-500); font-weight:700; margin-bottom:-2px;">Role</label>
    <select id="edit_role" name="edit_role" data-role-select data-batch-field="edit_batch_field">
        <option value="teacher" <?= $edit_user['role'] === 'teacher' ? 'selected' : '' ?>>Teacher</option>
        <option value="student" <?= $edit_user['role'] === 'student' ? 'selected' : '' ?>>Student</option>
    </select>

    <label for="edit_department_id" style="font-size:12px; color:var(--gray-500); font-weight:700; margin-bottom:-2px;">Department</label>
    <select id="edit_department_id" name="edit_department_id" required>
        <option value="">Select Department</option>
        <?php $depts->data_seek(0); while ($d = $depts->fetch_assoc()): ?>
        <option value="<?= (int)$d['id'] ?>" <?= (int)$edit_user['department_id'] === (int)$d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
        <?php endwhile; ?>
    </select>

    <div class="form-group batch-field" id="edit_batch_field">
        <label for="edit_batch" style="font-size:12px; color:var(--gray-500); font-weight:700; margin-bottom:-2px;">Batch</label>
        <input id="edit_batch" type="text" name="edit_batch" placeholder="e.g. 2024" value="<?= htmlspecialchars($edit_user['batch'] ?? '') ?>">
    </div>

    <div class="edit-actions">
        <button type="submit" name="update_user" class="primary-button">Update User</button>
        <a href="dashboard.php?user_search=<?= urlencode($user_search) ?>&role_filter=<?= urlencode($user_role) ?>&user_limit=<?= (int)$user_limit ?>&user_page=<?= (int)$user_page ?>" class="reset-btn">Cancel</a>
    </div>
</form>
<?php else: ?>
<h3>Create Teacher / Student</h3>
<p class="small">Add a teacher or student account with a clear, readable form layout.</p>

<form method="post" class="create-user-form">

<input
    type="text"
    name="name"
    placeholder="Name"
    required>

<input
    type="email"
    name="email"
    placeholder="Email"
    required>

<input
    type="password"
    name="password"
    placeholder="Password"
    required>

<label for="role" style="font-size:12px; color:var(--gray-500); font-weight:700; margin-bottom:-2px;">Role</label>
<select id="role" name="role" data-role-select data-batch-field="batch_field">
    <option value="teacher">Teacher</option>
    <option value="student">Student</option>
</select>

<label for="department_id" style="font-size:12px; color:var(--gray-500); font-weight:700; margin-bottom:-2px;">Department</label>
<select id="department_id" name="department_id" required>
    <option value="">Select Department</option>
    <?php $depts->data_seek(0); while ($d = $depts->fetch_assoc()): ?>
    <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
    <?php endwhile; ?>
</select>

<div class="form-group batch-field" id="batch_field">
    <label for="batch" style="font-size:12px; color:var(--gray-500); font-weight:700; margin-bottom:-2px;">Batch</label>
    <input id="batch" type="text" name="batch" placeholder="e.g. 2024">
</div>

<button type="submit" class="primary-button">
    Create Account
</button>

</form>
<?php endif; ?>
</div>

<!-- USERS -->

<div class="card user-filter-card">

    <div class="user-header">
        <h3>All Users</h3>
        <p class="small user-subtitle">Search by name or email, filter by role, and manage everything from one place.</p>
    </div>

    <form method="get" class="user-toolbar">
        <div class="field">
            <label for="user_search">Search</label>
            <input
                id="user_search"
                type="text"
                name="user_search"
                placeholder="Search by name or email"
                value="<?= htmlspecialchars($user_search) ?>">
        </div>

        <div class="field">
            <label for="role_filter">Role</label>
            <select id="role_filter" name="role_filter">
                <option value="all" <?= $user_role === 'all' ? 'selected' : '' ?>>All Users</option>
                <option value="teacher" <?= $user_role === 'teacher' ? 'selected' : '' ?>>Teachers</option>
                <option value="student" <?= $user_role === 'student' ? 'selected' : '' ?>>Students</option>
            </select>
        </div>

        <div class="field" style="max-width:150px;">
            <label for="user_limit">Rows per page</label>
            <select id="user_limit" name="user_limit">
                <option value="10" <?= $user_limit == 10 ? 'selected' : '' ?>>10</option>
                <option value="20" <?= $user_limit == 20 ? 'selected' : '' ?>>20</option>
                <option value="50" <?= $user_limit == 50 ? 'selected' : '' ?>>50</option>
            </select>
        </div>

        <button type="submit" class="filter-btn">Filter</button>
        <a href="dashboard.php" class="reset-btn">Reset</a>
    </form>

    <div class="user-table-wrap" style="margin-top:14px;">
        <table class="user-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Batch</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users->num_rows > 0): ?>
                    <?php while($u = $users->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="user-name"><?= htmlspecialchars($u['name']) ?></div>
                            </td>
                            <td class="user-email"><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <span class="role-badge role-<?= htmlspecialchars($u['role']) ?>">
                                    <?= htmlspecialchars(ucfirst($u['role'])) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($u['department_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($u['batch'] ?? '—') ?></td>
                            <td>
                                <div class="action-group">
                                    <a class="edit-btn" href="dashboard.php?edit_id=<?= (int)$u['id'] ?>&user_search=<?= urlencode($user_search) ?>&role_filter=<?= urlencode($user_role) ?>&user_limit=<?= (int)$user_limit ?>&user_page=<?= (int)$user_page ?>">Edit</a>
                                    <form method="post" class="inline-action-form">
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <button
                                            type="submit"
                                            name="delete"
                                            class="delete-btn"
                                            onclick="return confirm('Delete this user?')">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="empty-state">No users matched your search.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="page-strip">
        <div class="count-info">
            Showing <?= $users->num_rows ?> of <?= $user_total ?> user(s) • Page <?= $user_page ?> of <?= $user_total_pages ?>
        </div>

        <form method="get" class="page-controls">
            <label for="user_page_limit">Rows</label>
            <select id="user_page_limit" name="user_limit" onchange="this.form.submit()">
                <option value="10" <?= $user_limit == 10 ? 'selected' : '' ?>>10</option>
                <option value="20" <?= $user_limit == 20 ? 'selected' : '' ?>>20</option>
                <option value="50" <?= $user_limit == 50 ? 'selected' : '' ?>>50</option>
            </select>

            <input type="hidden" name="user_search" value="<?= htmlspecialchars($user_search) ?>">
            <input type="hidden" name="role_filter" value="<?= htmlspecialchars($user_role) ?>">
            <input type="hidden" name="user_page" value="1">

            <button type="submit" name="user_page" value="<?= max(1, $user_page - 1) ?>" <?= $user_page <= 1 ? 'disabled' : '' ?>>Previous</button>
            <button type="submit" name="user_page" value="<?= min($user_total_pages, $user_page + 1) ?>" <?= $user_page >= $user_total_pages ? 'disabled' : '' ?>>Next</button>
        </form>
    </div>

</div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  function toggleBatchField(selectEl, fieldEl) {
    const isStudent = selectEl.value === 'student';
    fieldEl.style.display = isStudent ? '' : 'none';
    const input = fieldEl.querySelector('input');
    if (input) {
      input.required = isStudent;
      if (!isStudent) {
        input.value = '';
      }
    }
  }

  document.querySelectorAll('[data-role-select]').forEach(function (selectEl) {
    const fieldId = selectEl.getAttribute('data-batch-field');
    const fieldEl = document.getElementById(fieldId);
    if (fieldEl) {
      toggleBatchField(selectEl, fieldEl);
      selectEl.addEventListener('change', function () {
        toggleBatchField(selectEl, fieldEl);
      });
    }
  });
});
</script>

</body>
</html>