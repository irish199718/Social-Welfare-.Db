<?php
/**
 * beneficiary_dashboard.php
 * Role: beneficiary
 * Features:
 *   - Shows the beneficiary's profile (name, ID, household, address)
 *   - Lists only the beneficiary's own applications (BR2)
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
        <tr><td colspan="6" class="empty">You have not submitted any applications yet.</td></tr>
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