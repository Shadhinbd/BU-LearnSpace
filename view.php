<?php
require_once __DIR__ . '/config/db.php';
session_start();
if(!isset($_SESSION['user'])){ header('Location: index.php'); exit; }
$q = trim($_GET['q'] ?? '');
$dept = (int)($_GET['department'] ?? 0);
$sem = (int)($_GET['semester'] ?? 0);

$sql = "SELECT m.*, d.name as dept, s.name as sem FROM materials m JOIN departments d ON m.department_id=d.id JOIN semesters s ON m.semester_id=s.id WHERE 1";
$params = [];
if($q !== ''){ $sql .= " AND m.title LIKE ?"; $params[] = "%$q%"; }
if($dept) { $sql .= " AND m.department_id=?"; $params[] = $dept; }
if($sem) { $sql .= " AND m.semester_id=?"; $params[] = $sem; }
$sql .= " ORDER BY uploaded_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$materials = $stmt->fetchAll();
$departments = $pdo->query("SELECT * FROM departments")->fetchAll();
$semesters = $pdo->query("SELECT * FROM semesters")->fetchAll();
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Materials</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><div class="container">
  <div class="header"><div class="logo">Materials</div>
    <div style="margin-left:auto"><a class="btn" href="index.php">Home</a></div></div>

  <h3>Search Materials</h3>
  <form method="get">
    <div class="form-group"><input class="input" name="q" placeholder="Search by title" value="<?php echo htmlspecialchars($q); ?>"></div>
    <div class="form-group">
      <select name="department" class="input">
        <option value="">-- Department --</option>
        <?php foreach($departments as $d): ?><option value="<?php echo $d['id']; ?>" <?php if($dept==$d['id']) echo 'selected'; ?>><?php echo htmlspecialchars($d['name']); ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <select name="semester" class="input">
        <option value="">-- Semester --</option>
        <?php foreach($semesters as $s): ?><option value="<?php echo $s['id']; ?>" <?php if($sem==$s['id']) echo 'selected'; ?>><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?>
      </select>
    </div>
    <button class="btn">Search</button>
  </form>

  <h4>Results</h4>
  <table class="table"><tr><th>Title</th><th>Dept</th><th>Sem</th><th>Action</th></tr>
  <?php foreach($materials as $m): ?>
    <tr>
      <td><?php echo htmlspecialchars($m['title']); ?></td>
      <td><?php echo htmlspecialchars($m['dept']); ?></td>
      <td><?php echo htmlspecialchars($m['sem']); ?></td>
      <td>
        <?php if(strtolower(pathinfo($m['filename'], PATHINFO_EXTENSION))==='pdf'): ?>
          <a class="btn" href="material_view.php?id=<?php echo $m['id']; ?>" target="_blank">View</a>
        <?php endif; ?>
        <a class="btn" href="download.php?id=<?php echo $m['id']; ?>">Download</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </table>

</div></body></html>
