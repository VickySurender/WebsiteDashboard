<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireGuest();

$errors   = [];
$email    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (empty($password)) {
            $errors[] = 'Password is required.';
        } else {
            $stmt = $conn->prepare(
                'SELECT id, full_name, email, password_hash, avatar_color, role FROM users WHERE email = ?'
            );
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0 || !password_verify($password, ($row = $result->fetch_assoc())['password_hash'])) {
                $errors[] = 'Invalid email or password. Please try again.';
            } else {
                // Set session
                $_SESSION['user_id']    = $row['id'];
                $_SESSION['full_name']  = $row['full_name'];
                $_SESSION['email']      = $row['email'];
                $_SESSION['role']       = $row['role'];
                $_SESSION['avatar_color'] = $row['avatar_color'];

                // Update last login
                $upd = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
                $upd->bind_param('i', $row['id']);
                $upd->execute();

                // Log activity
                $ip  = $_SERVER['REMOTE_ADDR'];
                $act = 'Logged in';
                $log = $conn->prepare('INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, ?, ?)');
                $log->bind_param('iss', $row['id'], $act, $ip);
                $log->execute();

                // Remember me cookie (30 days)
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + 30 * 86400, '/', '', false, true);
                }

                redirect('dashboard.php');
            }
            $stmt->close();
        }
    }
}

$flash = getFlash();
$csrf  = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — AuthSystem</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:      #0d0f14;
    --surface: #161922;
    --border:  #252a35;
    --accent:  #6C63FF;
    --accent2: #FF6584;
    --text:    #e8eaf0;
    --muted:   #6b7280;
    --success: #20BF6B;
    --danger:  #FC5C65;
  }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 2rem 1rem;
    position: relative;
    overflow-x: hidden;
  }
  body::before, body::after {
    content: '';
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    opacity: .18;
    pointer-events: none;
  }
  body::before { width:600px;height:600px;background:var(--accent);top:-200px;left:-200px; }
  body::after  { width:500px;height:500px;background:var(--accent2);bottom:-150px;right:-150px; }

  .card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 2.8rem 2.4rem;
    width: 100%;
    max-width: 420px;
    position: relative;
    z-index: 1;
    animation: slideUp .5s cubic-bezier(.16,1,.3,1) both;
  }
  @keyframes slideUp {
    from { opacity:0; transform:translateY(30px); }
    to   { opacity:1; transform:translateY(0); }
  }

  .logo {
    font-family:'Syne',sans-serif;font-weight:800;font-size:1.5rem;
    letter-spacing:-.02em;margin-bottom:1.8rem;display:flex;align-items:center;gap:.5rem;
  }
  .logo span {
    display:inline-block;width:32px;height:32px;
    background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:8px;
  }

  h1 { font-family:'Syne',sans-serif;font-size:1.75rem;font-weight:700;line-height:1.2;margin-bottom:.5rem; }
  .subtitle { color:var(--muted);font-size:.9rem;margin-bottom:2rem; }

  .alert { padding:.85rem 1rem;border-radius:10px;font-size:.875rem;margin-bottom:1.4rem; }
  .alert-danger  { background:rgba(252,92,101,.12);border:1px solid rgba(252,92,101,.3);color:#ff8a93; }
  .alert-success { background:rgba(32,191,107,.12);border:1px solid rgba(32,191,107,.3);color:#4ade80; }

  .field { margin-bottom:1rem; }
  .field label { display:block;font-size:.8rem;font-weight:500;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:.4rem; }

  .input-wrap { position:relative; }
  .input-wrap svg { position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);pointer-events:none; }

  input[type="email"], input[type="password"], input[type="text"] {
    width:100%;background:var(--bg);border:1px solid var(--border);border-radius:10px;
    padding:.75rem 1rem .75rem 2.6rem;color:var(--text);font-family:'DM Sans',sans-serif;
    font-size:.95rem;transition:border-color .2s,box-shadow .2s;outline:none;
  }
  input:focus { border-color:var(--accent);box-shadow:0 0 0 3px rgba(108,99,255,.15); }
  input::placeholder { color:var(--muted); }

  .password-toggle {
    position:absolute;right:12px;top:50%;transform:translateY(-50%);
    background:none;border:none;color:var(--muted);cursor:pointer;padding:2px;display:flex;transition:color .2s;
  }
  .password-toggle:hover { color:var(--text); }

  .row-between { display:flex;align-items:center;justify-content:space-between;margin-bottom:1.4rem; }
  .remember { display:flex;align-items:center;gap:.45rem;font-size:.875rem;color:var(--muted);cursor:pointer; }
  .remember input[type="checkbox"] {
    width:16px;height:16px;accent-color:var(--accent);cursor:pointer;padding:0;
  }
  .forgot { font-size:.875rem;color:var(--accent);text-decoration:none; }
  .forgot:hover { text-decoration:underline; }

  .btn { width:100%;padding:.85rem;border:none;border-radius:10px;font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;cursor:pointer;transition:all .2s; }
  .btn-primary { background:linear-gradient(135deg,var(--accent),#8B5CF6);color:#fff;letter-spacing:.02em; }
  .btn-primary:hover { transform:translateY(-2px);box-shadow:0 8px 24px rgba(108,99,255,.4); }
  .btn-primary:active { transform:translateY(0); }

  .link-row { text-align:center;margin-top:1.4rem;font-size:.875rem;color:var(--muted); }
  .link-row a { color:var(--accent);text-decoration:none;font-weight:500; }
  .link-row a:hover { text-decoration:underline; }
</style>
</head>
<body>
<div class="card">
  <div class="logo"><span></span> AuthSystem</div>
  <h1>Welcome back</h1>
  <p class="subtitle">Sign in to continue to your dashboard.</p>

  <?php if ($flash): ?>
  <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($errors[0]) ?></div>
  <?php endif; ?>

  <form method="POST" action="login.php" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <div class="field">
      <label>Email Address</label>
      <div class="input-wrap">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
        <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="jane@example.com" autocomplete="email" required>
      </div>
    </div>

    <div class="field">
      <label>Password</label>
      <div class="input-wrap">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <input type="password" name="password" id="password" placeholder="Your password" autocomplete="current-password" required>
        <button type="button" class="password-toggle" onclick="togglePw()">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>

    <div class="row-between">
      <label class="remember">
        <input type="checkbox" name="remember"> Remember me
      </label>
      <a href="#" class="forgot">Forgot password?</a>
    </div>

    <button class="btn btn-primary" type="submit">Sign In →</button>
  </form>

  <div class="link-row">Don't have an account? <a href="register.php">Create one</a></div>
</div>

<script>
function togglePw() {
  const inp = document.getElementById('password');
  inp.type = inp.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
