<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';

if (admin_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!csrf_check()) {
        $error = 'Session expired. Please try again.';
    } else {
        $stmt = $pdo->prepare('SELECT admin_id, password, full_name FROM admin WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = (int) $admin['admin_id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            header('Location: dashboard.php');
            exit;
        }
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login | Bella Vista</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<main>
<h1>Admin Login</h1>

<?php if ($error): ?>
    <p><?= e($error) ?></p>
<?php endif; ?>

<form method="post" action="login.php">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <p>
        <label for="username">Username</label><br>
        <input type="text" id="username" name="username" value="<?= e($username) ?>" required>
    </p>
    <p>
        <label for="password">Password</label><br>
        <input type="password" id="password" name="password" required>
    </p>
    <p><button type="submit">Log in</button></p>
</form>

<p><a href="../index.php">&larr; Back to website</a></p>
</main>
</body>
</html>
