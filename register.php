<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireGuest();

$errors   = [];
$formData = ['full_name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        $formData = ['full_name' => $fullName, 'email' => $email];

        // Validation
        if (strlen($fullName) < 2) $errors[] = 'Full name must be at least 2 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';

        $pwErrors = validatePassword($password);
        if (!empty($pwErrors)) $errors[] = 'Password must contain: ' . implode(', ', $pwErrors) . '.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            // Check duplicate email
            $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = 'An account with this email already exists.';
            } else {
                $hash   = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $colors = ['#6C63FF','#FF6584','#43BCCD','#F7B731','#20BF6B','#FC5C65'];
                $color  = $colors[array_rand($colors)];

                $stmt = $conn->prepare(
                    'INSERT INTO users (full_name, email, password_hash, avatar_color) VALUES (?, ?, ?, ?)'
                );
                $stmt->bind_param('ssss', $fullName, $email, $hash, $color);

                if ($stmt->execute()) {
                    $userId = $conn->insert_id;
                    // Log activity
                    $ip   = $_SERVER['REMOTE_ADDR'];
                    $logS = $conn->prepare('INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, ?, ?)');
                    $act  = 'Account created';
                    $logS->bind_param('iss', $userId, $act, $ip);
                    $logS->execute();

                    setFlash('success', 'Account created! Welcome aboard — please log in.');
                    redirect('login.php');
                } else {
                    $errors[] = 'Registration failed. Please try again.';
                }
            }
            $stmt->close();
        }
    }
}

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account — AuthSystem</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:        #0d0f14;
    --surface:   #161922;
    --border:    #252a35;
    --accent:    #6C63FF;
    --accent2:   #FF6584;
    --text:      #e8eaf0;
    --muted:     #6b7280;
    --success:   #20BF6B;
    --danger:    #FC5C65;
    --radius:    14px;
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

  /* Decorative blobs */
  body::before, body::after {
    content: '';
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    opacity: .18;
    pointer-events: none;
  }
  body::before {
    width: 600px; height: 600px;
    background: var(--accent);
    top: -200px; left: -200px;
  }
  body::after {
    width: 500px; height: 500px;
    background: var(--accent2);
    bottom: -150px; right: -150px;
  }

  .card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 2.8rem 2.4rem;
    width: 100%;
    max-width: 460px;
    position: relative;
    z-index: 1;
    animation: slideUp .5s cubic-bezier(.16,1,.3,1) both;
  }

  @keyframes slideUp {
    from { opacity:0; transform: translateY(30px); }
    to   { opacity:1; transform: translateY(0); }
  }

  .logo {
    font-family: 'Syne', sans-serif;
    font-weight: 800;
    font-size: 1.5rem;
    letter-spacing: -0.02em;
    margin-bottom: 1.8rem;
    display: flex;
    align-items: center;
    gap: .5rem;
  }
  .logo span {
    display: inline-block;
    width: 32px; height: 32px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border-radius: 8px;
  }

  h1 {
    font-family: 'Syne', sans-serif;
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: .5rem;
  }
  .subtitle {
    color: var(--muted);
    font-size: .9rem;
    margin-bottom: 2rem;
  }

  .alert {
    padding: .85rem 1rem;
    border-radius: 10px;
    font-size: .875rem;
    margin-bottom: 1.4rem;
  }
  .alert-danger { background: rgba(252,92,101,.12); border: 1px solid rgba(252,92,101,.3); color: #ff8a93; }
  .alert ul { list-style: none; }
  .alert ul li::before { content: '• '; }

  .form-row { display: grid; gap: 1rem; margin-bottom: 1rem; }

  .field label {
    display: block;
    font-size: .8rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--muted);
    margin-bottom: .4rem;
  }

  .input-wrap { position: relative; }
  .input-wrap svg {
    position: absolute;
    left: 14px; top: 50%;
    transform: translateY(-50%);
    color: var(--muted);
    pointer-events: none;
  }

  input {
    width: 100%;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: .75rem 1rem .75rem 2.6rem;
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: .95rem;
    transition: border-color .2s, box-shadow .2s;
    outline: none;
  }
  input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(108,99,255,.15);
  }
  input::placeholder { color: var(--muted); }

  .password-toggle {
    position: absolute;
    right: 12px; top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--muted);
    cursor: pointer;
    padding: 2px;
    display: flex;
    transition: color .2s;
  }
  .password-toggle:hover { color: var(--text); }

  .strength-bar {
    height: 3px;
    background: var(--border);
    border-radius: 4px;
    margin-top: .5rem;
    overflow: hidden;
  }
  .strength-fill {
    height: 100%;
    border-radius: 4px;
    transition: width .3s, background .3s;
    width: 0;
  }
  .strength-label { font-size: .75rem; color: var(--muted); margin-top: .25rem; }

  .btn {
    width: 100%;
    padding: .85rem;
    border: none;
    border-radius: 10px;
    font-family: 'Syne', sans-serif;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .2s;
    margin-top: .5rem;
  }
  .btn-primary {
    background: linear-gradient(135deg, var(--accent), #8B5CF6);
    color: #fff;
    letter-spacing: .02em;
  }
  .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(108,99,255,.4); }
  .btn-primary:active { transform: translateY(0); }

  .link-row {
    text-align: center;
    margin-top: 1.4rem;
    font-size: .875rem;
    color: var(--muted);
  }
  .link-row a { color: var(--accent); text-decoration: none; font-weight: 500; }
  .link-row a:hover { text-decoration: underline; }

  .divider {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin: 1.4rem 0;
    color: var(--muted);
    font-size: .8rem;
  }
  .divider::before, .divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
  }
</style>
</head>
<body>
<div class="card">
  <div class="logo"><span></span> AuthSystem</div>
  <h1>Create account</h1>
  <p class="subtitle">Join us — it only takes a minute.</p>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <form method="POST" action="register.php" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <div class="form-row" style="grid-template-columns:1fr 1fr">
      <div class="field" style="grid-column:1/-1">
        <label>Full Name</label>
        <div class="input-wrap">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <input type="text" name="full_name" placeholder="Jane Doe"
                 value="<?= htmlspecialchars($formData['full_name']) ?>" autocomplete="name" required>
        </div>
      </div>

      <div class="field" style="grid-column:1/-1">
        <label>Email Address</label>
        <div class="input-wrap">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
          <input type="email" name="email" placeholder="jane@example.com"
                 value="<?= htmlspecialchars($formData['email']) ?>" autocomplete="email" required>
        </div>
      </div>

      <div class="field">
        <label>Password</label>
        <div class="input-wrap">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <input type="password" name="password" id="password" placeholder="Min. 8 characters" autocomplete="new-password" required>
          <button type="button" class="password-toggle" onclick="togglePw('password',this)">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
        <div class="strength-label" id="strengthLabel">Enter a password</div>
      </div>

      <div class="field">
        <label>Confirm Password</label>
        <div class="input-wrap">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <input type="password" name="confirm_password" id="confirm" placeholder="Repeat password" autocomplete="new-password" required>
          <button type="button" class="password-toggle" onclick="togglePw('confirm',this)">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>
    </div>

    <button class="btn btn-primary" type="submit">Create Account →</button>
  </form>

  <div class="link-row">Already have an account? <a href="login.php">Sign in</a></div>
</div>

<script>
function togglePw(id, btn) {
  const inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
}

const pwInput = document.getElementById('password');
const fill    = document.getElementById('strengthFill');
const label   = document.getElementById('strengthLabel');

pwInput.addEventListener('input', () => {
  const v = pwInput.value;
  let score = 0;
  if (v.length >= 8)             score++;
  if (/[A-Z]/.test(v))           score++;
  if (/[0-9]/.test(v))           score++;
  if (/[^A-Za-z0-9]/.test(v))    score++;

  const pct   = ['0%','25%','50%','75%','100%'][score];
  const colors = ['#ccc','#FC5C65','#F7B731','#20BF6B','#6C63FF'];
  const labels = ['Enter a password','Weak','Fair','Good','Strong'];
  fill.style.width      = pct;
  fill.style.background = colors[score];
  label.textContent     = labels[score];
  label.style.color     = colors[score];
});
</script>
</body>
</html>
