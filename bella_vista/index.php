<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$room_types = get_room_types($pdo);
$gallery    = $pdo->query('SELECT image_path, caption FROM gallery ORDER BY image_id')->fetchAll();

$page_title   = 'Bella Vista Hotel & Events Place';
$page_scripts = ['assets/js/search.js'];
$page_main_class = 'home-main';
include __DIR__ . '/includes/header.php';
?>

<div class="home-page">
    <section class="home-hero page-shell" aria-labelledby="home-title">
        <div class="hero-intro">
            <div>
                <p class="eyebrow">01 / A RETREAT IN THE PHILIPPINES</p>
                <h1 id="home-title">A quieter kind <span>of luxury.</span></h1>
            </div>
            <div class="hero-copy">
                <p>At the foothills of the mountains, a place to slow down, settle in, and feel at home.</p>
                <a class="button" href="#book">Reserve your stay</a>
            </div>
        </div>
        <img class="hero-image" src="assets/images/hotel-dusk.jpg.jpg" alt="Bella Vista Hotel illuminated at dusk">
        <div class="image-caption-row">
            <span>BELLA VISTA HOTEL &amp; EVENTS PLACE</span>
            <span>Nature outside. Warmth within.</span>
        </div>
    </section>

    <section class="booking-section page-shell" id="book" aria-labelledby="book-title">
        <p class="eyebrow">MAKE ROOM FOR A SLOWER STAY</p>
        <h2 id="book-title">Find your stay</h2>
        <form class="booking-form" action="rooms.php" method="get">
            <div class="booking-field">
                <label for="check_in">Check-in</label>
                <input type="date" id="check_in" name="check_in" min="<?= date('Y-m-d') ?>" required>
                <small>From <?= e(CHECK_IN_TIME) ?></small>
            </div>
            <div class="booking-field">
                <label for="check_out">Check-out</label>
                <input type="date" id="check_out" name="check_out" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                <small>Until <?= e(CHECK_OUT_TIME) ?></small>
            </div>
            <div class="booking-field">
                <label for="room_type">Room type</label>
                <select id="room_type" name="room_type" required>
                    <?php foreach ($room_types as $t): ?>
                        <option value="<?= e($t['room_type_id']) ?>" data-max="<?= e($t['max_capacity']) ?>">
                            <?= e($t['type_name']) ?> (max <?= e($t['max_capacity']) ?> guests)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="booking-field">
                <label for="guests">Guests</label>
                <input type="number" id="guests" name="guests" value="2" min="1" required>
            </div>
            <button type="submit">Check availability</button>
        </form>
    </section>

    <section class="home-section page-shell" id="about" aria-labelledby="about-title">
        <div class="about-grid">
            <img class="about-image" src="assets/images/room-standard.jpg.jpg" alt="Standard room with a bed and sitting area">
            <div class="about-copy">
                <p class="eyebrow">02 / BELLA VISTA</p>
                <p class="eyebrow">About Us</p>
                <h2 id="about-title">Some places invite you to simply be.</h2>
                <p>Bella Vista Hotel &amp; Events Place is a premier eco-chic boutique hotel and events destination nestled at the scenic foothills of the mountains. It offers a tranquil, elegant, and exclusive retreat for pamilya, tourists, and corporate clients looking for a peaceful sanctuary away from the hustle and bustle of the city.</p>
                <p class="serif-line">A stay to unwind. A place to come together.</p>
            </div>
        </div>
    </section>

    <section class="home-section page-shell" id="history" aria-labelledby="history-title">
        <div class="history-grid">
            <div>
                <p class="eyebrow">03 / OUR HISTORY</p>
                <h2 id="history-title">A family's place. Now yours, too.</h2>
            </div>
            <div class="history-copy">
                <p class="eyebrow">ESTABLISHED 2021</p>
                <p>Established in 2021, Bella Vista began as an exclusive family-owned resthouse. Due to growing demand for intimate celebrations and restful getaways, it was expanded and transformed into a boutique hotel and events venue. Today, it is celebrated for its warm Filipino hospitality, picturesque views, and top-tier service for both accommodations and special events.</p>
            </div>
        </div>
    </section>

    <section class="home-section page-shell" id="rooms" aria-labelledby="rooms-title">
        <p class="eyebrow">04 / STAY &amp; GATHER</p>
        <div class="section-heading">
            <h2 id="rooms-title">A space for your kind of stay.</h2>
        </div>
        <div class="room-grid">
            <?php foreach ($room_types as $t): ?>
                <?php
                $type_name = (string) $t['type_name'];
                if (stripos($type_name, 'standard') !== false) {
                    $room_image = 'assets/images/room-standard.jpg.jpg';
                    $image_alt = 'Standard room';
                } elseif (stripos($type_name, 'family') !== false) {
                    $room_image = 'assets/images/room-family.jpg.jpg';
                    $image_alt = 'Family room';
                } else {
                    $room_image = 'assets/images/event-hall.jpg.jpg';
                    $image_alt = 'Event hall';
                }
                ?>
                <article class="room-card">
                    <img src="<?= e($room_image) ?>" alt="<?= e($image_alt) ?>">
                    <div class="room-card-content">
                        <h3><?= e($t['type_name']) ?></h3>
                        <p class="room-meta">
                            <?php if ($t['base_capacity'] == $t['max_capacity']): ?>
                                Up to <?= e($t['max_capacity']) ?> guests
                            <?php else: ?>
                                <?= e($t['base_capacity']) ?> guests included, up to <?= e($t['max_capacity']) ?> max
                            <?php endif; ?>
                        </p>
                        <p class="room-price">₱<?= e(number_format((float) $t['rate_per_night'], 0)) ?> / night</p>
                        <p class="room-extra"><?= $t['extra_person_fee'] > 0 ? e(peso($t['extra_person_fee'])) . ' per extra guest, per night' : 'No extra fee' ?></p>
                        <a class="button" href="index.php?room_type=<?= e($t['room_type_id']) ?>#book">Explore availability</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="home-section page-shell" id="gallery" aria-labelledby="gallery-title">
        <p class="eyebrow">05 / A CLOSER LOOK</p>
        <h2 id="gallery-title">Little moments. Lasting memories.</h2>
        <?php if (!$gallery): ?>
            <p>Photos coming soon.</p>
        <?php else: ?>
            <div class="gallery-grid">
                <?php foreach ($gallery as $img): ?>
                    <figure>
                        <img src="<?= e($img['image_path']) ?>" alt="<?= e($img['caption']) ?>">
                        <figcaption><?= e($img['caption']) ?></figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <p class="gallery-note">Atmosphere imagery is illustrative; final property photography can be added before launch.</p>
    </section>

    <section class="home-section terms-section page-shell" id="terms" aria-labelledby="terms-title">
        <p class="eyebrow">06 / BEFORE YOUR STAY</p>
        <h2 id="terms-title">A few things to know.</h2>
        <?php include __DIR__ . '/includes/terms.php'; ?>
    </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>