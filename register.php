<?php
require_once 'config.php';
$msg = ''; $error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

    if (!empty($username) && !empty($password)) {
        $hashed_pwd = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashed_pwd, $role);
        
        if ($stmt->execute()) {
            $msg = "Registered successfully! <a href='login.php'>Login here</a>";
        } else {
            $error = "Username already taken.";
        }
        $stmt->close();
    } else {
        $error = "All fields required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Register</title>
<style>
    body { font-family: Arial, sans-serif; background: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
    .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 320px; }
    input, select { width: 100%; padding: 8px; margin: 8px 0; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
    button { width: 100%; padding: 10px; background: #16a34a; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
</style>
</head>
<body>
<div class="card">
    <h2 style="text-align:center; color:#16a34a;">Register</h2>
    <?php if($error): ?><p style="color:red; font-size:0.85em;"><?= $error ?></p><?php endif; ?>
    <?php if($msg): ?><p style="color:green; font-size:0.85em;"><?= $msg ?></p><?php endif; ?>
    <form method="POST">
        <label>Username</label><input type="text" name="username" required>
        <label>Password</label><input type="password" name="password" required>
        <label>Role</label>
        <select name="role">
            <option value="beneficiary">Beneficiary</option>
            <option value="admin">Social Worker (Admin)</option>
        </select>
        <button type="submit">Register</button>
    </form>
    <p style="text-align:center; font-size:0.85em; margin-top:15px;"><a href="login.php">Back to Login</a></p>
</div>
</body>
</html>