<?php
session_start();
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$id  = (int) ($_SESSION['last_reservation_id'] ?? 0);
$res = null;

if ($id) {
    $stmt = $pdo->prepare(
        "SELECT r.*, CONCAT(g.first_name, ' ', g.last_name) AS guest_name,
                g.email, g.phone, rm.room_name, rt.type_name
         FROM reservation r
         JOIN guest g       ON g.guest_id = r.guest_id
         JOIN room rm       ON rm.room_id = r.room_id
         JOIN room_type rt  ON rt.room_type_id = rm.room_type_id
         WHERE r.reservation_id = ?"
    );
    $stmt->execute([$id]);
    $res = $stmt->fetch() ?: null;
}

$page_title = 'Reservation Received | Bella Vista';
include __DIR__ . '/includes/header.php';
?>

<?php if (!$res): ?>
    <h2>No reservation found</h2>
    <p><a href="index.php">Back to home</a></p>

<?php else: ?>
    <h2>Reservation Received</h2>
    <p>Thank you, <?= e($res['guest_name']) ?>! Your reservation is <strong>pending approval</strong>. Our team will confirm it once your downpayment is received.</p>

    <table border="1" cellpadding="6" cellspacing="0">
        <tr><th align="left">Reservation no.</th><td>BV-<?= str_pad((string) $res['reservation_id'], 5, '0', STR_PAD_LEFT) ?></td></tr>
        <tr><th align="left">Status</th><td><?= e(ucfirst($res['status'])) ?></td></tr>
        <tr><th align="left">Room</th><td><?= e($res['room_name']) ?> (<?= e($res['type_name']) ?>)</td></tr>
        <tr><th align="left">Check-in</th><td><?= e(parse_date($res['check_in_date'])->format('F j, Y')) ?>, <?= e(CHECK_IN_TIME) ?></td></tr>
        <tr><th align="left">Check-out</th><td><?= e(parse_date($res['check_out_date'])->format('F j, Y')) ?>, <?= e(CHECK_OUT_TIME) ?></td></tr>
        <tr><th align="left">Guests</th><td><?= (int) $res['num_guests'] ?></td></tr>
        <tr><th align="left">Nights</th><td><?= (int) $res['num_nights'] ?></td></tr>
        <tr><th align="left">Room charge</th><td><?= peso($res['base_amount']) ?></td></tr>
        <tr><th align="left">Extra guest fee</th><td><?= peso($res['extra_person_fee_total']) ?></td></tr>
        <tr><th align="left">Total</th><td><strong><?= peso($res['total_amount']) ?></strong></td></tr>
        <tr><th align="left">Downpayment required</th><td><?= peso($res['downpayment_amount']) ?></td></tr>
        <tr><th align="left">Balance due at check-in</th><td><?= peso($res['total_amount'] - $res['downpayment_amount']) ?></td></tr>
        <tr><th align="left">Email</th><td><?= e($res['email']) ?></td></tr>
        <tr><th align="left">Phone</th><td><?= e($res['phone']) ?></td></tr>
    </table>

    <h3>Reminders</h3>
    <?php include __DIR__ . '/includes/terms.php'; ?>

    <p><a href="index.php">Back to home</a></p>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
