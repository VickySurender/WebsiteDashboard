<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$userId   = $_SESSION['user_id'];
$fullName = $_SESSION['full_name'];
$email    = $_SESSION['email'];
$role     = $_SESSION['role'];
$avatarColor = $_SESSION['avatar_color'];

// Stats
$totalUsers = $conn->query('SELECT COUNT(*) AS c FROM users')->fetch_assoc()['c'];
$stmt = $conn->prepare('SELECT created_at, last_login FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();

// Recent activity log
$logStmt = $conn->prepare(
    'SELECT action, ip_address, created_at FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 8'
);
$logStmt->bind_param('i', $userId);
$logStmt->execute();
$activities = $logStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Initials for avatar
$nameParts = explode(' ', trim($fullName));
$initials  = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));

$joinedDays = max(1, (int) round((time() - strtotime($userRow['created_at'])) / 86400));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — AuthSystem</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:      #0d0f14;
    --surface: #161922;
    --surface2:#1e2330;
    --border:  #252a35;
    --accent:  #6C63FF;
    --accent2: #FF6584;
    --accent3: #20BF6B;
    --text:    #e8eaf0;
    --muted:   #6b7280;
    --sidebar: 240px;
  }

  body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; }

  /* ── Sidebar ─────────────────────────────── */
  .sidebar {
    width: var(--sidebar);
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    padding: 1.5rem 1rem;
    z-index: 100;
  }

  .sidebar-logo {
    font-family:'Syne',sans-serif;font-weight:800;font-size:1.3rem;
    letter-spacing:-.02em;display:flex;align-items:center;gap:.5rem;margin-bottom:2.5rem;padding-left:.4rem;
  }
  .sidebar-logo span {
    width:28px;height:28px;background:linear-gradient(135deg,var(--accent),var(--accent2));
    border-radius:7px;display:inline-block;flex-shrink:0;
  }

  .nav-section { font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);padding:.3rem .6rem;margin-top:1rem;margin-bottom:.4rem; }

  .nav-item {
    display:flex;align-items:center;gap:.7rem;padding:.65rem .8rem;border-radius:10px;
    font-size:.9rem;color:var(--muted);text-decoration:none;transition:all .15s;margin-bottom:.2rem;
    cursor:pointer;border:none;background:none;width:100%;text-align:left;
  }
  .nav-item:hover { background:var(--surface2);color:var(--text); }
  .nav-item.active { background:rgba(108,99,255,.15);color:var(--accent); }
  .nav-item svg { flex-shrink:0; }

  .sidebar-bottom {
    margin-top:auto;padding-top:1rem;border-top:1px solid var(--border);
  }
  .user-card {
    display:flex;align-items:center;gap:.7rem;padding:.6rem;border-radius:10px;
    background:var(--surface2);
  }
  .avatar {
    width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;
    font-family:'Syne',sans-serif;font-weight:700;font-size:.75rem;color:#fff;flex-shrink:0;
  }
  .user-info { overflow:hidden; }
  .user-name  { font-size:.85rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
  .user-email { font-size:.72rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }

  /* ── Main ─────────────────────────────────── */
  .main { margin-left:var(--sidebar);flex:1;padding:2rem;min-height:100vh; }

  .topbar {
    display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;
  }
  .page-title { font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700; }
  .page-sub   { color:var(--muted);font-size:.875rem;margin-top:.2rem; }

  .topbar-right { display:flex;align-items:center;gap:1rem; }
  .badge { background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:.4rem .8rem;font-size:.8rem;color:var(--muted); }
  .badge strong { color:var(--accent); }

  .logout-btn {
    display:flex;align-items:center;gap:.4rem;padding:.45rem 1rem;background:rgba(252,92,101,.12);
    border:1px solid rgba(252,92,101,.25);border-radius:8px;color:#ff8a93;font-size:.875rem;
    text-decoration:none;font-weight:500;transition:all .2s;cursor:pointer;
  }
  .logout-btn:hover { background:rgba(252,92,101,.2); }

  /* ── Stats Grid ──────────────────────────── */
  .stats-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:2rem; }

  .stat-card {
    background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:1.4rem;
    position:relative;overflow:hidden;animation:fadeIn .4s ease both;
  }
  .stat-card:nth-child(2) { animation-delay:.08s; }
  .stat-card:nth-child(3) { animation-delay:.16s; }
  .stat-card:nth-child(4) { animation-delay:.24s; }

  @keyframes fadeIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

  .stat-card::before {
    content:'';position:absolute;top:-30px;right:-30px;width:80px;height:80px;
    border-radius:50%;opacity:.12;
  }
  .stat-card:nth-child(1)::before { background:var(--accent); }
  .stat-card:nth-child(2)::before { background:var(--accent2); }
  .stat-card:nth-child(3)::before { background:var(--accent3); }
  .stat-card:nth-child(4)::before { background:#F7B731; }

  .stat-icon {
    width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:1rem;
  }
  .stat-value { font-family:'Syne',sans-serif;font-size:2rem;font-weight:700;line-height:1; }
  .stat-label { color:var(--muted);font-size:.85rem;margin-top:.3rem; }
  .stat-delta { font-size:.75rem;margin-top:.5rem; }

  /* ── Content Grid ────────────────────────── */
  .content-grid { display:grid;grid-template-columns:1fr 360px;gap:1.5rem; }

  .panel {
    background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:1.5rem;
  }
  .panel-title { font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;margin-bottom:1.2rem;display:flex;align-items:center;justify-content:space-between; }
  .panel-title span { font-size:.75rem;color:var(--muted);font-family:'DM Sans',sans-serif;font-weight:400; }

  /* Activity List */
  .activity-item {
    display:flex;align-items:center;gap:.8rem;padding:.7rem 0;border-bottom:1px solid var(--border);
  }
  .activity-item:last-child { border-bottom:none; }
  .activity-dot { width:8px;height:8px;border-radius:50%;background:var(--accent);flex-shrink:0; }
  .activity-text { font-size:.875rem; }
  .activity-meta { font-size:.75rem;color:var(--muted);margin-top:.15rem; }

  /* Profile Card */
  .profile-avatar {
    width:72px;height:72px;border-radius:50%;display:flex;align-items:center;justify-content:center;
    font-family:'Syne',sans-serif;font-weight:700;font-size:1.4rem;color:#fff;margin:0 auto 1rem;
  }
  .profile-name  { font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:700;text-align:center; }
  .profile-email { color:var(--muted);font-size:.85rem;text-align:center;margin-top:.2rem; }
  .profile-badge {
    display:inline-flex;align-items:center;gap:.3rem;background:rgba(108,99,255,.15);
    color:var(--accent);border-radius:20px;padding:.2rem .7rem;font-size:.75rem;font-weight:500;
    margin:.8rem auto 1.2rem;display:flex;justify-content:center;width:fit-content;margin-inline:auto;
  }
  .profile-stats { display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-top:1.2rem; }
  .ps { background:var(--surface2);border-radius:10px;padding:.8rem;text-align:center; }
  .ps-val { font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:700; }
  .ps-lbl { font-size:.72rem;color:var(--muted);margin-top:.2rem; }

  .edit-btn {
    display:block;text-align:center;margin-top:1.2rem;padding:.6rem;
    background:var(--surface2);border:1px solid var(--border);border-radius:8px;
    color:var(--text);font-size:.875rem;text-decoration:none;transition:all .2s;
  }
  .edit-btn:hover { border-color:var(--accent);color:var(--accent); }

  @media (max-width:900px) {
    .content-grid { grid-template-columns:1fr; }
    .sidebar { display:none; }
    .main { margin-left:0; }
  }
</style>
</head>
<body>

<!-- ── Sidebar ─────────────────────────────────────────────── -->
<aside class="sidebar">
  <div class="sidebar-logo"><span></span> AuthSystem</div>

  <div class="nav-section">Main</div>
  <a class="nav-item active" href="dashboard.php">
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
    Dashboard
  </a>
  <a class="nav-item" href="#">
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    Profile
  </a>
  <a class="nav-item" href="#">
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>
    Analytics
  </a>

  <div class="nav-section">Settings</div>
  <a class="nav-item" href="#">
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
    Preferences
  </a>
  <a class="nav-item" href="#">
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    Security
  </a>

  <div class="sidebar-bottom">
    <div class="user-card">
      <div class="avatar" style="background:<?= htmlspecialchars($avatarColor) ?>"><?= htmlspecialchars($initials) ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($fullName) ?></div>
        <div class="user-email"><?= htmlspecialchars($email) ?></div>
      </div>
    </div>
  </div>
</aside>

<!-- ── Main Content ────────────────────────────────────────── -->
<main class="main">

  <div class="topbar">
    <div>
      <div class="page-title">Dashboard</div>
      <div class="page-sub">Welcome back, <?= htmlspecialchars(explode(' ', $fullName)[0]) ?> 👋</div>
    </div>
    <div class="topbar-right">
      <div class="badge">Role: <strong><?= htmlspecialchars(ucfirst($role)) ?></strong></div>
      <a class="logout-btn" href="logout.php">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Logout
      </a>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(108,99,255,.15)">
        <svg width="20" height="20" fill="none" stroke="#6C63FF" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </div>
      <div class="stat-value"><?= $totalUsers ?></div>
      <div class="stat-label">Total Users</div>
      <div class="stat-delta" style="color:var(--accent3)">↑ Growing</div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(255,101,132,.15)">
        <svg width="20" height="20" fill="none" stroke="#FF6584" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      </div>
      <div class="stat-value"><?= $joinedDays ?></div>
      <div class="stat-label">Days Since Joined</div>
      <div class="stat-delta" style="color:var(--muted)"><?= date('M d, Y', strtotime($userRow['created_at'])) ?></div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(32,191,107,.15)">
        <svg width="20" height="20" fill="none" stroke="#20BF6B" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      </div>
      <div class="stat-value"><?= count($activities) ?></div>
      <div class="stat-label">Activity Events</div>
      <div class="stat-delta" style="color:var(--accent3)">● Active session</div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(247,183,49,.15)">
        <svg width="20" height="20" fill="none" stroke="#F7B731" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div class="stat-value"><?= $userRow['last_login'] ? date('H:i', strtotime($userRow['last_login'])) : 'Now' ?></div>
      <div class="stat-label">Last Login</div>
      <div class="stat-delta" style="color:var(--muted)"><?= $userRow['last_login'] ? date('M d', strtotime($userRow['last_login'])) : 'Today' ?></div>
    </div>
  </div>

  <!-- Content Grid -->
  <div class="content-grid">

    <!-- Activity Log -->
    <div class="panel">
      <div class="panel-title">Recent Activity <span><?= count($activities) ?> events</span></div>
      <?php if (empty($activities)): ?>
        <p style="color:var(--muted);font-size:.875rem;text-align:center;padding:1.5rem 0">No activity yet.</p>
      <?php else: ?>
        <?php foreach ($activities as $a): ?>
        <div class="activity-item">
          <div class="activity-dot"></div>
          <div>
            <div class="activity-text"><?= htmlspecialchars($a['action']) ?></div>
            <div class="activity-meta">
              <?= date('M d, Y · H:i', strtotime($a['created_at'])) ?> — IP: <?= htmlspecialchars($a['ip_address']) ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Profile Card -->
    <div class="panel">
      <div class="panel-title">My Profile</div>
      <div class="profile-avatar" style="background:<?= htmlspecialchars($avatarColor) ?>"><?= htmlspecialchars($initials) ?></div>
      <div class="profile-name"><?= htmlspecialchars($fullName) ?></div>
      <div class="profile-email"><?= htmlspecialchars($email) ?></div>
      <div class="profile-badge">
        <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm3.7-11.7l-5 5-1.4-1.4 5-5 1.4 1.4z"/></svg>
        <?= htmlspecialchars(ucfirst($role)) ?>
      </div>

      <div class="profile-stats">
        <div class="ps">
          <div class="ps-val"><?= $joinedDays ?>d</div>
          <div class="ps-lbl">Member for</div>
        </div>
        <div class="ps">
          <div class="ps-val"><?= count($activities) ?></div>
          <div class="ps-lbl">Activities</div>
        </div>
      </div>

      <a href="#" class="edit-btn">Edit Profile</a>
    </div>

  </div>
</main>

</body>
</html>
