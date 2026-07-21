<?php $current = basename($_SERVER['PHP_SELF']); ?>
<aside class="sidebar">
  <a href="../teacher/profile.php" class="profile-link <?= $current==='profile.php'?'active':'' ?>">
    <div class="profile-avatar">
      <?php
      $teacherPhoto = $_SESSION['user']['profile_photo'] ?? '';
      if (!empty($teacherPhoto) && file_exists(__DIR__ . '/../uploads/profile_photos/' . $teacherPhoto)) {
          echo '<img src="../uploads/profile_photos/' . htmlspecialchars($teacherPhoto) . '?t=' . time() . '" alt="Profile photo">';
      } else {
          echo strtoupper(substr(trim($_SESSION['user']['name'] ?? 'T'), 0, 1));
      }
      ?>
    </div>
    <div class="profile-meta">
      <strong><?= htmlspecialchars($_SESSION['user']['name'] ?? 'Teacher') ?></strong>
      <span>View profile</span>
    </div>
  </a>

  <nav class="sidebar-nav">
    <a href="../teacher/dashboard.php" class="<?= $current==='dashboard.php'?'active':'' ?>">&#9632; Dashboard</a>
    <a href="../teacher/materials.php" class="<?= $current==='materials.php'?'active':'' ?>">&#128196; Materials</a>
    <a href="../teacher/assignments.php" class="<?= $current==='assignments.php'?'active':'' ?>">&#128221; Assignments</a>
    <a href="../teacher/marks.php" class="<?= $current==='marks.php'?'active':'' ?>">&#127891; Assessment & Marks</a>
    <a href="../teacher/attendance.php" class="<?= $current==='attendance.php'?'active':'' ?>">&#10003; Attendance</a>
  </nav>
  <div class="sidebar-footer">
    <span><?= htmlspecialchars($_SESSION['user']['name'] ?? '') ?></span>
    <form action="../teacher/logout.php" method="post">
      <button type="submit" class="sidebar-logout">Logout</button>
    </form>
  </div>
</aside>
