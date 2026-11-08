<?php
/**
 * login.php
 * Authenticates a user against the `users` table.
 */
require_once 'config.php';

 $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT user_id, username, password_hash, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['user_id']  = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role']     = $row['role'];

            if ($row['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: beneficiary_dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "User not found!";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Social Welfare Assistance System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">

    <div class="auth-card">
        <h2>MSWDO Portal</h2>
        <p class="auth-subtitle">Social Welfare Assistance &amp; Distribution</p>

        <?php if (!empty($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" required placeholder="Enter your username">

            <label>Password</label>
            <input type="password" name="password" required placeholder="Enter your password">

            <button type="submit">Log In</button>
        </form>

        <div class="auth-link">
            <a href="register.php">Create Beneficiary Account</a>
        </div>

        <div class="demo-creds">
            <strong>Demo logins:</strong><br>
            social_worker_1 / admin123 (Admin / Social Worker)<br>
            nina_paz / nina123 (Beneficiary)<br>
            rafi_cruz / rafi123 (Beneficiary)
        </div>
    </div>

</body>
</html>