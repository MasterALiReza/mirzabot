<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';

if (!empty($_SESSION['admin_user'])) {
  header('Location: index.php');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

  if ($username === '' || $password === '') {
    $error = $textbotlang['panel']['loginEnterCredentials'];
  } elseif (!check_login_rate($ip)) {

    $error = $textbotlang['panel']['loginTooManyAttempts'];
    error_log("Login rate limit hit for IP: $ip username: $username");
  } else {

    $admin = select("admin", "*", "username", $username, "select");

    $dummyHash = '$2y$10$dummy.hash.for.timing.attack.prevention.xxxxxxxxxxxxxxxx';
    $storedHash = $admin ? $admin['password'] : $dummyHash;

    $isCorrect = false;
    if (password_verify($password, $storedHash)) {
      $isCorrect = true;
    } elseif ($admin && !password_needs_rehash($storedHash, PASSWORD_BCRYPT)) {

      if ($password === $storedHash) {
        $isCorrect = true;
      }
    } elseif ($admin) {

      if ($password === $admin['password']) {
        $isCorrect = true;
      }
    }

    if ($isCorrect && $admin) {

      if (!str_starts_with($admin['password'], '$2')) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        update("admin", "password", $hash, "username", $username);
      }
      clear_login_rate($ip);
      session_regenerate_id(true);
      $_SESSION['admin_user'] = $admin['username'];
      $_SESSION['login_time'] = time();
      flash('success', $textbotlang['panel']['loginWelcomeBack'] . $admin['username']);
      header('Location: index.php');
      exit;
    } else {
      $error = $textbotlang['panel']['loginWrongCredentials'];
      error_log("Failed login for username: $username from IP: $ip");
    }
  }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
  <meta name="theme-color" content="#0F172A" id="mtc">
  <title><?= $textbotlang['panel']['loginPanelTitle'] ?></title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/mobile_optimizations.css">
  <script>(function () { var t = localStorage.getItem('panel-theme') || 'navy'; document.documentElement.setAttribute('data-theme', t); var c = { navy: '#222831', light: '#F1F5F9' }; var m = document.getElementById('mtc'); if (m && c[t]) m.content = c[t]; })();</script>
  <style>
    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        width: 100%;
    }
    .input-icon {
        position: absolute;
        right: 12px;
        color: var(--dim);
        display: flex;
        align-items: center;
        pointer-events: none;
        transition: color var(--tf);
        z-index: 2;
    }
    .input-with-icon {
        padding-right: 38px !important;
        width: 100%;
    }
    .input-wrapper:focus-within .input-icon {
        color: var(--ac);
    }
    /* Modern Auth Box Styling */
    .auth-box {
        background: rgba(var(--glass-base-rgb, 30, 41, 59), 0.45) !important;
        backdrop-filter: blur(18px) saturate(120%) !important;
        -webkit-backdrop-filter: blur(18px) saturate(120%) !important;
        border: 1.5px solid rgba(255, 255, 255, 0.05) !important;
        border-radius: 20px !important;
        padding: 32px !important;
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25) !important;
    }
    [data-theme=light] .auth-box {
        background: rgba(255, 255, 255, 0.75) !important;
        border: 1.5px solid rgba(0, 0, 0, 0.08) !important;
        box-shadow: 0 12px 40px rgba(15, 23, 42, 0.05) !important;
    }
    .auth-bottom {
        font-weight: 500;
        color: var(--mute);
    }
    .auth-form .btn-primary {
        height: 46px;
        font-size: 0.92rem;
        letter-spacing: 0.01em;
    }
    @media (max-width: 768px) {
        .auth-box {
            padding: 24px 20px !important;
            border-radius: 16px !important;
        }
    }
  </style>
</head>

<body>
  <div class="auth">
    <aside class="auth-aside">
      <div class="auth-mark">
        <div class="dot">M</div>
        <span><?= $textbotlang['panel']['loginHeading'] ?></span>
      </div>
      <div class="auth-quote">
        <h2><?= $textbotlang['panel']['loginSubtitle'] ?> <a style="color:#a8dafd !important  "
            href="https://github.com/MasterALiReza/OxBot">OxBot</a>
          <?= $textbotlang['panel']['loginUsernamePlaceholder'] ?></h2>
        <cite><?= $textbotlang['panel']['loginPasswordLabel'] ?></cite>
      </div>
      <div class="auth-foot">© <?= date('Y') ?> <?= $textbotlang['panel']['loginPasswordPlaceholder'] ?></div>
    </aside>
    <main class="auth-main">
      <div class="auth-box" style="animation:fadeUp .5s ease-out">
        <h1><?= $textbotlang['panel']['loginButton'] ?></h1>
        <p class="lede"><?= $textbotlang['panel']['loginRememberMe'] ?></p>
        <?php if ($error): ?>
          <div class="notice notice-no" style="margin-bottom:20px"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form class="auth-form" method="POST" autocomplete="on">
          <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
          <div class="field">
            <label for="username"><?= $textbotlang['panel']['loginFooter'] ?></label>
            <div class="input-wrapper">
              <span class="input-icon"><?= icon('user', 16) ?></span>
              <input type="text" id="username" name="username" class="input input-with-icon" placeholder="admin"
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username" required autofocus
                maxlength="100">
            </div>
          </div>
          <div class="field">
            <label for="password"><?= $textbotlang['panel']['loginErrorTitle'] ?></label>
            <div class="input-wrapper">
              <span class="input-icon"><?= icon('lock', 16) ?></span>
              <input type="password" id="password" name="password" class="input input-with-icon" placeholder="••••••••"
                autocomplete="current-password" required maxlength="200">
            </div>
          </div>
          <button type="submit" class="btn btn-primary" id="loginBtn">
            <span id="loginText"><?= $textbotlang['panel']['loginShowPassword'] ?></span>
            <span id="loginSpin"
              style="display:none;width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite"></span>
          </button>
        </form>
        <div class="auth-bottom"><?= $textbotlang['panel']['loginHidePassword'] ?></div>
      </div>
    </main>
  </div>
  <script src="js/login.js"></script>
</body>

</html>
