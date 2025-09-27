<?php include __DIR__ . '/partials/header.php'; ?>

    <section id="home" class="hero">
        <div class="hero-content">
            <h1>Decor SS xoş gəlmisiniz</h1>
            <p>Gəncə Şəhərində İstənilən zövqə uyğun Dekorlar.</p>
        </div>
    </section>

    <section id="Dekorlar" class="section">
        <h2>Dekorlar</h2>
        <div class="product-grid">
            <?php include __DIR__ . '/partials/products.php'; ?>
        </div>
    </section>

    <section id="Səbət" class="add-to-cart">
        <h2>Səbət</h2>
        <div id="cart-items"></div>
        <p>Cəmi: ₼ <span id="cart-total">0.00</span></p>
    </section>

    <div id="myModal" class="modal">
        <span class="close">&times;</span>
        <img class="modal-content" id="img01">
        <div id="caption"></div>
    </div>

    <section id="Haqqımızda" class="section">
        <h2>Haqqımızda</h2>
        <p>Decor SS 2025-ci ilin 1 Fevral Tarixində Gəncə Şəhərində Fəaliyyətə Başlamışdır.</p>
        <p>İstənilən Zövqə Uyğun Dekorlar İlə Sizin Xidmətinizdədir.</p>
    </section>

    <section id="Əlaqə" class="section">
        <h2>Əlaqə</h2>
        <p class="contact-info" style="margin-top: 5px;"><i class="fa-solid fa-phone"></i> Əlaqə nömrəsi: <a href="tel:+994508811899">+994 50 881 18 99</a></p>
        <p class="contact-info" style="margin-top: 5px;"><i class="fa-solid fa-envelope"></i> Email: <a href="mailto:decorssganja@gmail.com">decorssganja@gmail.com</a></p>
        <h2 style="margin-top: 5px;">Sosial media hesablarımız:</h2>
        <div class="wrapper">
            <a href="https://www.instagram.com/decor_ss_gence/" class="icon" target="_blank"><i class="fa-brands fa-instagram" style="font-size: 28px;"></i></a>
            <a href="https://wa.me/+994508811899" class="icon" target="_blank"><i class="fa-brands fa-whatsapp" style="font-size: 28px;"></i></a>
        </div>
        <form id="contact-form" action="submit-form.php" method="POST">
            <input type="text" name="name" placeholder="Adınız" required>
            <input type="email" name="email" placeholder="Sizin Email" required>
            <textarea name="message" placeholder="Mesaj" required></textarea>
            <button type="submit">Göndər</button>
        </form>
    </section>

    <section id="Rezervasiya" class="section">
        <h2>Rezervasiya</h2>
        <form id="booking-form" action="submit-reservation.php" method="POST">
            <input type="text" name="name" placeholder="Adınız" required>
            <input type="email" name="email" placeholder="Sizin Email" required>
            <input type="date" name="reservation_date" id="reservation_date" min="<?= date('Y-m-d') ?>" required>
            <input type="time" name="reservation_time" id="reservation_time" min="09:00" max="18:00" required>
            <button type="submit">Göndər</button>
        </form>
    </section>

<?php include __DIR__ . '/partials/footer.php'; ?>


