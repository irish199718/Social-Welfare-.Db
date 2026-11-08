<?php
/**
 * register.php
 * Self-registration for BENEFICIARY role only.
 *
 * WHAT THIS DOES:
 *   1. Creates a login account in `users` (role = beneficiary, forced server-side)
 *   2. Auto-creates a household row for the new person
 *   3. Auto-creates a beneficiary profile with beneficiary_name = username
 *      and user_id = NULL (NOT yet linked)
 *   4. The admin must then use the "Link Beneficiary Profile" form on the
 *      admin dashboard to verify identity and connect the user account.
 *
 * Because the beneficiary_name is set to the username, the admin sees
 * matching names in both dropdowns of the Link form (e.g., "lili" on the
 * left, "lili (user #9)" on the right), making the matching obvious.
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
        // Transaction: create user + household + beneficiary (unlinked)
        $conn->begin_transaction();
        try {
            // 1. Create the login account
            $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $password_hash, $role);
            $stmt->execute();
            $new_user_id = $stmt->insert_id;
            $stmt->close();

            // 2. Auto-generate IDs based on the new user_id
            //    This avoids collisions with existing IDs (B-01, B-02, B-03 from seed data)
            $seq = $new_user_id + 10;
            $beneficiary_id = 'B-' . str_pad($seq, 2, '0', STR_PAD_LEFT);
            $household_id   = 'HH-' . str_pad($seq, 2, '0', STR_PAD_LEFT);

            // 3. Create a household row (address is a placeholder; admin can update later)
            $hstmt = $conn->prepare("INSERT INTO households (household_id, address) VALUES (?, '(Please update address)')");
            $hstmt->bind_param("s", $household_id);
            $hstmt->execute();
            $hstmt->close();

            // 4. Create the beneficiary profile with beneficiary_name = username
            //    user_id is NULL -> admin must link via the Link form
            $bstmt = $conn->prepare("INSERT INTO beneficiaries (beneficiary_id, beneficiary_name, household_id, user_id) VALUES (?, ?, ?, NULL)");
            $bstmt->bind_param("sss", $beneficiary_id, $username, $household_id);
            $bstmt->execute();
            $bstmt->close();

            $conn->commit();
            $msg = "Registration successful! A beneficiary profile ($beneficiary_id) has been created for you. "
                 . "Please visit the MSWDO office so the social worker can verify your identity and link your account. "
                 . "Once linked, you can log in to see your records.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Something went wrong. Please try again.";
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
        <p class="auth-subtitle">A pending beneficiary profile will be created in your name for verification by the social worker.</p>

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