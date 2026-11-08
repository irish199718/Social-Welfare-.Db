<?php
/**
 * admin_dashboard.php
 * Role: admin (Social Worker - MSWDO personnel)
 * Features:
 *   - View all assistance applications
 *   - Approve / Reject applications (BR5: only authorized personnel)
 *       When approving, admin sets the approved_amount (used for BR5 batch limit).
 *   - Add disbursement releases for Approved applications (BR4)
 *       Release Ref is auto-generated; server enforces BR5: SUM(amount) <= approved_amount
 *   - Manage beneficiaries (link newly registered user accounts to a beneficiary profile)
 */
require_once 'config.php';

// RBAC: only admin can be here
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

 $feedback = '';
 $feedback_type = '';

// ---------- Handle POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // (1) Update application eligibility status (BR5 - only admin can do this)
    if (isset($_POST['update_status'])) {
        $app_id    = $_POST['application_id'];
        $status    = $_POST['status'];
        $approved_amount = ($status === 'Approved') ? floatval($_POST['approved_amount']) : 0.00;

        $stmt = $conn->prepare("UPDATE applications SET eligibility_status = ?, approved_amount = ? WHERE application_id = ?");
        $stmt->bind_param("sds", $status, $approved_amount, $app_id);
        $stmt->execute();
        $stmt->close();
        $feedback = "Application $app_id updated to $status.";
        $feedback_type = 'success';
    }

    // (2) Add a disbursement release (BR4 + BR5 batch limit)
    if (isset($_POST['add_disbursement'])) {
        $app_id       = $_POST['application_id'];
        $release_date = $_POST['release_date'];
        $amount       = floatval($_POST['amount']);

        // BR5 server-side: re-fetch application status + approved_amount + sum of existing disbursements
        $chk = $conn->prepare("
            SELECT a.eligibility_status, a.approved_amount,
                   COALESCE((SELECT SUM(d.amount_php) FROM disbursements d WHERE d.application_id = a.application_id), 0) AS already_released
            FROM applications a
            WHERE a.application_id = ?
        ");
        $chk->bind_param("s", $app_id);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$row) {
            $feedback = "Application.";
            $feedback_type = 'error';
        } elseif ($row['eligibility_status'] !== 'Approved') {
            $feedback = "BR5 violation: application $app_id is not Approved; cannot release aid.";
            $feedback_type = 'error';
        } elseif (($row['already_released'] + $amount) > $row['approved_amount']) {
            $feedback = "BR5 violation: total release (PHP " . number_format($row['already_released'] + $amount, 2)
                      . ") would exceed approved amount (PHP " . number_format($row['approved_amount'], 2) . ").";
            $feedback_type = 'error';
        } else {
            // AUTO-GENERATE a unique release_ref (no more manual typing!)
            // Format: REL-YYMMDD-XXXX (e.g. REL-261108-a3b2)
            $release_ref = 'REL-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -4));

            try {
                $stmt = $conn->prepare("INSERT INTO disbursements (release_ref, application_id, release_date, amount_php) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssd", $release_ref, $app_id, $release_date, $amount);
                $stmt->execute();
                $stmt->close();
                $feedback = "Disbursement $release_ref released for $app_id.";
                $feedback_type = 'success';
            } catch (mysqli_sql_exception $e) {
                // Fallback: if somehow the generated ref collides, try once more with more entropy
                $release_ref = 'REL-' . strtoupper(uniqid());
                try {
                    $stmt = $conn->prepare("INSERT INTO disbursements (release_ref, application_id, release_date, amount_php) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssd", $release_ref, $app_id, $release_date, $amount);
                    $stmt->execute();
                    $stmt->close();
                    $feedback = "Disbursement $release_ref released for $app_id.";
                    $feedback_type = 'success';
                } catch (mysqli_sql_exception $e2) {
                    $feedback = "Error releasing aid: " . $e2->getMessage();
                    $feedback_type = 'error';
                }
            }
        }
    }

    // (3) Link a registered user to an existing beneficiary profile
    if (isset($_POST['link_beneficiary'])) {
        $beneficiary_id = $_POST['beneficiary_id'];
        $user_id        = $_POST['user_id'];

        // Clear any previous link on this beneficiary, then set the new link
        $clr = $conn->prepare("UPDATE beneficiaries SET user_id = NULL WHERE user_id = ?");
        $clr->bind_param("i", $user_id);
        $clr->execute();
        $clr->close();

        $stmt = $conn->prepare("UPDATE beneficiaries SET user_id = ? WHERE beneficiary_id = ?");
        $stmt->bind_param("is", $user_id, $beneficiary_id);
        $stmt->execute();
        $stmt->close();
        $feedback = "Beneficiary $beneficiary_id linked to user #$user_id.";
        $feedback_type = 'success';
    }
}

// ---------- Fetch data for display ----------
 $applications = $conn->query("
    SELECT a.application_id, b.beneficiary_name, h.household_id, h.address,
           a.program, a.application_date, a.request_message,
           a.eligibility_status, a.approved_amount,
           COALESCE((SELECT SUM(d.amount_php) FROM disbursements d WHERE d.application_id = a.application_id), 0) AS total_released
    FROM applications a
    JOIN beneficiaries b ON a.beneficiary_id = b.beneficiary_id
    JOIN households    h ON b.household_id   = h.household_id
    ORDER BY a.application_date DESC
");

 $disbursements = $conn->query("
    SELECT d.release_ref, d.application_id, d.release_date, d.amount_php,
           b.beneficiary_name
    FROM disbursements d
    JOIN applications a ON d.application_id = a.application_id
    JOIN beneficiaries b ON a.beneficiary_id = b.beneficiary_id
    ORDER BY d.release_date DESC
");

// Approved applications (for the disbursement form dropdown)
 $approved_apps = $conn->query("
    SELECT a.application_id, b.beneficiary_name, a.program, a.approved_amount,
           COALESCE((SELECT SUM(d.amount_php) FROM disbursements d WHERE d.application_id = a.application_id), 0) AS already_released
    FROM applications a
    JOIN beneficiaries b ON a.beneficiary_id = b.beneficiary_id
    WHERE a.eligibility_status = 'Approved'
    ORDER BY a.application_id
");

// For beneficiary-user linking
 $beneficiaries = $conn->query("SELECT beneficiary_id, beneficiary_name, user_id FROM beneficiaries ORDER BY beneficiary_id");
 $unlinked_users = $conn->query("SELECT u.user_id, u.username FROM users u WHERE u.role = 'beneficiary' AND u.user_id NOT IN (SELECT user_id FROM beneficiaries WHERE user_id IS NOT NULL) ORDER BY u.username");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Social Welfare System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="header">
    <h2>Social Worker Portal (Admin) &mdash; MSWDO</h2>
    <div>
        <span>User: <?= htmlspecialchars($_SESSION['username']) ?> (Admin)</span>
        &nbsp;|&nbsp;
        <a href="logout.php" class="logout">Logout</a>
    </div>
</div>

<?php if ($feedback): ?>
    <div class="feedback <?= $feedback_type ?>"><?= htmlspecialchars($feedback) ?></div>
<?php endif; ?>

<!-- ===================== APPLICATIONS ===================== -->
<h3>Assistance Applications</h3>
<table>
       <tr>
        <th>App ID</th><th>Beneficiary</th><th>Household</th><th>Address</th>
        <th>Program</th><th>Date</th><th>Request Message</th><th>Status</th><th>Approved</th><th>Released</th><th>Action</th>
    </tr>
    <?php while($row = $applications->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['application_id']) ?></td>
        <td><?= htmlspecialchars($row['beneficiary_name']) ?></td>
        <td><?= htmlspecialchars($row['household_id']) ?></td>
        <td><?= htmlspecialchars($row['address']) ?></td>
        <td><?= htmlspecialchars($row['program']) ?></td>
        <td><?= $row['application_date'] ?></td>
        <td><?= htmlspecialchars($row['request_message'] ?? '(none)') ?></td>
        <td><span class="status-badge status-<?= strtolower($row['eligibility_status']) ?>"><?= $row['eligibility_status'] ?></span></td>
        <td>PHP <?= number_format($row['approved_amount'], 2) ?></td>
        <td>PHP <?= number_format($row['total_released'], 2) ?></td>
        <td>
            <form method="POST" style="display:flex; gap:4px; flex-wrap:wrap;">
                <input type="hidden" name="application_id" value="<?= $row['application_id'] ?>">
                <select name="status" onchange="this.form.submit()">
                    <option value="Pending"  <?= $row['eligibility_status']==='Pending'?'selected':'' ?>>Pending</option>
                    <option value="Approved" <?= $row['eligibility_status']==='Approved'?'selected':'' ?>>Approve</option>
                    <option value="Rejected" <?= $row['eligibility_status']==='Rejected'?'selected':'' ?>>Reject</option>
                </select>
                <?php if ($row['eligibility_status'] === 'Approved' || $row['eligibility_status'] === 'Pending'): ?>
                <input type="number" name="approved_amount" step="0.01" min="0"
                       value="<?= $row['approved_amount'] ?>" placeholder="Amount"
                       style="width:90px; padding:5px;" required>
                <?php endif; ?>
                <input type="hidden" name="update_status" value="1">
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

<!-- ===================== DISBURSEMENTS ===================== -->
<h3>Disbursement Releases</h3>
<table>
    <tr>
        <th>Release Ref</th><th>Application</th><th>Beneficiary</th>
        <th>Release Date</th><th>Amount (PHP)</th>
    </tr>
    <?php while($row = $disbursements->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['release_ref']) ?></td>
        <td><?= htmlspecialchars($row['application_id']) ?></td>
        <td><?= htmlspecialchars($row['beneficiary_name']) ?></td>
        <td><?= $row['release_date'] ?></td>
        <td>PHP <?= number_format($row['amount_php'], 2) ?></td>
    </tr>
    <?php endwhile; ?>
</table>

<!-- Add disbursement (BR5 enforced server-side; Release Ref auto-generated) -->
<div class="card">
    <h3>Add Disbursement <small>(BR5: total cannot exceed approved amount; Release Ref auto-generated)</small></h3>
    <form method="POST" class="grid-form">
        <select name="application_id" required>
            <option value="">-- Approved Application --</option>
            <?php while($a = $approved_apps->fetch_assoc()):
                $remaining = $a['approved_amount'] - $a['already_released']; ?>
                <option value="<?= $a['application_id'] ?>">
                    <?= htmlspecialchars($a['application_id'].' - '.$a['beneficiary_name'].' - '.$a['program'])
                       .' (Remaining: PHP '.number_format($remaining, 2).')' ?>
                </option>
            <?php endwhile; ?>
        </select>
        <input type="date" name="release_date" required>
        <input type="number" name="amount" step="0.01" min="0.01" placeholder="Amount (PHP)" required>
        <input type="hidden" name="add_disbursement" value="1">
        <button type="submit" style="grid-column: 1 / -1;">Release Aid</button>
    </form>
</div>

<!-- ===================== LINK BENEFICIARY TO USER ===================== -->
<h3>Link Beneficiary Profile to User Account</h3>
<p class="muted">When a beneficiary self-registers, their account is NOT yet linked to a profile.
Use this form to link them so they can see their applications and disbursements.</p>
<form method="POST" class="grid-form" style="grid-template-columns: 2fr 2fr 1fr;">
    <select name="beneficiary_id" required>
        <option value="">-- Beneficiary --</option>
        <?php $beneficiaries->data_seek(0); while($b = $beneficiaries->fetch_assoc()): ?>
            <option value="<?= $b['beneficiary_id'] ?>">
                <?= htmlspecialchars($b['beneficiary_id'].' - '.$b['beneficiary_name'])
                   . ($b['user_id'] ? ' (linked to user #'.$b['user_id'].')' : ' (unlinked)') ?>
            </option>
        <?php endwhile; ?>
    </select>
    <select name="user_id" required>
        <option value="">-- Unlinked User Account --</option>
        <?php while($u = $unlinked_users->fetch_assoc()): ?>
            <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['username']) ?> (user #<?= $u['user_id'] ?>)</option>
        <?php endwhile; ?>
    </select>
    <input type="hidden" name="link_beneficiary" value="1">
    <button type="submit">Link</button>
</form>

</body>
</html>