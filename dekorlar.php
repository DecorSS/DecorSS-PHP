<?php include __DIR__ . '/partials/header.php'; ?>

    <section id="home" class="hero">
        <div class="hero-content">
            <h1>Decor SS Dekorları</h1>
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

<?php include __DIR__ . '/partials/footer.php'; ?>


