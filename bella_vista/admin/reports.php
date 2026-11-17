<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

$today = date('Y-m-d');
$from  = $_GET['from'] ?? date('Y-01-01');
$to    = $_GET['to'] ?? $today;

$errors = [];
$f = parse_date($from);
$t = parse_date($to);
if (!$f || !$t) {
    $errors[] = 'Choose valid dates. Showing the current year instead.';
    $from = date('Y-01-01');
    $to   = $today;
    $f = parse_date($from);
    $t = parse_date($to);
} elseif ($t < $f) {
    $errors[] = 'The end date cannot be before the start date. Showing the current year instead.';
    $from = date('Y-01-01');
    $to   = $today;
    $f = parse_date($from);
    $t = parse_date($to);
}

$NET = "CASE WHEN payment_type = 'refund' THEN -amount ELSE amount END";

/* Revenue by payment type */
$stmt = $pdo->prepare(
    'SELECT payment_type, SUM(amount) FROM payment
     WHERE DATE(payment_date) BETWEEN ? AND ? GROUP BY payment_type'
);
$stmt->execute([$from, $to]);
$by_type = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$types = ['downpayment', 'balance', 'fine', 'damage', 'refund'];
foreach ($types as $ty) {
    $by_type[$ty] = (float) ($by_type[$ty] ?? 0);
}
$net_revenue = $by_type['downpayment'] + $by_type['balance'] + $by_type['fine'] + $by_type['damage'] - $by_type['refund'];

/* Revenue by room type */
$stmt = $pdo->prepare(
    "SELECT rt.room_type_id, SUM(CASE WHEN p.payment_type = 'refund' THEN -p.amount ELSE p.amount END)
     FROM payment p
     JOIN reservation r ON r.reservation_id = p.reservation_id
     JOIN room rm       ON rm.room_id = r.room_id
     JOIN room_type rt  ON rt.room_type_id = rm.room_type_id
     WHERE DATE(p.payment_date) BETWEEN ? AND ?
     GROUP BY rt.room_type_id"
);
$stmt->execute([$from, $to]);
$by_room_type = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$room_types   = get_room_types($pdo);

/* Bookings by status (created in range) */
$stmt = $pdo->prepare('SELECT status, COUNT(*) FROM reservation WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status');
$stmt->execute([$from, $to]);
$by_status = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$statuses  = ['pending', 'approved', 'cancelled', 'completed'];
$total_bookings = 0;
foreach ($statuses as $s) {
    $by_status[$s] = (int) ($by_status[$s] ?? 0);
    $total_bookings += $by_status[$s];
}

/* Monthly table */
$stmt = $pdo->prepare(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) FROM reservation
     WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY ym"
);
$stmt->execute([$from, $to]);
$month_bookings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $pdo->prepare(
    "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, SUM($NET) FROM payment
     WHERE DATE(payment_date) BETWEEN ? AND ? GROUP BY ym"
);
$stmt->execute([$from, $to]);
$month_revenue = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$months = [];
$cursor = $f->modify('first day of this month');
$guard  = 0;
while ($cursor <= $t && $guard < 60) {
    $key = $cursor->format('Y-m');
    $months[] = [
        'key'      => $key,
        'label'    => $cursor->format('F Y'),
        'bookings' => (int) ($month_bookings[$key] ?? 0),
        'revenue'  => (float) ($month_revenue[$key] ?? 0),
    ];
    $cursor = $cursor->modify('+1 month');
    $guard++;
}

/* Outstanding balances on approved reservations (as of today) */
$outstanding = (float) $pdo->query(
    "SELECT COALESCE(SUM(b.balance), 0)
     FROM v_reservation_balance b
     JOIN reservation r ON r.reservation_id = b.reservation_id
     WHERE r.status = 'approved'"
)->fetchColumn();

$page_title = 'Reports | Bella Vista Admin';
include __DIR__ . '/../includes/admin_header.php';
?>

<h2>Reports</h2>

<?php if ($errors): ?>
    <ul>
        <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="get" action="reports.php">
    <label for="from">From</label>
    <input type="date" id="from" name="from" value="<?= e($from) ?>" required>
    <label for="to">To</label>
    <input type="date" id="to" name="to" value="<?= e($to) ?>" required>
    <button type="submit">Apply</button>
    <button type="button" onclick="window.print()">Print report</button>
</form>

<p>Period: <?= e($f->format('F j, Y')) ?> to <?= e($t->format('F j, Y')) ?></p>

<section id="summary">
    <h3>Summary</h3>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr><th align="left">Net revenue (payments received minus refunds)</th><td><strong><?= peso($net_revenue) ?></strong></td></tr>
        <tr><th align="left">Bookings made</th><td><?= $total_bookings ?></td></tr>
        <tr><th align="left">Outstanding balances on approved reservations (as of today)</th><td><?= peso($outstanding) ?></td></tr>
    </table>
</section>

<section id="by-status">
    <h3>Bookings by status</h3>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr><th>Pending</th><th>Approved</th><th>Cancelled</th><th>Completed</th><th>Total</th></tr>
        <tr>
            <td><?= $by_status['pending'] ?></td>
            <td><?= $by_status['approved'] ?></td>
            <td><?= $by_status['cancelled'] ?></td>
            <td><?= $by_status['completed'] ?></td>
            <td><?= $total_bookings ?></td>
        </tr>
    </table>
</section>

<section id="by-month">
    <h3>Monthly bookings and revenue</h3>
    <div class="table-wrap">
    <table border="1" cellpadding="6" cellspacing="0">
        <tr><th>Month</th><th>Bookings made</th><th>Net revenue</th></tr>
        <?php foreach ($months as $m): ?>
            <tr>
                <td><?= e($m['label']) ?></td>
                <td><?= $m['bookings'] ?></td>
                <td><?= peso($m['revenue']) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <th align="left">Total</th>
            <th align="left"><?= array_sum(array_column($months, 'bookings')) ?></th>
            <th align="left"><?= peso(array_sum(array_column($months, 'revenue'))) ?></th>
        </tr>
    </table>
    </div>
</section>

<section id="by-payment-type">
    <h3>Revenue by payment type</h3>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr><th>Type</th><th>Amount</th></tr>
        <?php foreach ($types as $ty): ?>
            <tr>
                <td><?= e(ucfirst($ty)) ?></td>
                <td><?= $ty === 'refund' ? '-' : '' ?><?= peso($by_type[$ty]) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr><th align="left">Net</th><th align="left"><?= peso($net_revenue) ?></th></tr>
    </table>
</section>

<section id="by-room-type">
    <h3>Revenue by room type</h3>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr><th>Room type</th><th>Net revenue</th></tr>
        <?php foreach ($room_types as $rt): ?>
            <tr>
                <td><?= e($rt['type_name']) ?></td>
                <td><?= peso($by_room_type[$rt['room_type_id']] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</section>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
