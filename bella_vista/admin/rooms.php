<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

if (!function_exists('bv_no')) {
    function bv_no($id): string
    {
        return 'BV-' . str_pad((string) (int) $id, 5, '0', STR_PAD_LEFT);
    }
}
if (!function_exists('flash')) {
    function flash(string $msg, string $type = 'ok'): void
    {
        $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
    }
}

$today = date('Y-m-d');

/* ---------- ACTION: set room status (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        flash('Session expired. Please try again.', 'error');
        header('Location: rooms.php');
        exit;
    }

    $room_id    = (int) ($_POST['room_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

    $stmt = $pdo->prepare('SELECT room_name, status FROM room WHERE room_id = ?');
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();

    if (!$room) {
        flash('Room not found.', 'error');
    } elseif (!in_array($new_status, ['available', 'maintenance'], true)) {
        flash('Invalid status.', 'error');
    } elseif ($new_status === $room['status']) {
        flash($room['room_name'] . ' is already set to ' . $new_status . '.', 'error');
    } else {
        $blocked = 0;
        if ($new_status === 'maintenance') {
            $c = $pdo->prepare(
                "SELECT COUNT(*) FROM reservation
                 WHERE room_id = ? AND status IN ('pending','approved') AND check_out_date > ?"
            );
            $c->execute([$room_id, $today]);
            $blocked = (int) $c->fetchColumn();
        }

        if ($blocked > 0) {
            flash($room['room_name'] . ' has ' . $blocked . ' pending or approved reservation(s) that have not ended. Cancel or finish them before setting maintenance.', 'error');
        } else {
            $u = $pdo->prepare('UPDATE room SET status = ? WHERE room_id = ?');
            $u->execute([$new_status, $room_id]);
            flash($room['room_name'] . ' is now ' . $new_status . '.');
        }
    }
    header('Location: rooms.php');
    exit;
}

/* ---------- PAGE DATA ---------- */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$rooms = $pdo->query(
    'SELECT rm.room_id, rm.room_name, rm.status, rt.type_name
     FROM room rm JOIN room_type rt ON rt.room_type_id = rm.room_type_id
     ORDER BY rt.room_type_id, rm.room_name'
)->fetchAll();

$cur_stmt = $pdo->prepare(
    "SELECT r.reservation_id, r.status, CONCAT(g.first_name, ' ', g.last_name) AS guest_name,
            r.check_in_date, r.check_out_date
     FROM reservation r JOIN guest g ON g.guest_id = r.guest_id
     WHERE r.room_id = ? AND r.status IN ('pending','approved')
       AND r.check_in_date <= ? AND r.check_out_date > ?
     ORDER BY r.status LIMIT 1"
);
$next_stmt = $pdo->prepare(
    "SELECT r.reservation_id, r.status, r.check_in_date
     FROM reservation r
     WHERE r.room_id = ? AND r.status IN ('pending','approved') AND r.check_in_date > ?
     ORDER BY r.check_in_date LIMIT 1"
);

$rows = [];
foreach ($rooms as $rm) {
    $cur_stmt->execute([$rm['room_id'], $today, $today]);
    $cur = $cur_stmt->fetch() ?: null;
    $next_stmt->execute([$rm['room_id'], $today]);
    $next = $next_stmt->fetch() ?: null;

    if ($rm['status'] === 'maintenance') {
        $label = 'Maintenance';
    } elseif ($cur && $cur['status'] === 'approved') {
        $label = 'Occupied';
    } elseif ($cur) {
        $label = 'Pending hold';
    } else {
        $label = 'Available';
    }
    $rows[] = ['room' => $rm, 'cur' => $cur, 'next' => $next, 'label' => $label];
}

/* ---------- DATE RANGE CHECKER ---------- */
$from = $_GET['from'] ?? $today;
$to   = $_GET['to'] ?? date('Y-m-d', strtotime('+1 day'));
$check_errors = [];
$check_rows   = null;

if (isset($_GET['from']) || isset($_GET['to'])) {
    $f = parse_date($from);
    $t = parse_date($to);
    if (!$f || !$t) {
        $check_errors[] = 'Choose valid dates.';
    } elseif ($t <= $f) {
        $check_errors[] = 'The end date must be after the start date.';
    } else {
        $q = $pdo->prepare(
            "SELECT r.reservation_id, r.status, CONCAT(g.first_name, ' ', g.last_name) AS guest_name,
                    r.check_in_date, r.check_out_date
             FROM reservation r JOIN guest g ON g.guest_id = r.guest_id
             WHERE r.room_id = ? AND r.status IN ('pending','approved')
               AND r.check_in_date < ? AND r.check_out_date > ?
             ORDER BY r.check_in_date"
        );
        $check_rows = [];
        foreach ($rooms as $rm) {
            $q->execute([$rm['room_id'], $to, $from]);
            $check_rows[] = ['room' => $rm, 'bookings' => $q->fetchAll()];
        }
    }
}

$page_title = 'Rooms | Bella Vista Admin';
include __DIR__ . '/../includes/admin_header.php';
?>

<h2>Rooms and Availability</h2>

<?php if ($flash): ?>
    <p><strong><?= $flash['type'] === 'error' ? 'Error: ' : '' ?><?= e($flash['msg']) ?></strong></p>
<?php endif; ?>

<section id="today">
    <h3>Status today (<?= e(date('F j, Y')) ?>)</h3>
    <div class="table-wrap">
    <table border="1" cellpadding="6" cellspacing="0">
        <tr>
            <th>Room</th><th>Type</th><th>Status</th><th>Current stay</th><th>Next booking</th><th>Maintenance</th>
        </tr>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['room']['room_name']) ?></td>
                <td><?= e($r['room']['type_name']) ?></td>
                <td><strong><?= e($r['label']) ?></strong></td>
                <td>
                    <?php if ($r['cur']): ?>
                        <a href="reservations.php?id=<?= (int) $r['cur']['reservation_id'] ?>"><?= e(bv_no($r['cur']['reservation_id'])) ?></a>
                        <?= e($r['cur']['guest_name']) ?>
                        (<?= e($r['cur']['check_in_date']) ?> to <?= e($r['cur']['check_out_date']) ?>)
                    <?php else: ?>
                        None
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($r['next']): ?>
                        <a href="reservations.php?id=<?= (int) $r['next']['reservation_id'] ?>"><?= e(bv_no($r['next']['reservation_id'])) ?></a>
                        on <?= e($r['next']['check_in_date']) ?> (<?= e($r['next']['status']) ?>)
                    <?php else: ?>
                        None
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" action="rooms.php">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="room_id" value="<?= (int) $r['room']['room_id'] ?>">
                        <?php if ($r['room']['status'] === 'maintenance'): ?>
                            <input type="hidden" name="new_status" value="available">
                            <button type="submit">Return to service</button>
                        <?php else: ?>
                            <input type="hidden" name="new_status" value="maintenance">
                            <button type="submit">Set to maintenance</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <p>Occupied = approved stay in progress. Pending hold = a pending reservation covers today. A room in maintenance cannot be booked by guests.</p>
</section>

<section id="checker">
    <h3>Check availability for a date range</h3>
    <form method="get" action="rooms.php">
        <label for="from">From (check-in)</label>
        <input type="date" id="from" name="from" value="<?= e($from) ?>" required>
        <label for="to">To (check-out)</label>
        <input type="date" id="to" name="to" value="<?= e($to) ?>" required>
        <button type="submit">Check</button>
    </form>

    <?php if ($check_errors): ?>
        <ul>
            <?php foreach ($check_errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php elseif ($check_rows !== null): ?>
        <div class="table-wrap">
        <table border="1" cellpadding="6" cellspacing="0">
            <tr><th>Room</th><th>Type</th><th>Result</th></tr>
            <?php foreach ($check_rows as $c): ?>
                <tr>
                    <td><?= e($c['room']['room_name']) ?></td>
                    <td><?= e($c['room']['type_name']) ?></td>
                    <td>
                        <?php if ($c['room']['status'] === 'maintenance'): ?>
                            Maintenance
                        <?php elseif (!$c['bookings']): ?>
                            <strong>Free</strong>
                        <?php else: ?>
                            Booked:
                            <?php foreach ($c['bookings'] as $b): ?>
                                <a href="reservations.php?id=<?= (int) $b['reservation_id'] ?>"><?= e(bv_no($b['reservation_id'])) ?></a>
                                <?= e($b['guest_name']) ?>
                                (<?= e($b['check_in_date']) ?> to <?= e($b['check_out_date']) ?>, <?= e($b['status']) ?>)
                                <br>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
