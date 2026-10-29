<?php
require_once 'config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT user_id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];

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
    <title>Login - Social Welfare System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">

    <div class="auth-card">
        <h2>System Login</h2>

        <?php if (!empty($error)): ?>
            <div class="error-msg" style="color: #dc2626; background-color: #fee2e2; padding: 8px; border-radius: 6px; font-size: 0.85rem; text-align: center; margin-bottom: 15px;">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Username</label>
            <input type="text" name="username" required placeholder="Enter your username">

            <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Password</label>
            <input type="password" name="password" required placeholder="Enter your password">

            <button type="submit" style="margin-top: 10px;">Log In</button>
        </form>

        <div style="text-align: center; margin-top: 20px;">
            <a href="register.php" style="color: #2563eb; font-size: 0.85rem; text-decoration: none; font-weight: 600;">Create Account</a>
        </div>
    </div>

</body>
</html>