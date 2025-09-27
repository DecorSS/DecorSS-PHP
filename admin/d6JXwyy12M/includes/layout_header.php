<?php
// Not: Sayfa dosyaları zaten config ve auth dosyalarını include ediyor. Burada tekrar include etmiyoruz.
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="admin-shell">
    <aside class="sidebar">
        <div class="brand">Decor SS Admin</div>
        <nav>
            <a href="dashboard.php" class="<?=basename($_SERVER['PHP_SELF'])==='dashboard.php'?'active':''?>">İdarəetmə Paneli</a>
            <a href="products.php" class="<?=basename($_SERVER['PHP_SELF'])==='products.php'?'active':''?>">Məhsullar</a>
            <a href="product_form.php" class="<?=basename($_SERVER['PHP_SELF'])==='product_form.php'?'active':''?>">Yeni Məhsul</a>
            <a href="messages.php" class="<?=basename($_SERVER['PHP_SELF'])==='messages.php'?'active':''?>">Mesajlar</a>
            <a href="reservations.php" class="<?=basename($_SERVER['PHP_SELF'])==='reservations.php'?'active':''?>">Rezervasiyalar</a>
            <a href="users.php" class="<?=basename($_SERVER['PHP_SELF'])==='users.php'?'active':''?>">İstifadəçilər</a>
            <a href="unused_images.php" class="<?=basename($_SERVER['PHP_SELF'])==='unused_images.php'?'active':''?>">İstifadə Olunmayan Görseller</a>
            <a href="logout.php">Çıxış</a>
        </nav>
    </aside>
    <div class="content">
        <div class="topbar">
            <button class="menu-btn" onclick="document.querySelector('.sidebar').classList.toggle('open')">☰</button>
            <div class="title">Admin Panel</div>
            <div></div>
        </div>
        <div class="main">

