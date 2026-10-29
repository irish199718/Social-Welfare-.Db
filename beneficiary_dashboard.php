<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'beneficiary') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch linked profile information
$stmt = $conn->prepare("SELECT b.beneficiary_id, b.beneficiary_name, h.household_id, h.address FROM beneficiaries b JOIN households h ON b.household_id = h.household_id WHERE b.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Beneficiary Portal</title>
<style>
    body { font-family: Arial, sans-serif; background: #f8fafc; padding: 20px; margin: 0; }
    .header { background: #15803d; color: white; padding: 15px; display: flex; justify-content: space-between; border-radius: 6px; align-items: center; }
    .card { background: white; padding: 20px; border-radius: 6px; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .logout { color: white; background: #ef4444; padding: 6px 12px; text-decoration: none; border-radius: 4px; }
</style>
</head>
<body>
<div class="header">
    <h2>Beneficiary Portal</h2>
    <div><span>User: <?= $_SESSION['username'] ?></span> | <a href="logout.php" class="logout">Logout</a></div>
</div>
<div class="card">
    <h3>My Profile Information</h3>
    <?php if($profile): ?>
        <p><strong>ID:</strong> <?= $profile['beneficiary_id'] ?></p>
        <p><strong>Name:</strong> <?= htmlspecialchars($profile['beneficiary_name']) ?></p>
        <p><strong>Household:</strong> <?= $profile['household_id'] ?> (<?= htmlspecialchars($profile['address']) ?>)</p>
    <?php else: ?>
        <p style="color: orange;">Your account is not yet linked to a beneficiary profile. Please contact the administrator.</p>
    <?php endif; ?>
</div>
</body>
</html>