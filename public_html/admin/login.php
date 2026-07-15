<?php
// login.php — admin sign-in page.
// NOTE: this page must NOT include sys/auth.php (auth.php redirects logged-out
// visitors back to login.php, which would loop forever). We load the pieces
// we need directly instead.
require_once __DIR__ . '/../sys/db_connect.php';
require_once __DIR__ . '/../sys/helpers.php';

sl_session_start();

// Already logged in? Straight to the dashboard.
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$err  = '';
$info = '';
if (isset($_GET['timeout'])) {
    $info = 'You were logged out after 30 minutes of inactivity.';
}

// Log a user in and send them to the dashboard (used by both normal login
// and the first-run setup below).
function sl_login_success($conn, $id, $username, $role) {
    session_regenerate_id(true); // new session ID prevents session fixation
    $_SESSION['admin_id']       = $id;
    $_SESSION['admin_username'] = $username;
    $_SESSION['admin_role']     = $role;
    $_SESSION['last_activity']  = time();
    $stmt = $conn->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    header('Location: index.php');
    exit;
}

// FIRST-RUN SETUP: if there are no admin accounts yet, show a "create the
// first admin" form instead of login. (The public git repo contains no
// seeded credentials, so the very first visit creates the account.)
$res = $conn->query("SELECT COUNT(*) AS c FROM admin_users");
$first_run = ((int)$res->fetch_assoc()['c'] === 0);

// Sticky form values (repopulated after a validation error)
$old_username = '';
$old_email    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $err = 'Session expired — please try again.';

    } elseif (($_POST['action'] ?? '') === 'setup') {
        // ---- Create the first admin account ----
        // Re-check the count on POST so two browser tabs can't both create one.
        $res = $conn->query("SELECT COUNT(*) AS c FROM admin_users");
        if ((int)$res->fetch_assoc()['c'] !== 0) {
            $err = 'An admin account already exists — please log in.';
            $first_run = false;
        } else {
            $username = trim($_POST['username'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm  = $_POST['confirm_password'] ?? '';
            $old_username = $username;
            $old_email    = $email;

            if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) {
                $err = 'Username must be 3-30 characters (letters, numbers, . _ - only).';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $err = 'Please enter a valid email address.';
            } elseif (strlen($password) < 10) {
                $err = 'Password must be at least 10 characters.';
            } elseif ($password !== $confirm) {
                $err = 'Passwords do not match.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    "INSERT INTO admin_users (username, email, password_hash, role, is_active) VALUES (?, ?, ?, 'superadmin', 1)"
                );
                $stmt->bind_param('sss', $username, $email, $hash);
                $stmt->execute();
                $new_id = (int)$conn->insert_id;
                $stmt->close();
                sl_login_success($conn, $new_id, $username, 'superadmin'); // redirects
            }
        }

    } else {
        // ---- Normal login ----
        $iphash = ip_hash($conn);

        // RATE LIMIT: 5 failed attempts per IP per 15 minutes.
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c FROM rate_limit WHERE kind = 'login' AND ip_hash = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)"
        );
        $stmt->bind_param('s', $iphash);
        $stmt->execute();
        $attempts = (int)$stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();

        if ($attempts >= 5) {
            $err = 'Too many attempts — wait 15 minutes.';
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $old_username = $username;

            $user = null;
            if ($username !== '' && $password !== '') {
                $stmt = $conn->prepare(
                    "SELECT id, username, password_hash, role FROM admin_users WHERE username = ? AND is_active = 1"
                );
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }

            if ($user && password_verify($password, $user['password_hash'])) {
                sl_login_success($conn, (int)$user['id'], $user['username'], $user['role']); // redirects
            } else {
                // Record the failed attempt for the rate limiter, then show a
                // generic error (never reveal whether username or password was wrong).
                $stmt = $conn->prepare("INSERT INTO rate_limit (kind, ip_hash, created_at) VALUES ('login', ?, NOW())");
                $stmt->bind_param('s', $iphash);
                $stmt->execute();
                $stmt->close();
                $err = 'Invalid username or password';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $first_run ? 'First-Run Setup' : 'Login'; ?> — Sha Lanka Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: {
                brand: '#1577BE', brandglow: '#2BA8E0', espresso: '#1C1A17', cream: '#F5F0E6'
            } } }
        };
    </script>
</head>
<body class="bg-espresso min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <div class="bg-white rounded-lg shadow p-6">
            <h1 class="text-xl font-bold text-espresso text-center mb-1">Sha Lanka Admin</h1>

            <?php if ($first_run): ?>
                <p class="text-sm text-gray-500 text-center mb-4">Create the first admin account</p>
            <?php else: ?>
                <p class="text-sm text-gray-500 text-center mb-4">Sign in to continue</p>
            <?php endif; ?>

            <?php if ($info !== ''): ?>
                <div class="bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded p-3 mb-4"><?php echo h($info); ?></div>
            <?php endif; ?>
            <?php if ($err !== ''): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded p-3 mb-4"><?php echo h($err); ?></div>
            <?php endif; ?>

            <?php if ($first_run): ?>
                <!-- First-run setup form -->
                <form method="post" action="login.php" autocomplete="off">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="setup">
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="username">Username</label>
                    <input class="w-full border border-gray-300 rounded px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-brand"
                           type="text" id="username" name="username" value="<?php echo h($old_username); ?>" required>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="email">Email</label>
                    <input class="w-full border border-gray-300 rounded px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-brand"
                           type="email" id="email" name="email" value="<?php echo h($old_email); ?>" required>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="password">Password <span class="text-gray-400 font-normal">(min 10 characters)</span></label>
                    <input class="w-full border border-gray-300 rounded px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-brand"
                           type="password" id="password" name="password" minlength="10" required>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="confirm_password">Confirm password</label>
                    <input class="w-full border border-gray-300 rounded px-3 py-2 text-sm mb-4 focus:outline-none focus:ring-2 focus:ring-brand"
                           type="password" id="confirm_password" name="confirm_password" minlength="10" required>
                    <button class="w-full bg-brand text-white text-sm px-3 py-2 rounded hover:bg-brandglow" type="submit">Create admin account</button>
                </form>
            <?php else: ?>
                <!-- Login form -->
                <form method="post" action="login.php">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="login">
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="username">Username</label>
                    <input class="w-full border border-gray-300 rounded px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-brand"
                           type="text" id="username" name="username" value="<?php echo h($old_username); ?>" autocomplete="username" required autofocus>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="password">Password</label>
                    <input class="w-full border border-gray-300 rounded px-3 py-2 text-sm mb-4 focus:outline-none focus:ring-2 focus:ring-brand"
                           type="password" id="password" name="password" autocomplete="current-password" required>
                    <button class="w-full bg-brand text-white text-sm px-3 py-2 rounded hover:bg-brandglow" type="submit">Log in</button>
                </form>
            <?php endif; ?>
        </div>
        <p class="text-cream/60 text-xs text-center mt-4">Sha Lanka Travels — admin area</p>
    </div>
</body>
</html>
