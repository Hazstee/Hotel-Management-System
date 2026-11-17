<?php
date_default_timezone_set('Asia/Manila');

const DOWNPAYMENT_RATE = 0.50;
const CHECK_IN_TIME    = '2:00 PM';
const CHECK_OUT_TIME   = '12:00 NN';
const REFUND_MIN_DAYS  = 3;


function parse_date(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return ($date && $date->format('Y-m-d') === $value) ? $date : null;
}

function count_nights(string $check_in, string $check_out): int
{
    $in  = parse_date($check_in);
    $out = parse_date($check_out);
    if (!$in || !$out) {
        return 0;
    }
    return (int) $in->diff($out)->format('%r%a');
}


function validate_booking_dates(string $check_in, string $check_out): array
{
    $errors = [];
    $in     = parse_date($check_in);
    $out    = parse_date($check_out);
    $today  = new DateTimeImmutable('today');

    if (!$in || !$out) {
        $errors[] = 'Please select valid check-in and check-out dates.';
        return $errors;
    }
    if ($in < $today) {
        $errors[] = 'Check-in date cannot be in the past.';
    }
    if ($out <= $in) {
        $errors[] = 'Check-out date must be after the check-in date.';
    }
    return $errors;
}

function validate_guests(array $room, int $num_guests): array
{
    $errors = [];
    if ($num_guests < 1) {
        $errors[] = 'At least 1 guest is required.';
    } elseif ($num_guests > (int) $room['max_capacity']) {
        $errors[] = $room['type_name'] . ' allows a maximum of ' . $room['max_capacity'] . ' guests.';
    }
    return $errors;
}

function validate_guest_details(array $guest): array
{
    $errors = [];
    if (trim($guest['first_name'] ?? '') === '') {
        $errors[] = 'First name is required.';
    }
    if (trim($guest['last_name'] ?? '') === '') {
        $errors[] = 'Last name is required.';
    }
    if (!filter_var($guest['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $guest['phone'] ?? '')) {
        $errors[] = 'A valid phone number is required.';
    }
    return $errors;
}


function get_room(PDO $pdo, int $room_id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT rm.room_id, rm.room_name, rm.status,
                rt.type_name, rt.base_capacity, rt.max_capacity,
                rt.rate_per_night, rt.extra_person_fee
         FROM room rm
         JOIN room_type rt ON rt.room_type_id = rm.room_type_id
         WHERE rm.room_id = ?'
    );
    $stmt->execute([$room_id]);
    return $stmt->fetch() ?: null;
}

function is_room_available(PDO $pdo, int $room_id, string $check_in, string $check_out): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM reservation
         WHERE room_id = ?
           AND status IN ('pending','approved')
           AND check_in_date  < ?
           AND check_out_date > ?"
    );
    $stmt->execute([$room_id, $check_out, $check_in]);
    return (int) $stmt->fetchColumn() === 0;
}

function get_room_types(PDO $pdo): array
{
    return $pdo->query(
        'SELECT room_type_id, type_name, base_capacity, max_capacity,
                rate_per_night, extra_person_fee
         FROM room_type
         ORDER BY rate_per_night'
    )->fetchAll();
}

function get_available_rooms(
    PDO $pdo,
    string $check_in,
    string $check_out,
    int $num_guests,
    ?int $room_type_id = null
): array
{
    $stmt = $pdo->prepare(
        "SELECT rm.room_id, rm.room_name,
                rt.room_type_id, rt.type_name, rt.base_capacity, rt.max_capacity,
                rt.rate_per_night, rt.extra_person_fee
         FROM room rm
         JOIN room_type rt ON rt.room_type_id = rm.room_type_id
         WHERE rm.status = 'available'
           AND rt.max_capacity >= ?
           AND (? IS NULL OR rt.room_type_id = ?)
           AND rm.room_id NOT IN (
                SELECT room_id FROM reservation
                WHERE status IN ('pending','approved')
                  AND check_in_date  < ?
                  AND check_out_date > ?
           )
         ORDER BY rt.rate_per_night, rm.room_name"
    );
    $stmt->execute([$num_guests, $room_type_id, $room_type_id, $check_out, $check_in]);
    return $stmt->fetchAll();
}


function calculate_price(array $room, int $num_guests, int $nights): array
{
    $rate         = (float) $room['rate_per_night'];
    $extra_guests = max(0, $num_guests - (int) $room['base_capacity']);
    $base_amount  = $rate * $nights;
    $extra_total  = $extra_guests * (float) $room['extra_person_fee'] * $nights;
    $total        = $base_amount + $extra_total;
    $downpayment  = round($total * DOWNPAYMENT_RATE, 2);

    return [
        'nights'        => $nights,
        'rate_applied'  => round($rate, 2),
        'extra_guests'  => $extra_guests,
        'base_amount'   => round($base_amount, 2),
        'extra_total'   => round($extra_total, 2),
        'total_amount'  => round($total, 2),
        'downpayment'   => $downpayment,
        'balance'       => round($total - $downpayment, 2),
    ];
}


function is_refundable(string $check_in, ?DateTimeImmutable $now = null): bool
{
    $in    = parse_date($check_in);
    $today = $now ? $now->setTime(0, 0) : new DateTimeImmutable('today');
    if (!$in) {
        return false;
    }
    $days_left = (int) $today->diff($in)->format('%r%a');
    return $days_left >= REFUND_MIN_DAYS;
}


function create_reservation(
    PDO $pdo,
    array $guest,
    int $room_id,
    string $check_in,
    string $check_out,
    int $num_guests,
    bool $terms_accepted
): array {
    $errors = [];

    if (!$terms_accepted) {
        $errors[] = 'You must accept the Terms and Conditions.';
    }

    $room = get_room($pdo, $room_id);
    if (!$room) {
        $errors[] = 'Selected room does not exist.';
    } elseif ($room['status'] !== 'available') {
        $errors[] = 'Selected room is currently unavailable.';
    }

    $errors = array_merge(
        $errors,
        validate_booking_dates($check_in, $check_out),
        validate_guest_details($guest)
    );
    if ($room) {
        $errors = array_merge($errors, validate_guests($room, $num_guests));
    }

    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }

    $nights = count_nights($check_in, $check_out);
    $price  = calculate_price($room, $num_guests, $nights);

    try {
        $pdo->beginTransaction();

        $lock = $pdo->prepare('SELECT room_id FROM room WHERE room_id = ? FOR UPDATE');
        $lock->execute([$room_id]);

        if (!is_room_available($pdo, $room_id, $check_in, $check_out)) {
            $pdo->rollBack();
            return [
                'success' => false,
                'errors'  => ['Sorry, this room was just booked for those dates. Please choose another.'],
            ];
        }

        $stmt = $pdo->prepare(
            'INSERT INTO guest (first_name, last_name, email, phone) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            trim($guest['first_name']),
            trim($guest['last_name']),
            trim($guest['email']),
            trim($guest['phone']),
        ]);
        $guest_id = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO reservation
                (guest_id, room_id, check_in_date, check_out_date, num_guests, num_nights,
                 rate_applied, base_amount, extra_person_fee_total, total_amount,
                 downpayment_amount, terms_accepted)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            $guest_id, $room_id, $check_in, $check_out, $num_guests, $nights,
            $price['rate_applied'], $price['base_amount'], $price['extra_total'],
            $price['total_amount'], $price['downpayment'],
        ]);
        $reservation_id = (int) $pdo->lastInsertId();

        $pdo->commit();

        return [
            'success'        => true,
            'reservation_id' => $reservation_id,
            'price'          => $price,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'errors' => ['Something went wrong while saving your booking.']];
    }
}