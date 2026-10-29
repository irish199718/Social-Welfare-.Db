<?php
require_once 'config.php';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT user_id, username, password_hash, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: beneficiary_dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "User not found.";
        }
        $stmt->close();
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card">
    <h2 style="text-align:center; color:#1e3a8a;">System Login</h2>
    <?php if($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
    <form method="POST">
        <label>Username</label><input type="text" name="username" required>
        <label>Password</label><input type="password" name="password" required>
        <button type="submit">Log In</button>
    </form>
    <p style="text-align:center; font-size:0.85em; margin-top:15px;"><a href="register.php">Create Account</a></p>
</div>
</body>
</html>