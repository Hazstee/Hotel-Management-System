<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$check_in     = $_GET['check_in'] ?? '';
$check_out    = $_GET['check_out'] ?? '';
$room_type_id = (int) ($_GET['room_type'] ?? 0);
$guests       = (int) ($_GET['guests'] ?? 0);

$errors = validate_booking_dates($check_in, $check_out);

$type = null;
foreach (get_room_types($pdo) as $t) {
    if ((int) $t['room_type_id'] === $room_type_id) {
        $type = $t;
    }
}
if (!$type) {
    $errors[] = 'Please choose a room type.';
} else {
    $errors = array_merge($errors, validate_guests($type, $guests));
}

$rooms  = [];
$nights = 0;
if (!$errors) {
    $nights = count_nights($check_in, $check_out);
    $rooms  = get_available_rooms($pdo, $check_in, $check_out, $guests, $room_type_id);
}

$page_title = 'Available Rooms | Bella Vista';
include __DIR__ . '/includes/header.php';
?>

<h2>Available Rooms</h2>

<?php if ($errors): ?>
    <ul>
        <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
    <p><a href="index.php#book">&larr; Back to booking</a></p>

<?php else: ?>
    <p>
        <?= e($type['type_name']) ?> &middot;
        <?= e(parse_date($check_in)->format('M j, Y')) ?> (<?= e(CHECK_IN_TIME) ?>) to
        <?= e(parse_date($check_out)->format('M j, Y')) ?> (<?= e(CHECK_OUT_TIME) ?>) &middot;
        <?= (int) $nights ?> night(s) &middot; <?= (int) $guests ?> guest(s)
    </p>

    <?php if (!$rooms): ?>
        <p>Sorry, no <?= e($type['type_name']) ?> is available for those dates. Please try other dates.</p>
        <p><a href="index.php#book">&larr; Change search</a></p>
    <?php else: ?>
        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Rate per night</th>
                    <th>Extra guest fee</th>
                    <th>Total</th>
                    <th>Downpayment</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rooms as $room): ?>
                <?php $price = calculate_price($room, $guests, $nights); ?>
                <tr>
                    <td><?= e($room['room_name']) ?></td>
                    <td><?= peso($price['rate_applied']) ?></td>
                    <td><?= peso($price['extra_total']) ?></td>
                    <td><strong><?= peso($price['total_amount']) ?></strong></td>
                    <td><?= peso($price['downpayment']) ?></td>
                    <td>
                        <a href="booking.php?<?= e(http_build_query([
                            'room_id'   => $room['room_id'],
                            'check_in'  => $check_in,
                            'check_out' => $check_out,
                            'guests'    => $guests,
                        ])) ?>">Book this room</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p><a href="index.php#book">&larr; Change search</a></p>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
