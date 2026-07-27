<?php $current = basename($_SERVER['PHP_SELF']); ?>
<aside class="sidebar">
  <a href="../student/profile.php" class="profile-link <?= $current==='profile.php'?'active':'' ?>">
    <div class="profile-avatar">
      <?php
      $studentPhoto = $_SESSION['student']['profile_photo'] ?? '';
      if (!empty($studentPhoto) && file_exists(__DIR__ . '/../uploads/profile_photos/' . $studentPhoto)) {
          echo '<img src="../uploads/profile_photos/' . htmlspecialchars($studentPhoto) . '?t=' . time() . '" alt="Profile photo">';
      } else {
          echo strtoupper(substr(trim($_SESSION['student']['name'] ?? 'S'), 0, 1));
      }
      ?>
    </div>
    <div class="profile-meta">
      <strong><?= htmlspecialchars($_SESSION['student']['name'] ?? 'Student') ?></strong>
      <span>View profile</span>
    </div>
  </a>

  <nav class="sidebar-nav">
    <a href="../student/dashboard.php" class="<?= $current==='dashboard.php'?'active':'' ?>">&#9632; Dashboard</a>
    <a href="../student/view.php" class="<?= $current==='view.php'?'active':'' ?>">&#128196; Study Materials</a>
    <a href="../student/bookmarks.php" class="<?= $current==='bookmarks.php'?'active':'' ?>">&#128278; Bookmarks</a>
    <a href="../student/assignments.php" class="<?= $current==='assignments.php'?'active':'' ?>">&#128221; Assignments</a>
    <a href="../student/marks.php" class="<?= $current==='marks.php'?'active':'' ?>">&#127891; My Marks</a>
    <a href="../student/attendance.php" class="<?= $current==='attendance.php'?'active':'' ?>">&#10003; My Attendance</a>
  </nav>
  <div class="sidebar-footer">
    <span><?= htmlspecialchars($_SESSION['student']['name'] ?? '') ?></span>
    <form action="../student/logout.php" method="post">
      <button type="submit" class="sidebar-logout">Logout</button>
    </form>
  </div>
</aside>
