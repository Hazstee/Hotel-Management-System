<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';

$exists = (int) $pdo->query('SELECT COUNT(*) FROM admin')->fetchColumn() > 0;
$errors = [];
$done   = false;
$full_name = '';
$username  = '';

if (!$exists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm'] ?? '';

    if (!csrf_check()) {
        $errors[] = 'Session expired. Please try again.';
    }
    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3-50 characters (letters, numbers, . _ -).';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO admin (username, password, full_name) VALUES (?, ?, ?)');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $full_name]);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Setup | Bella Vista</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<main>
<h1>Admin Setup</h1>

<?php if ($done): ?>
    <p>Admin account created. <a href="login.php">Go to login</a>.</p>
<?php elseif ($exists): ?>
    <p>Setup is already complete. <a href="login.php">Go to login</a>.</p>
<?php else: ?>
    <?php if ($errors): ?>
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="setup.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <p>
            <label for="full_name">Full name</label><br>
            <input type="text" id="full_name" name="full_name" maxlength="100" value="<?= e($full_name) ?>" required>
        </p>
        <p>
            <label for="username">Username</label><br>
            <input type="text" id="username" name="username" maxlength="50" value="<?= e($username) ?>" required>
        </p>
        <p>
            <label for="password">Password (8+ characters)</label><br>
            <input type="password" id="password" name="password" minlength="8" required>
        </p>
        <p>
            <label for="confirm">Confirm password</label><br>
            <input type="password" id="confirm" name="confirm" minlength="8" required>
        </p>
        <p><button type="submit">Create admin account</button></p>
    </form>
<?php endif; ?>
</main>
</body>
</html>
