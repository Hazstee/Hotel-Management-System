<?php
session_start();
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$room_id   = (int) ($_REQUEST['room_id'] ?? 0);
$check_in  = $_REQUEST['check_in'] ?? '';
$check_out = $_REQUEST['check_out'] ?? '';
$guests    = (int) ($_REQUEST['guests'] ?? 0);

$errors = [];
$room   = $room_id ? get_room($pdo, $room_id) : null;

if (!$room) {
    $errors[] = 'Room not found.';
} else {
    $errors = array_merge(
        validate_booking_dates($check_in, $check_out),
        validate_guests($room, $guests)
    );
    if ($room['status'] !== 'available') {
        $errors[] = 'This room is currently unavailable.';
    }
    if (!$errors && !is_room_available($pdo, $room_id, $check_in, $check_out)) {
        $errors[] = 'Sorry, this room is no longer available for those dates.';
    }
}

$form        = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => ''];
$form_errors = [];

if (!$errors && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $unused) {
        $form[$key] = trim($_POST[$key] ?? '');
    }
    $result = create_reservation(
        $pdo, $form, $room_id, $check_in, $check_out, $guests, isset($_POST['terms'])
    );
    if ($result['success']) {
        $_SESSION['last_reservation_id'] = $result['reservation_id'];
        header('Location: confirmation.php');
        exit;
    }
    $form_errors = $result['errors'];
}

$price = null;
if (!$errors) {
    $nights = count_nights($check_in, $check_out);
    $price  = calculate_price($room, $guests, $nights);
}

$page_title = 'Complete Your Booking | Bella Vista';
include __DIR__ . '/includes/header.php';
?>

<h2>Complete Your Booking</h2>

<?php if ($errors): ?>
    <ul>
        <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
    <p><a href="index.php#book">&larr; Back to booking</a></p>

<?php else: ?>

    <h3>Booking Summary</h3>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr><th align="left">Room</th><td><?= e($room['room_name']) ?> (<?= e($room['type_name']) ?>)</td></tr>
        <tr><th align="left">Check-in</th><td><?= e(parse_date($check_in)->format('F j, Y')) ?>, <?= e(CHECK_IN_TIME) ?></td></tr>
        <tr><th align="left">Check-out</th><td><?= e(parse_date($check_out)->format('F j, Y')) ?>, <?= e(CHECK_OUT_TIME) ?></td></tr>
        <tr><th align="left">Nights</th><td><?= (int) $price['nights'] ?></td></tr>
        <tr><th align="left">Guests</th><td><?= (int) $guests ?></td></tr>
        <tr><th align="left">Rate per night</th><td><?= peso($price['rate_applied']) ?></td></tr>
        <tr><th align="left">Room charge</th><td><?= peso($price['base_amount']) ?></td></tr>
        <tr>
            <th align="left">Extra guest fee</th>
            <td><?= peso($price['extra_total']) ?> (<?= (int) $price['extra_guests'] ?> extra guest(s))</td>
        </tr>
        <tr><th align="left">Total</th><td><strong><?= peso($price['total_amount']) ?></strong></td></tr>
        <tr><th align="left">Downpayment (<?= (int) (DOWNPAYMENT_RATE * 100) ?>%)</th><td><?= peso($price['downpayment']) ?></td></tr>
        <tr><th align="left">Balance due at check-in</th><td><?= peso($price['balance']) ?></td></tr>
    </table>

    <h3>Guest Details</h3>

    <?php if ($form_errors): ?>
        <ul>
            <?php foreach ($form_errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="booking.php">
        <input type="hidden" name="room_id" value="<?= (int) $room_id ?>">
        <input type="hidden" name="check_in" value="<?= e($check_in) ?>">
        <input type="hidden" name="check_out" value="<?= e($check_out) ?>">
        <input type="hidden" name="guests" value="<?= (int) $guests ?>">

        <p>
            <label for="first_name">First name</label><br>
            <input type="text" id="first_name" name="first_name" maxlength="50" value="<?= e($form['first_name']) ?>" required>
        </p>
        <p>
            <label for="last_name">Last name</label><br>
            <input type="text" id="last_name" name="last_name" maxlength="50" value="<?= e($form['last_name']) ?>" required>
        </p>
        <p>
            <label for="email">Email</label><br>
            <input type="email" id="email" name="email" maxlength="100" value="<?= e($form['email']) ?>" required>
        </p>
        <p>
            <label for="phone">Phone</label><br>
            <input type="tel" id="phone" name="phone" maxlength="20" value="<?= e($form['phone']) ?>" required>
        </p>

        <details>
            <summary>Read the Terms &amp; Conditions</summary>
            <?php include __DIR__ . '/includes/terms.php'; ?>
        </details>

        <p>
            <input type="checkbox" id="terms" name="terms" value="1" required>
            <label for="terms">I have read and agree to the Terms &amp; Conditions.</label>
        </p>

        <p><button type="submit">Confirm Reservation</button></p>
    </form>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
