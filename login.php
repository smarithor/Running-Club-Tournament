<?php
// login.php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/auth.php';

$redirectTo = isset($_GET['redirect']) ? (string)$_GET['redirect'] : '/';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    if (hash_equals(SITE_PASSWORD, $password)) {
        $_SESSION['rc_tournament_authed'] = true;
        header('Location: ' . $redirectTo);
        exit;
    } else {
        $error = 'Incorrect password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>RC Tournament — Sign in</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <form class="card" method="post" action="">
        <h1>Sign in</h1>
        <?php if ($error): ?><div class="err"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectTo, ENT_QUOTES) ?>">
        <label for="password">Site Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">Enter</button>
        <div class="hint">Access is restricted to authorized users.</div>
    </form>
</body>
</html>
