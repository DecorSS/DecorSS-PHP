<?php include __DIR__ . '/partials/header.php'; ?>

    <section id="Əlaqə" class="section">
        <h2>Əlaqə</h2>
        <p class="contact-info"><i class="fa-solid fa-phone"></i> Əlaqə nömrəsi: <a href="tel:+994508811899">+994 50 881 18 99</a></p>
        <h2>Sosial media hesablarımız:</h2>
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

<?php include __DIR__ . '/partials/footer.php'; ?>


