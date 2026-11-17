<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

const PAYMENT_TYPES = ['downpayment', 'balance', 'fine', 'damage', 'refund'];
const STATUSES      = ['pending', 'approved', 'cancelled', 'completed'];

function bv_no($id): string
{
    return 'BV-' . str_pad((string) (int) $id, 5, '0', STR_PAD_LEFT);
}

function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function load_reservation(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT r.*, CONCAT(g.first_name, ' ', g.last_name) AS guest_name, g.email, g.phone,
                rm.room_name, rt.type_name, a.full_name AS approved_by_name
         FROM reservation r
         JOIN guest g      ON g.guest_id = r.guest_id
         JOIN room rm      ON rm.room_id = r.room_id
         JOIN room_type rt ON rt.room_type_id = rm.room_type_id
         LEFT JOIN admin a ON a.admin_id = r.approved_by
         WHERE r.reservation_id = ?"
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function payment_totals(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare(
        'SELECT payment_type, SUM(amount) FROM payment WHERE reservation_id = ? GROUP BY payment_type'
    );
    $stmt->execute([$id]);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $t = [];
    foreach (PAYMENT_TYPES as $type) {
        $t[$type] = (float) ($rows[$type] ?? 0);
    }
    $t['paid'] = $t['downpayment'] + $t['balance'];
    $t['net']  = $t['paid'] - $t['refund'];
    return $t;
}

/* ---------- ACTIONS (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if (!csrf_check()) {
        flash('Session expired. Please try again.', 'error');
        header('Location: reservations.php');
        exit;
    }

    $res = load_reservation($pdo, $id);
    if (!$res) {
        flash('Reservation not found.', 'error');
        header('Location: reservations.php');
        exit;
    }

    $t         = payment_totals($pdo, $id);
    $total     = (float) $res['total_amount'];
    $remaining = $total - $t['paid'];

    if ($action === 'payment') {
        $type   = $_POST['payment_type'] ?? '';
        $amount = round((float) str_replace(',', '', $_POST['amount'] ?? '0'), 2);

        if (!in_array($type, PAYMENT_TYPES, true)) {
            flash('Choose a valid payment type.', 'error');
        } elseif ($amount <= 0) {
            flash('Enter an amount greater than zero.', 'error');
        } elseif ($res['status'] === 'cancelled' && $type !== 'refund') {
            flash('A cancelled reservation can only receive a refund entry.', 'error');
        } elseif (in_array($type, ['downpayment', 'balance'], true) && $amount > $remaining + 0.005) {
            flash('Amount is more than the remaining balance of ' . peso($remaining) . '.', 'error');
        } elseif ($type === 'downpayment' && $t['downpayment'] > 0) {
            flash('The downpayment was already recorded. Use "balance" for further payments.', 'error');
        } elseif ($type === 'balance' && $t['downpayment'] <= 0) {
            flash('Record the downpayment first.', 'error');
        } elseif ($type === 'refund' && $res['status'] !== 'cancelled') {
            flash('Refunds can only be recorded for cancelled reservations.', 'error');
        } elseif ($type === 'refund' && $amount > $t['net'] + 0.005) {
            flash('Refund is more than the amount paid (' . peso($t['net']) . ').', 'error');
        } elseif (in_array($type, ['fine', 'damage'], true) && !in_array($res['status'], ['approved', 'completed'], true)) {
            flash('Fines and damage charges apply only to approved or completed reservations.', 'error');
        } else {
            $stmt = $pdo->prepare('INSERT INTO payment (reservation_id, amount, payment_type) VALUES (?, ?, ?)');
            $stmt->execute([$id, $amount, $type]);
            flash(ucfirst($type) . ' of ' . peso($amount) . ' recorded.');
        }

    } elseif ($action === 'approve') {
        if ($res['status'] !== 'pending') {
            flash('Only pending reservations can be approved.', 'error');
        } elseif ($t['paid'] < (float) $res['downpayment_amount'] - 0.005) {
            flash('Record the downpayment of ' . peso($res['downpayment_amount']) . ' before approving.', 'error');
        } else {
            $stmt = $pdo->prepare("UPDATE reservation SET status = 'approved', approved_by = ? WHERE reservation_id = ?");
            $stmt->execute([$_SESSION['admin_id'], $id]);
            flash('Reservation approved.');
        }

    } elseif ($action === 'cancel') {
        if (!in_array($res['status'], ['pending', 'approved'], true)) {
            flash('This reservation cannot be cancelled.', 'error');
        } else {
            $stmt = $pdo->prepare("UPDATE reservation SET status = 'cancelled', cancelled_at = ? WHERE reservation_id = ?");
            $stmt->execute([date('Y-m-d H:i:s'), $id]);
            if (is_refundable($res['check_in_date'])) {
                flash('Reservation cancelled. It is refundable: record a refund if money was paid.');
            } else {
                flash('Reservation cancelled. It is NON-REFUNDABLE (less than ' . REFUND_MIN_DAYS . ' days before check-in).');
            }
        }

    } elseif ($action === 'complete') {
        if ($res['status'] !== 'approved') {
            flash('Only approved reservations can be completed.', 'error');
        } elseif (date('Y-m-d') < $res['check_out_date']) {
            flash('A reservation can be completed on or after its check-out date.', 'error');
        } elseif ($remaining > 0.005) {
            flash('The balance of ' . peso($remaining) . ' must be fully paid first.', 'error');
        } else {
            $stmt = $pdo->prepare("UPDATE reservation SET status = 'completed' WHERE reservation_id = ?");
            $stmt->execute([$id]);
            flash('Reservation marked as completed.');
        }
    }

    header('Location: reservations.php?id=' . $id);
    exit;
}

/* ---------- PAGE (GET) ---------- */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$id  = (int) ($_GET['id'] ?? 0);
$res = $id ? load_reservation($pdo, $id) : null;

$page_title = 'Reservations | Bella Vista Admin';
include __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($flash): ?>
    <p><strong><?= $flash['type'] === 'error' ? 'Error: ' : '' ?><?= e($flash['msg']) ?></strong></p>
<?php endif; ?>

<?php if ($id && !$res): ?>
    <h2>Reservation not found</h2>
    <p><a href="reservations.php">&larr; Back to reservations</a></p>

<?php elseif ($res): ?>
    <?php
    $t          = payment_totals($pdo, $id);
    $total      = (float) $res['total_amount'];
    $remaining  = $total - $t['paid'];
    $payments   = $pdo->prepare('SELECT payment_type, amount, payment_date FROM payment WHERE reservation_id = ? ORDER BY payment_date, payment_id');
    $payments->execute([$id]);
    $payments   = $payments->fetchAll();
    $status     = $res['status'];
    $can_approve = $status === 'pending' && $t['paid'] >= (float) $res['downpayment_amount'] - 0.005;
    $can_cancel  = in_array($status, ['pending', 'approved'], true);
    $can_complete = $status === 'approved' && date('Y-m-d') >= $res['check_out_date'] && $remaining <= 0.005;
    ?>
    <h2>Reservation <?= e(bv_no($id)) ?></h2>
    <p><a href="reservations.php">&larr; Back to reservations</a></p>

    <h3>Details</h3>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr><th align="left">Status</th><td><strong><?= e(ucfirst($status)) ?></strong></td></tr>
        <tr><th align="left">Guest</th><td><?= e($res['guest_name']) ?></td></tr>
        <tr><th align="left">Email</th><td><?= e($res['email']) ?></td></tr>
        <tr><th align="left">Phone</th><td><?= e($res['phone']) ?></td></tr>
        <tr><th align="left">Room</th><td><?= e($res['room_name']) ?> (<?= e($res['type_name']) ?>)</td></tr>
        <tr><th align="left">Check-in</th><td><?= e($res['check_in_date']) ?>, <?= e(CHECK_IN_TIME) ?></td></tr>
        <tr><th align="left">Check-out</th><td><?= e($res['check_out_date']) ?>, <?= e(CHECK_OUT_TIME) ?></td></tr>
        <tr><th align="left">Guests</th><td><?= (int) $res['num_guests'] ?></td></tr>
        <tr><th align="left">Nights</th><td><?= (int) $res['num_nights'] ?></td></tr>
        <tr><th align="left">Terms accepted</th><td><?= $res['terms_accepted'] ? 'Yes' : 'No' ?></td></tr>
        <tr><th align="left">Booked on</th><td><?= e($res['created_at']) ?></td></tr>
        <?php if ($res['approved_by_name']): ?>
            <tr><th align="left">Approved by</th><td><?= e($res['approved_by_name']) ?></td></tr>
        <?php endif; ?>
        <?php if ($res['cancelled_at']): ?>
            <tr><th align="left">Cancelled at</th><td><?= e($res['cancelled_at']) ?></td></tr>
        <?php endif; ?>
    </table>

    <h3>Price and payments</h3>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr><th align="left">Rate per night</th><td><?= peso($res['rate_applied']) ?></td></tr>
        <tr><th align="left">Room charge</th><td><?= peso($res['base_amount']) ?></td></tr>
        <tr><th align="left">Extra guest fee</th><td><?= peso($res['extra_person_fee_total']) ?></td></tr>
        <tr><th align="left">Total</th><td><strong><?= peso($total) ?></strong></td></tr>
        <tr><th align="left">Downpayment required</th><td><?= peso($res['downpayment_amount']) ?></td></tr>
        <tr><th align="left">Paid so far</th><td><?= peso($t['paid']) ?></td></tr>
        <tr><th align="left">Remaining balance</th><td><?= peso(max(0, $remaining)) ?></td></tr>
        <tr><th align="left">Fines and damage charges</th><td><?= peso($t['fine'] + $t['damage']) ?></td></tr>
        <tr><th align="left">Refunded</th><td><?= peso($t['refund']) ?></td></tr>
    </table>

    <h3>Payment history</h3>
    <?php if (!$payments): ?>
        <p>No payments recorded yet.</p>
    <?php else: ?>
        <table border="1" cellpadding="6" cellspacing="0">
            <tr><th>Date</th><th>Type</th><th>Amount</th></tr>
            <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?= e($p['payment_date']) ?></td>
                    <td><?= e(ucfirst($p['payment_type'])) ?></td>
                    <td><?= $p['payment_type'] === 'refund' ? '-' : '' ?><?= peso($p['amount']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h3>Record a payment</h3>
    <form method="post" action="reservations.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <input type="hidden" name="action" value="payment">
        <p>
            <label for="payment_type">Type</label><br>
            <select id="payment_type" name="payment_type" required>
                <option value="downpayment">Downpayment</option>
                <option value="balance">Balance</option>
                <option value="fine">Fine (e.g. smoking)</option>
                <option value="damage">Damage charge</option>
                <option value="refund">Refund</option>
            </select>
        </p>
        <p>
            <label for="amount">Amount (PHP)</label><br>
            <input type="number" id="amount" name="amount" min="0.01" step="0.01" required>
        </p>
        <p><button type="submit">Record payment</button></p>
    </form>

    <h3>Actions</h3>

    <h4>Approve</h4>
    <?php if ($can_approve): ?>
        <form method="post" action="reservations.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="action" value="approve">
            <button type="submit">Approve reservation</button>
        </form>
    <?php elseif ($status === 'pending'): ?>
        <p>Record the downpayment of <?= peso($res['downpayment_amount']) ?> to enable approval.</p>
    <?php else: ?>
        <p>Not available (status: <?= e($status) ?>).</p>
    <?php endif; ?>

    <h4>Cancel</h4>
    <?php if ($can_cancel): ?>
        <p>
            <?php if (is_refundable($res['check_in_date'])): ?>
                Cancelling now is <strong>refundable</strong> (at least <?= (int) REFUND_MIN_DAYS ?> days before check-in).
            <?php else: ?>
                Cancelling now is <strong>non-refundable</strong> (less than <?= (int) REFUND_MIN_DAYS ?> days before check-in).
            <?php endif; ?>
        </p>
        <form method="post" action="reservations.php" onsubmit="return confirm('Cancel this reservation?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="action" value="cancel">
            <button type="submit">Cancel reservation</button>
        </form>
    <?php else: ?>
        <p>Not available (status: <?= e($status) ?>).</p>
    <?php endif; ?>

    <h4>Mark as completed</h4>
    <?php if ($can_complete): ?>
        <form method="post" action="reservations.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="action" value="complete">
            <button type="submit">Mark as completed</button>
        </form>
    <?php elseif ($status === 'approved'): ?>
        <p>Available on or after the check-out date, once the balance is fully paid.</p>
    <?php else: ?>
        <p>Not available (status: <?= e($status) ?>).</p>
    <?php endif; ?>

<?php else: ?>
    <?php
    $status_filter = $_GET['status'] ?? 'all';
    $q             = trim($_GET['q'] ?? '');
    $where  = [];
    $params = [];

    if (in_array($status_filter, STATUSES, true)) {
        $where[]  = 'status = ?';
        $params[] = $status_filter;
    }
    if ($q !== '') {
        $like  = '%' . $q . '%';
        $cond  = '(guest_name LIKE ? OR email LIKE ? OR room_name LIKE ?';
        $params = array_merge($params, [$like, $like, $like]);
        $digits = preg_replace('/\D/', '', $q);
        if ($digits !== '') {
            $cond    .= ' OR reservation_id = ?';
            $params[] = (int) $digits;
        }
        $cond   .= ')';
        $where[] = $cond;
    }

    $sql = 'SELECT * FROM v_reservation_details'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY created_at DESC LIMIT 200';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $list = $stmt->fetchAll();
    ?>
    <h2>Reservations</h2>

    <form method="get" action="reservations.php">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="all">All</option>
            <?php foreach (STATUSES as $s): ?>
                <option value="<?= e($s) ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="q">Search</label>
        <input type="text" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, email, room or BV-00001">
        <button type="submit">Filter</button>
        <a href="reservations.php">Reset</a>
    </form>

    <?php if (!$list): ?>
        <p>No reservations found.</p>
    <?php else: ?>
        <table border="1" cellpadding="6" cellspacing="0">
            <tr>
                <th>No.</th><th>Guest</th><th>Room</th><th>Check-in</th><th>Check-out</th>
                <th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th></th>
            </tr>
            <?php foreach ($list as $r): ?>
                <tr>
                    <td><?= e(bv_no($r['reservation_id'])) ?></td>
                    <td><?= e($r['guest_name']) ?></td>
                    <td><?= e($r['room_name']) ?></td>
                    <td><?= e($r['check_in_date']) ?></td>
                    <td><?= e($r['check_out_date']) ?></td>
                    <td><?= peso($r['total_amount']) ?></td>
                    <td><?= peso($r['total_paid']) ?></td>
                    <td><?= peso($r['balance']) ?></td>
                    <td><?= e(ucfirst($r['status'])) ?></td>
                    <td><a href="reservations.php?id=<?= (int) $r['reservation_id'] ?>">Manage</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
