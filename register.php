<?php
require_once 'config.php';

$error = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // Check if username already exists
    $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $error = "Username already taken!";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $password, $role);
        if ($stmt->execute()) {
            $msg = "Registration successful! You can now login.";
        } else {
            $error = "Something went wrong. Please try again.";
        }
        $stmt->close();
        
    }
    $check->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Social Welfare System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">

    <div class="auth-card">
        <h2>Create Account</h2>

        <?php if (!empty($error)): ?>
            <div class="error-msg" style="color: #dc2626; background-color: #fee2e2; padding: 8px; border-radius: 6px; font-size: 0.85rem; text-align: center; margin-bottom: 15px;">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($msg)): ?>
            <div class="success-msg" style="color: #16a34a; background-color: #dcfce7; padding: 8px; border-radius: 6px; font-size: 0.85rem; text-align: center; margin-bottom: 15px;">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Username</label>
            <input type="text" name="username" required placeholder="Choose a username">

            <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Password</label>
            <input type="password" name="password" required placeholder="Choose a password">

            <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Role</label>
            <select name="role" required>
                <option value="beneficiary">Beneficiary</option>
                <option value="admin">Admin / Social Worker</option>
            </select>

            <button type="submit" style="margin-top: 10px;">Register</button>
        </form>

        <div style="text-align: center; margin-top: 20px;">
            <a href="login.php" style="color: #2563eb; font-size: 0.85rem; text-decoration: none; font-weight: 600;">Already have an account? Log In</a>
        </div>
    </div>

</body>
</html>