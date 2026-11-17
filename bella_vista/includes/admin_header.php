<?php $page_title = $page_title ?? 'Admin | Bella Vista'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<header>
    <h1>Bella Vista Admin</h1>
    <nav>
        <a href="dashboard.php">Dashboard</a> |
        <a href="reservations.php">Reservations</a> |
        <a href="rooms.php">Rooms</a> |
        <a href="reports.php">Reports</a> |
        <a href="../index.php" target="_blank">View website</a> |
        <a href="logout.php">Logout (<?= e($_SESSION['admin_name'] ?? '') ?>)</a>
    </nav>
</header>
<main>
