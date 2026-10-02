<?php
$headTitle = "Settings";
include('head.php');

require_once __DIR__ . '/premium.php';

if (!isset($_SESSION['id'], $_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /assets/login.php');
    exit();
}

$userId = (int) $_SESSION['id'];
$settingsUrl = '/assets/user_settings.php';

const USERNAME_CHANGE_DAYS = 15;
const CODE_TTL_SECONDS = 15 * 60;
const CODE_MAX_ATTEMPTS = 5;
const PASSWORD_MIN_LENGTH = 8;

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function loadUser(PDO $db, int $userId): array
{
    $stmt = $db->prepare('SELECT username, email, password_hash, last_username_change FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Show a message after the redirect and go back to the settings page (so a refresh never re-submits).
 */
function finish(string $type, string $message, string $anchor): never
{
    $_SESSION['settings_flash'] = ['type' => $type, 'message' => $message, 'section' => $anchor];
    header('Location: /assets/user_settings.php#' . $anchor);
    exit();
}

/**
 * Usernames are the public URL (qrsume.com/username): the characters .htaccess routes,
 * and never the name of a file or folder of the site.
 */
function usernameProblem(string $username): ?string
{
    if (!preg_match('/^[a-z0-9][a-z0-9_.]{3,24}$/', $username)) {
        return 'Use 4 to 25 characters: lowercase letters, numbers, dots (.) and underscores (_), starting with a letter or number.';
    }
    $root = dirname(__DIR__);
    if (file_exists("$root/$username") || file_exists("$root/$username.php")) {
        return 'That name is reserved by the website. Please choose another.';
    }

    return null;
}

function nextUsernameChange(?string $lastChange): ?int
{
    if (!$lastChange) {
        return null;
    }
    $next = strtotime($lastChange) + USERNAME_CHANGE_DAYS * 86400;

    return $next > time() ? $next : null;
}

function sendCode(string $to, string $subject): ?array
{
    $code = (string) random_int(100000, 999999);
    $message = "Your QRsume verification code is: $code\n\nIt expires in 15 minutes. If you did not ask for it, you can ignore this email.";
    if (!mail($to, $subject, $message, "From: no-reply@qrsume.com\r\n")) {
        return null;
    }

    return ['code' => $code, 'expires' => time() + CODE_TTL_SECONDS, 'attempts' => 0];
}

$user = loadUser($db, $userId);

// -----------------------------------------------------------------------------
// Actions
// -----------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $anchor = ['username' => 'account', 'email_request' => 'email', 'email_verify' => 'email', 'email_cancel' => 'email', 'password' => 'password'][$action] ?? 'account';

    if (!hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
        finish('danger', 'Your session expired. Please try again.', $anchor);
    }

    switch ($action) {
        case 'username':
            $newUsername = strtolower(trim((string) ($_POST['username'] ?? '')));
            if ($newUsername === $user['username']) {
                finish('info', 'That is already your username.', 'account');
            }
            if ($problem = usernameProblem($newUsername)) {
                finish('danger', $problem, 'account');
            }
            if ($next = nextUsernameChange($user['last_username_change'] ?? null)) {
                finish('danger', 'You can change your username again on ' . date('j F Y', $next) . '.', 'account');
            }
            $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND id != ?');
            $stmt->execute([$newUsername, $userId]);
            if ($stmt->fetchColumn()) {
                finish('danger', 'That username is already taken. Please choose another.', 'account');
            }

            $db->prepare('UPDATE users SET username = ?, last_username_change = ? WHERE id = ?')
                ->execute([$newUsername, date('Y-m-d H:i:s'), $userId]);
            // The "download CV" link of the public page carries the username
            $db->prepare("UPDATE personalinfo SET cv_url = ? WHERE user_id = ? AND cv_url LIKE '%pdf2.php?username=%'")
                ->execute(['https://qrsume.com/pdf2.php?username=' . $newUsername, $userId]);
            $_SESSION['username'] = $newUsername;
            finish('success', 'Your username is now ' . $newUsername . '. Your page is at qrsume.com/' . $newUsername . '.', 'account');

        case 'email_request':
            $newEmail = trim((string) ($_POST['email'] ?? ''));
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                finish('danger', 'Please enter a valid email address.', 'email');
            }
            if (strcasecmp($newEmail, $user['email']) === 0) {
                finish('info', 'That is already your login email.', 'email');
            }
            $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id != ?');
            $stmt->execute([$newEmail, $userId]);
            if ($stmt->fetchColumn()) {
                finish('danger', 'That email is already used by another account.', 'email');
            }
            $pending = sendCode($newEmail, 'Confirm your new QRsume email');
            if ($pending === null) {
                finish('danger', 'We could not send the email. Please try again later.', 'email');
            }
            $_SESSION['email_change'] = $pending + ['email' => $newEmail];
            finish('success', "We sent a 6-digit code to $newEmail. Enter it below to confirm.", 'email');

        case 'email_verify':
            $pending = $_SESSION['email_change'] ?? null;
            if (!$pending || $pending['expires'] < time()) {
                unset($_SESSION['email_change']);
                finish('danger', 'The code has expired. Please ask for a new one.', 'email');
            }
            if (!hash_equals($pending['code'], trim((string) ($_POST['code'] ?? '')))) {
                $_SESSION['email_change']['attempts']++;
                if ($_SESSION['email_change']['attempts'] >= CODE_MAX_ATTEMPTS) {
                    unset($_SESSION['email_change']);
                    finish('danger', 'Too many wrong codes. Please ask for a new one.', 'email');
                }
                finish('danger', 'That code is not right. Please check the email and try again.', 'email');
            }
            $db->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$pending['email'], $userId]);
            unset($_SESSION['email_change']);
            finish('success', 'Your login email is now ' . $pending['email'] . '.', 'email');

        case 'email_cancel':
            unset($_SESSION['email_change']);
            finish('info', 'Email change cancelled.', 'email');

        case 'password':
            $current = (string) ($_POST['current_password'] ?? '');
            $new = (string) ($_POST['new_password'] ?? '');
            if (!password_verify($current, (string) $user['password_hash'])) {
                finish('danger', 'Your current password is not right.', 'password');
            }
            if (strlen($new) < PASSWORD_MIN_LENGTH) {
                finish('danger', 'Your new password needs at least ' . PASSWORD_MIN_LENGTH . ' characters.', 'password');
            }
            if ($new !== (string) ($_POST['confirm_password'] ?? '')) {
                finish('danger', 'The two new passwords do not match.', 'password');
            }
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            finish('success', 'Your password has been changed.', 'password');
    }

    finish('danger', 'Something went wrong. Please try again.', 'account');
}

// -----------------------------------------------------------------------------
// Page data
// -----------------------------------------------------------------------------

$flash = $_SESSION['settings_flash'] ?? null;
unset($_SESSION['settings_flash']);

$emailChange = $_SESSION['email_change'] ?? null;
if ($emailChange && $emailChange['expires'] < time()) {
    unset($_SESSION['email_change']);
    $emailChange = null;
}

$nextChange = nextUsernameChange($user['last_username_change'] ?? null);
$isPremium = userHasPurchase($db, $userId, 'remove_qrsume_branding');

function flashFor(?array $flash, string $section): string
{
    if (!$flash || $flash['section'] !== $section) {
        return '';
    }

    return '<div class="alert alert-' . e($flash['type']) . ' py-2 small" role="' . ($flash['type'] === 'danger' ? 'alert' : 'status') . '">' . e($flash['message']) . '</div>';
}
?>
<style>
  body {
    background: #f5f6f8;
  }

  .settings {
    width: 100%;
    max-width: 760px;
    margin: 0 auto;
    padding: 1.5rem 16px 4rem;
  }

  .settings h1 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 750;
  }

  .settings-card {
    margin-bottom: 1rem;
    padding: 1.25rem;
    border: 1px solid #e7e8ec;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 6px 20px rgba(15, 23, 42, .04);
    scroll-margin-top: 80px;
  }

  .settings-card h2 {
    margin: 0 0 .25rem;
    font-size: 1.05rem;
    font-weight: 700;
  }

  .settings-card .lead-text {
    margin-bottom: 1rem;
    color: #6b7280;
    font-size: .9rem;
  }

  .current-value {
    display: inline-block;
    margin-bottom: .75rem;
    padding: .2rem .6rem;
    border-radius: 8px;
    background: #f3f4f6;
    font-weight: 600;
    word-break: break-all;
  }
</style>

<body>
<?php include('nav_dashboard.php'); ?>

<main class="settings">
  <header class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h1>Settings</h1>
      <p class="text-muted mb-0">Your account, login email and password.</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="https://qrsume.com/assets/dashboard.php"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to dashboard</a>
  </header>

  <!-- Username -->
  <section class="settings-card" id="account" aria-labelledby="accountTitle">
    <h2 id="accountTitle">Username and page address</h2>
    <p class="lead-text">Your username is the address of your public page.</p>
    <?= flashFor($flash, 'account') ?>
    <div class="current-value">qrsume.com/<?= e($user['username']) ?></div>

    <form method="POST" action="https://qrsume.com/assets/user_settings.php">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="action" value="username">
      <label for="username" class="form-label fw-semibold">New username</label>
      <div class="input-group">
        <span class="input-group-text">qrsume.com/</span>
        <input type="text" id="username" name="username" class="form-control" value="<?= e($user['username']) ?>"
               required minlength="4" maxlength="25" pattern="[a-z0-9][a-z0-9_.]{3,24}" autocapitalize="none" spellcheck="false"
               aria-describedby="usernameHelp" <?= $nextChange ? 'disabled' : '' ?>>
        <button type="submit" class="btn btn-primary" <?= $nextChange ? 'disabled' : '' ?>>Save</button>
      </div>
      <div id="usernameHelp" class="form-text">
        4 to 25 characters: lowercase letters, numbers, dots and underscores, starting with a letter or number. You can change it once every <?= USERNAME_CHANGE_DAYS ?> days.
      </div>
      <?php if ($nextChange): ?>
        <div class="form-text text-dark"><i class="bi bi-clock me-1" aria-hidden="true"></i>You can change it again on <?= date('j F Y', $nextChange) ?>.</div>
      <?php else: ?>
        <div class="alert alert-warning small mt-2 mb-0 py-2">
          <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
          Your page will move to the new address. Links and QR codes on resumes you already sent will stop working.
        </div>
      <?php endif; ?>
    </form>
  </section>

  <!-- Login email -->
  <section class="settings-card" id="email" aria-labelledby="emailTitle">
    <h2 id="emailTitle">Login email</h2>
    <p class="lead-text">The email you log in with. The email shown on your resume is edited in your resume.</p>
    <?= flashFor($flash, 'email') ?>
    <div class="current-value"><?= e($user['email']) ?></div>

    <?php if ($emailChange): ?>
      <form method="POST" action="https://qrsume.com/assets/user_settings.php" class="mb-2">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="email_verify">
        <label for="emailCode" class="form-label fw-semibold">Code sent to <?= e($emailChange['email']) ?></label>
        <div class="input-group" style="max-width: 360px;">
          <input type="text" id="emailCode" name="code" class="form-control" inputmode="numeric" autocomplete="one-time-code"
                 pattern="[0-9]{6}" maxlength="6" placeholder="6-digit code" required>
          <button type="submit" class="btn btn-success">Confirm</button>
        </div>
        <div class="form-text">The code expires 15 minutes after it was sent.</div>
      </form>
      <form method="POST" action="https://qrsume.com/assets/user_settings.php">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="email_cancel">
        <button type="submit" class="btn btn-link btn-sm p-0">Cancel or use a different email</button>
      </form>
    <?php else: ?>
      <form method="POST" action="https://qrsume.com/assets/user_settings.php">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="email_request">
        <label for="newEmail" class="form-label fw-semibold">New email</label>
        <div class="input-group">
          <input type="email" id="newEmail" name="email" class="form-control" autocomplete="email" placeholder="name@example.com" required>
          <button type="submit" class="btn btn-primary">Send code</button>
        </div>
        <div class="form-text">We will send a code to the new address to make sure it is yours.</div>
      </form>
    <?php endif; ?>
  </section>

  <!-- Password -->
  <section class="settings-card" id="password" aria-labelledby="passwordTitle">
    <h2 id="passwordTitle">Password</h2>
    <p class="lead-text">Use at least <?= PASSWORD_MIN_LENGTH ?> characters.</p>
    <?= flashFor($flash, 'password') ?>
    <form method="POST" action="https://qrsume.com/assets/user_settings.php" class="row g-2">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="action" value="password">
      <input type="text" name="username" value="<?= e($user['username']) ?>" autocomplete="username" hidden>
      <div class="col-12">
        <label for="currentPassword" class="form-label fw-semibold">Current password</label>
        <input type="password" id="currentPassword" name="current_password" class="form-control" autocomplete="current-password" required>
        <div class="form-text"><a href="https://qrsume.com/assets/reset_password.php">Forgot it? Reset it by email</a></div>
      </div>
      <div class="col-sm-6">
        <label for="newPassword" class="form-label fw-semibold">New password</label>
        <input type="password" id="newPassword" name="new_password" class="form-control" autocomplete="new-password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required>
      </div>
      <div class="col-sm-6">
        <label for="confirmPassword" class="form-label fw-semibold">Repeat new password</label>
        <input type="password" id="confirmPassword" name="confirm_password" class="form-control" autocomplete="new-password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required>
      </div>
      <div class="col-12 d-flex align-items-center gap-2 mt-3">
        <button type="submit" class="btn btn-primary">Change password</button>
        <small class="text-muted"><input type="checkbox" class="form-check-input me-1" id="showPasswords"><label for="showPasswords">Show passwords</label></small>
      </div>
    </form>
  </section>

  <!-- Plan -->
  <section class="settings-card" aria-labelledby="planTitle">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h2 id="planTitle">Plan: <?= $isPremium ? 'Premium' : 'Free' ?></h2>
        <p class="lead-text mb-0">
          <?= $isPremium
              ? 'Your PDFs have no QRsume branding, and you have 5 extra CV imports from PDF.'
              : 'Premium removes the QRsume branding from your PDFs and adds 5 CV imports from PDF.' ?>
        </p>
      </div>
      <?php if (!$isPremium): ?>
        <a class="btn btn-warning btn-sm" href="https://qrsume.com/checkout_remove_branding.php"><i class="bi bi-unlock2-fill me-1" aria-hidden="true"></i>Go premium · €1.99</a>
      <?php endif; ?>
    </div>
  </section>

  <a class="btn btn-outline-danger" href="https://qrsume.com/assets/logout.php"><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Log out</a>
</main>

<?php include("footer.php"); ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Usernames are lowercase: show it while typing
  const username = document.getElementById('username');
  username?.addEventListener('input', () => {
    const position = username.selectionStart;
    username.value = username.value.toLowerCase().replace(/\s+/g, '');
    username.setSelectionRange(position, position);
  });

  document.getElementById('showPasswords')?.addEventListener('change', event => {
    document.querySelectorAll('#password input[type="password"], #password input[data-was-password]').forEach(field => {
      field.dataset.wasPassword = '1';
      field.type = event.target.checked ? 'text' : 'password';
    });
  });

  // Typing a mismatched repeat shows the problem before submitting
  const newPassword = document.getElementById('newPassword');
  const confirmPassword = document.getElementById('confirmPassword');
  const checkMatch = () => confirmPassword.setCustomValidity(
    confirmPassword.value && confirmPassword.value !== newPassword.value ? 'The passwords do not match.' : ''
  );
  newPassword?.addEventListener('input', checkMatch);
  confirmPassword?.addEventListener('input', checkMatch);
});
</script>
</body>
</html>
