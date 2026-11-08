<?php
/**
 * register.php
 * Self-registration for BENEFICIARY role only.
 *
 * UPDATE: Now automatically creates a beneficiary profile AND links it
 * to the new user account. No admin action needed.
 * Admin (social worker) accounts are seeded via SQL only.
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

    // Check if username already exists
    $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $error = "Username already taken!";
    } else {
        // Transaction: create user, create beneficiary profile, link them
        $conn->begin_transaction();
        try {
            // 1. Create the login account
            $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $password_hash, $role);
            $stmt->execute();
            $new_user_id = $stmt->insert_id;
            $stmt->close();

            // 2. Auto-generate IDs for the new beneficiary
            //    beneficiary_id: B-### (zero-padded based on user_id)
            //    household_id: HH-### (a new household for this person)
            $beneficiary_id = 'B-' . str_pad($new_user_id + 10, 2, '0', STR_PAD_LEFT);
            $household_id   = 'HH-' . str_pad($new_user_id + 10, 02, '0', STR_PAD_LEFT);

            // 3. Create a household row (address is blank - admin can update later)
            $hstmt = $conn->prepare("INSERT INTO households (household_id, address) VALUES (?, '(Please update address)')");
            $hstmt->bind_param("s", $household_id);
            $hstmt->execute();
            $hstmt->close();

            // 4. Create the beneficiary profile linked to this user
            //    Name defaults to the username; admin can update it later
            $bstmt = $conn->prepare("INSERT INTO beneficiaries (beneficiary_id, beneficiary_name, household_id, user_id) VALUES (?, ?, ?, ?)");
            $bstmt->bind_param("sssi", $beneficiary_id, $username, $household_id, $new_user_id);
            $bstmt->execute();
            $bstmt->close();

            $conn->commit();
            $msg = "Registration successful! Your Beneficiary ID is $beneficiary_id. You can now log in.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Something went wrong. Please try again. (" . $e->getMessage() . ")";
        }
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
        <p class="auth-subtitle">A beneficiary profile will be created for you automatically.</p>

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