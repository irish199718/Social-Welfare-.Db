<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $app_id = $_POST['application_id'];
    $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE applications SET eligibility_status = ? WHERE application_id = ?");
    $stmt->bind_param("ss", $status, $app_id);
    $stmt->execute();
    $stmt->close();
}

$applications = $conn->query("
    SELECT a.application_id, b.beneficiary_name, h.household_id, h.address, a.program, a.application_date, a.eligibility_status
    FROM applications a
    JOIN beneficiaries b ON a.beneficiary_id = b.beneficiary_id
    JOIN households h ON b.household_id = h.household_id
    ORDER BY a.application_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Admin Dashboard</title>
<style>
    body { font-family: Arial, sans-serif; background: #f8fafc; padding: 20px; margin: 0; }
    .header { background: #1e3a8a; color: white; padding: 15px; display: flex; justify-content: space-between; border-radius: 6px; align-items: center; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; }
    th, td { border: 1px solid #e2e8f0; padding: 10px; text-align: left; font-size: 0.9em; }
    th { background: #f1f5f9; }
    .logout { color: white; background: #ef4444; padding: 6px 12px; text-decoration: none; border-radius: 4px; }
</style>
</head>
<body>
<div class="header">
    <h2>Social Worker Portal (Admin)</h2>
    <div><span>User: <?= $_SESSION['username'] ?></span> | <a href="logout.php" class="logout">Logout</a></div>
</div>
<h3>Assistance Applications</h3>
<table>
    <tr><th>App ID</th><th>Name</th><th>Household</th><th>Address</th><th>Program</th><th>Date</th><th>Status</th><th>Action</th></tr>
    <?php while($row = $applications->fetch_assoc()): ?>
    <tr>
        <td><?= $row['application_id'] ?></td>
        <td><?= htmlspecialchars($row['beneficiary_name']) ?></td>
        <td><?= $row['household_id'] ?></td>
        <td><?= htmlspecialchars($row['address']) ?></td>
        <td><?= htmlspecialchars($row['program']) ?></td>
        <td><?= $row['application_date'] ?></td>
        <td><strong><?= $row['eligibility_status'] ?></strong></td>
        <td>
            <form method="POST">
                <input type="hidden" name="application_id" value="<?= $row['application_id'] ?>">
                <select name="status" onchange="this.form.submit()">
                    <option value="Pending" <?= $row['eligibility_status']=='Pending'?'selected':'' ?>>Pending</option>
                    <option value="Approved" <?= $row['eligibility_status']=='Approved'?'selected':'' ?>>Approve</option>
                    <option value="Rejected" <?= $row['eligibility_status']=='Rejected'?'selected':'' ?>>Reject</option>
                </select>
                <input type="hidden" name="update_status" value="1">
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
</body>
</html>