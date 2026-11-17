<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

$counts = $pdo->query('SELECT status, COUNT(*) FROM reservation GROUP BY status')
              ->fetchAll(PDO::FETCH_KEY_PAIR);
foreach (['pending', 'approved', 'cancelled', 'completed'] as $s) {
    $counts[$s] = (int) ($counts[$s] ?? 0);
}

$revenue = (float) $pdo->query(
    "SELECT COALESCE(SUM(CASE WHEN payment_type = 'refund' THEN -amount ELSE amount END), 0) FROM payment"
)->fetchColumn();

$today = date('Y-m-d');

$stmt = $pdo->prepare(
    "SELECT * FROM v_reservation_details
     WHERE check_in_date = ? AND status = 'approved' ORDER BY guest_name"
);
$stmt->execute([$today]);
$arrivals = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT * FROM v_reservation_details
     WHERE check_out_date = ? AND status = 'approved' ORDER BY guest_name"
);
$stmt->execute([$today]);
$departures = $stmt->fetchAll();

$pending = $pdo->query(
    "SELECT * FROM v_reservation_details WHERE status = 'pending' ORDER BY created_at LIMIT 10"
)->fetchAll();

$page_title = 'Dashboard | Bella Vista Admin';
include __DIR__ . '/../includes/admin_header.php';
?>

<h2>Dashboard</h2>
<p>Today is <?= e(date('l, F j, Y')) ?></p>

<section id="summary">
    <h3>Summary</h3>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr>
            <th>Pending</th>
            <th>Approved</th>
            <th>Cancelled</th>
            <th>Completed</th>
            <th>Total revenue</th>
        </tr>
        <tr>
            <td><?= $counts['pending'] ?></td>
            <td><?= $counts['approved'] ?></td>
            <td><?= $counts['cancelled'] ?></td>
            <td><?= $counts['completed'] ?></td>
            <td><?= peso($revenue) ?></td>
        </tr>
    </table>
</section>

<section id="pending">
    <h3>Pending reservations (need action)</h3>
    <?php if (!$pending): ?>
        <p>No pending reservations.</p>
    <?php else: ?>
        <table border="1" cellpadding="6" cellspacing="0">
            <tr>
                <th>No.</th><th>Guest</th><th>Room</th><th>Check-in</th><th>Check-out</th>
                <th>Total</th><th>Downpayment due</th><th></th>
            </tr>
            <?php foreach ($pending as $r): ?>
                <tr>
                    <td>BV-<?= str_pad((string) $r['reservation_id'], 5, '0', STR_PAD_LEFT) ?></td>
                    <td><?= e($r['guest_name']) ?></td>
                    <td><?= e($r['room_name']) ?></td>
                    <td><?= e($r['check_in_date']) ?></td>
                    <td><?= e($r['check_out_date']) ?></td>
                    <td><?= peso($r['total_amount']) ?></td>
                    <td><?= peso($r['downpayment_amount']) ?></td>
                    <td><a href="reservations.php?id=<?= (int) $r['reservation_id'] ?>">Manage</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</section>

<section id="arrivals">
    <h3>Today's check-ins (<?= e(CHECK_IN_TIME) ?>)</h3>
    <?php if (!$arrivals): ?>
        <p>No check-ins today.</p>
    <?php else: ?>
        <table border="1" cellpadding="6" cellspacing="0">
            <tr><th>No.</th><th>Guest</th><th>Room</th><th>Guests</th><th>Balance due</th></tr>
            <?php foreach ($arrivals as $r): ?>
                <tr>
                    <td>BV-<?= str_pad((string) $r['reservation_id'], 5, '0', STR_PAD_LEFT) ?></td>
                    <td><?= e($r['guest_name']) ?></td>
                    <td><?= e($r['room_name']) ?></td>
                    <td><?= (int) $r['num_guests'] ?></td>
                    <td><?= peso($r['balance']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</section>

<section id="departures">
    <h3>Today's check-outs (<?= e(CHECK_OUT_TIME) ?>)</h3>
    <?php if (!$departures): ?>
        <p>No check-outs today.</p>
    <?php else: ?>
        <table border="1" cellpadding="6" cellspacing="0">
            <tr><th>No.</th><th>Guest</th><th>Room</th><th>Balance due</th></tr>
            <?php foreach ($departures as $r): ?>
                <tr>
                    <td>BV-<?= str_pad((string) $r['reservation_id'], 5, '0', STR_PAD_LEFT) ?></td>
                    <td><?= e($r['guest_name']) ?></td>
                    <td><?= e($r['room_name']) ?></td>
                    <td><?= peso($r['balance']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
