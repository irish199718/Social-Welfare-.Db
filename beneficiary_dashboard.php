<?php
/**
 * beneficiary_dashboard.php
 * Role: beneficiary
 * Features:
 *   - Shows the beneficiary's profile (name, ID, household, address)
 *   - Lets the beneficiary UPDATE their name and address
 *   - Lets the beneficiary SUBMIT a new assistance application (BR2)
 *   - Lists only the beneficiary's own applications
 *   - Lists the disbursements released for each of their applications (BR4)
 *   - Beneficiary CANNOT approve applications or release aid (BR5 - admin only)
 */
require_once 'config.php';

// RBAC: only beneficiary can be here
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'beneficiary') {
    header("Location: login.php");
    exit();
}

 $user_id = $_SESSION['user_id'];
 $feedback = '';
 $feedback_type = '';

// Fetch linked profile information
 $stmt = $conn->prepare("
    SELECT b.beneficiary_id, b.beneficiary_name, h.household_id, h.address
    FROM beneficiaries b
    JOIN households h ON b.household_id = h.household_id
    WHERE b.user_id = ?
");
 $stmt->bind_param("i", $user_id);
 $stmt->execute();
 $profile = $stmt->get_result()->fetch_assoc();
 $stmt->close();

// ---------- Handle profile update ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile']) && $profile) {
    $new_name    = trim($_POST['beneficiary_name']);
    $new_address = trim($_POST['address']);

    if (empty($new_name)) {
        $feedback = "Name cannot be empty.";
        $feedback_type = 'error';
    } elseif (empty($new_address)) {
        $feedback = "Address cannot be empty.";
        $feedback_type = 'error';
    } else {
        // Transaction: update beneficiary name + household address
        $conn->begin_transaction();
        try {
            // 1. Update beneficiary name
            $bstmt = $conn->prepare("UPDATE beneficiaries SET beneficiary_name = ? WHERE beneficiary_id = ?");
            $bstmt->bind_param("ss", $new_name, $profile['beneficiary_id']);
            $bstmt->execute();
            $bstmt->close();

            // 2. Update household address
            $hstmt = $conn->prepare("UPDATE households SET address = ? WHERE household_id = ?");
            $hstmt->bind_param("ss", $new_address, $profile['household_id']);
            $hstmt->execute();
            $hstmt->close();

            $conn->commit();
            $feedback = "Profile updated successfully!";
            $feedback_type = 'success';

            // Refresh the profile variable so the form shows the new values
            $profile['beneficiary_name'] = $new_name;
            $profile['address'] = $new_address;
        } catch (Exception $e) {
            $conn->rollback();
            $feedback = "Error updating profile: " . $e->getMessage();
            $feedback_type = 'error';
        }
    }
}

// ---------- Handle new application submission ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application']) && $profile) {
    $program = trim($_POST['program']);
    $application_date = $_POST['application_date'];

    // Validate program against allowed list
    $allowed_programs = ['Emergency Aid', 'Food Assistance', 'Educational Aid', 'Medical Assistance', 'Burial Assistance', 'Calamity Aid'];
    if (!in_array($program, $allowed_programs)) {
        $feedback = "Invalid program selected.";
        $feedback_type = 'error';
    } elseif (empty($application_date)) {
        $feedback = "Please pick an application date.";
        $feedback_type = 'error';
    } else {
        // Auto-generate application_id
        $application_id = 'APP-' . strtoupper(substr(uniqid(), -6));

        try {
            $stmt = $conn->prepare("
                INSERT INTO applications (application_id, beneficiary_id, program, application_date, eligibility_status, approved_amount)
                VALUES (?, ?, ?, ?, 'Pending', 0.00)
            ");
            $stmt->bind_param("ssss", $application_id, $profile['beneficiary_id'], $program, $application_date);
            $stmt->execute();
            $stmt->close();
            $feedback = "Application $application_id submitted! Status: Pending. Please wait for the social worker to review it.";
            $feedback_type = 'success';
        } catch (mysqli_sql_exception $e) {
            $feedback = "Error submitting application: " . $e->getMessage();
            $feedback_type = 'error';
        }
    }
}

// Fetch own applications + total released per app
 $my_apps = null;
 $my_disbursements = null;

if ($profile) {
    $a = $conn->prepare("
        SELECT a.application_id, a.program, a.application_date, a.eligibility_status, a.approved_amount,
               COALESCE((SELECT SUM(d.amount_php) FROM disbursements d WHERE d.application_id = a.application_id), 0) AS total_released
        FROM applications a
        WHERE a.beneficiary_id = ?
        ORDER BY a.application_date DESC
    ");
    $a->bind_param("s", $profile['beneficiary_id']);
    $a->execute();
    $my_apps = $a->get_result();
    $a->close();

    $d = $conn->prepare("
        SELECT d.release_ref, d.application_id, d.release_date, d.amount_php
        FROM disbursements d
        JOIN applications a ON d.application_id = a.application_id
        WHERE a.beneficiary_id = ?
        ORDER BY d.release_date DESC
    ");
    $d->bind_param("s", $profile['beneficiary_id']);
    $d->execute();
    $my_disbursements = $d->get_result();
    $d->close();
}

// Default date for the form (today)
 $today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Beneficiary Portal - Social Welfare System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="header">
    <h2>Beneficiary Portal &mdash; MSWDO</h2>
    <div>
        <span>User: <?= htmlspecialchars($_SESSION['username']) ?> (Beneficiary)</span>
        &nbsp;|&nbsp;
        <a href="logout.php" class="logout">Logout</a>
    </div>
</div>

<?php if ($feedback): ?>
    <div class="feedback <?= $feedback_type ?>"><?= htmlspecialchars($feedback) ?></div>
<?php endif; ?>

<?php if (!$profile): ?>
    <div class="feedback error">Your account is not yet linked to a beneficiary profile. Please contact the social worker / administrator.</div>
<?php else: ?>

<!-- ===================== PROFILE ===================== -->
<div class="card">
    <h3>My Profile Information</h3>
    <p><strong>Beneficiary ID:</strong> <?= htmlspecialchars($profile['beneficiary_id']) ?></p>
    <p><strong>Name:</strong> <?= htmlspecialchars($profile['beneficiary_name']) ?></p>
    <p><strong>Household:</strong> <?= htmlspecialchars($profile['household_id']) ?>
        (<?= htmlspecialchars($profile['address']) ?>)</p>
</div>

<!-- ===================== UPDATE PROFILE ===================== -->
<div class="card">
    <h3>Update My Profile</h3>
    <p class="muted">Update your real name and household address. Your Beneficiary ID and Household ID will not change.</p>
    <form method="POST" class="grid-form" style="grid-template-columns: 1fr 2fr 1fr;">
        <input type="text" name="beneficiary_name" value="<?= htmlspecialchars($profile['beneficiary_name']) ?>" placeholder="Your full name" required>
        <input type="text" name="address" value="<?= htmlspecialchars($profile['address']) ?>" placeholder="Your household address" required>
        <input type="hidden" name="update_profile" value="1">
        <button type="submit">Update Profile</button>
    </form>
</div>

<!-- ===================== APPLY FOR ASSISTANCE ===================== -->
<div class="card">
    <h3>Apply for Assistance</h3>
    <p class="muted">Submit a new application. The social worker will review it and update the status.</p>
    <form method="POST" class="grid-form" style="grid-template-columns: 1.5fr 1fr 2fr 1fr;">
        <select name="program" required>
            <option value="">-- Select Program --</option>
            <option value="Emergency Aid">Emergency Aid</option>
            <option value="Food Assistance">Food Assistance</option>
            <option value="Educational Aid">Educational Aid</option>
            <option value="Medical Assistance">Medical Assistance</option>
            <option value="Burial Assistance">Burial Assistance</option>
            <option value="Calamity Aid">Calamity Aid</option>
        </select>
        <input type="date" name="application_date" value="<?= $today ?>" required>
        <input type="text" name="message" placeholder="Optional: brief description of your request">
        <input type="hidden" name="submit_application" value="1">
        <button type="submit">Submit Application</button>
    </form>
</div>

<!-- ===================== MY APPLICATIONS ===================== -->
<h3>My Assistance Applications</h3>
<table>
    <tr>
        <th>App ID</th><th>Program</th><th>Date Applied</th>
        <th>Status</th><th>Approved Amount</th><th>Total Released</th>
    </tr>
    <?php if ($my_apps && $my_apps->num_rows > 0): ?>
        <?php while($row = $my_apps->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['application_id']) ?></td>
            <td><?= htmlspecialchars($row['program']) ?></td>
            <td><?= $row['application_date'] ?></td>
            <td><span class="status-badge status-<?= strtolower($row['eligibility_status']) ?>"><?= $row['eligibility_status'] ?></span></td>
            <td>PHP <?= number_format($row['approved_amount'], 2) ?></td>
            <td>PHP <?= number_format($row['total_released'], 2) ?></td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="6" class="empty">You have not submitted any applications yet. Use the form above to apply.</td></tr>
    <?php endif; ?>
</table>

<!-- ===================== MY DISBURSEMENTS ===================== -->
<h3>My Disbursement Releases</h3>
<table>
    <tr>
        <th>Release Ref</th><th>Application</th><th>Release Date</th><th>Amount (PHP)</th>
    </tr>
    <?php if ($my_disbursements && $my_disbursements->num_rows > 0): ?>
        <?php while($row = $my_disbursements->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['release_ref']) ?></td>
            <td><?= htmlspecialchars($row['application_id']) ?></td>
            <td><?= $row['release_date'] ?></td>
            <td>PHP <?= number_format($row['amount_php'], 2) ?></td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="4" class="empty">No disbursements released for your applications yet.</td></tr>
    <?php endif; ?>
</table>

<?php endif; ?>

</body>
</html>