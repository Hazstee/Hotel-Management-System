<?php
if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('peso')) {
    function peso($amount): string
    {
        return '₱' . number_format((float) $amount, 2);
    }
}
$page_title = $page_title ?? 'Bella Vista Hotel & Events Place';
$page_scripts = $page_scripts ?? ['assets/js/search.js'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="site-header-inner">
        <a class="brand" href="index.php" aria-label="Bella Vista Hotel &amp; Events Place home">
            <span class="brand-name">BELLA VISTA</span>
            <span class="brand-subtitle">HOTEL &amp; EVENTS PLACE</span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Menu</button>
        <nav class="site-nav" id="site-nav" aria-label="Main navigation">
            <a href="index.php">Home</a>
            <a href="index.php#about">About</a>
            <a href="index.php#rooms">Rooms &amp; Rates</a>
            <a href="index.php#gallery">Gallery</a>
            <a class="mobile-book-link" href="index.php#book">Book Now</a>
        </nav>
        <a class="button header-book-link" href="index.php#book">Book Now</a>
    </div>
</header>
<main class="<?= e($page_main_class ?? 'page-shell') ?>">
