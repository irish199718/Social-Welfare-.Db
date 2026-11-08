<?php
/**
 * register.php
 * Self-registration is restricted to the BENEFICIARY role only.
 * Admin (social worker) accounts are seeded via SQL -- not self-registered,
 * because allowing users to pick "admin" at signup is an RBAC security hole.
 */
require_once 'config.php';

 $error = '';
 $msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Server-side: role is forced to beneficiary (NEVER trust a form field for role)
    $role = 'beneficiary';
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $error = "Username already taken!";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $password_hash, $role);
        if ($stmt->execute()) {
            $msg = "Registration successful! You can now log in. Note: an admin must link your account to a beneficiary profile before you can see your records.";
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
    <title>Register - Social Welfare Assistance System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">

    <div class="auth-card">
        <h2>Create Beneficiary Account</h2>
        <p class="auth-subtitle">Beneficiaries may self-register; social worker accounts are managed separately.</p>

        <?php if (!empty($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($msg)): ?>
            <div class="success-msg"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" required placeholder="Choose a username">

            <label>Password</label>
            <input type="password" name="password" required placeholder="Choose a password">

            <button type="submit">Register</button>
        </form>

        <div class="auth-link">
            <a href="login.php">Already have an account? Log In</a>
        </div>
    </div>

</body>
</html>