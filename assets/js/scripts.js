document.addEventListener('DOMContentLoaded', () => {
    const cart = [];
    const cartItemsContainer = document.getElementById('cart-items');
    const cartTotal = document.getElementById('cart-total');
    const menuToggle = document.querySelector('.menu-toggle');
    const navLinks = document.querySelector('.nav-links');

    if (menuToggle && navLinks) {
        menuToggle.addEventListener('click', function() {
            navLinks.classList.toggle('show');
        });
    }

    // Form submitlerini AJAX ile ele al ve inline mesaj göster
    function handleAjaxForm(formSelector, successText) {
        const form = document.querySelector(formSelector);
        if (!form) return;
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            const msgId = formSelector.replace('#', '') + '-status';
            let statusEl = document.getElementById(msgId);
            if (!statusEl) {
                statusEl = document.createElement('div');
                statusEl.id = msgId;
                statusEl.style.marginTop = '10px';
                form.parentNode.insertBefore(statusEl, form.nextSibling);
            }
            statusEl.textContent = '';
            statusEl.style.color = '#1F3A4D';
            if (submitBtn) { submitBtn.disabled = true; }
            try {
                const formData = new FormData(form);
                const res = await fetch(form.action, { method: 'POST', body: formData });
                const data = await res.json().catch(() => ({ ok: false, error: 'Invalid response' }));
                if (res.ok && data && data.ok) {
                    statusEl.textContent = successText;
                    statusEl.style.color = '#2e7d32';
                    form.reset();
                    setTimeout(() => { if (statusEl) statusEl.textContent = ''; }, 2500);
                } else {
                    const err = (data && data.error) ? data.error : ('HTTP ' + res.status);
                    statusEl.textContent = 'Xəta: ' + err;
                    statusEl.style.color = '#b00020';
                    setTimeout(() => { if (statusEl) statusEl.textContent = ''; }, 3000);
                }
            } catch (err) {
                statusEl.textContent = 'Xəta: Şəbəkə bağlantı problemi';
                statusEl.style.color = '#b00020';
                setTimeout(() => { if (statusEl) statusEl.textContent = ''; }, 3000);
            } finally {
                if (submitBtn) { submitBtn.disabled = false; }
            }
        });
    }

    handleAjaxForm('#contact-form', 'Mesajınız uğurla göndərildi.');
    handleAjaxForm('#booking-form', 'Rezervasiya istəyiniz qeydə alındı.');
    
    // Rezervasyon tarih ve saat kontrolleri
    const reservationDateInput = document.getElementById('reservation_date');
    const reservationTimeInput = document.getElementById('reservation_time');
    
    if (reservationDateInput && reservationTimeInput) {
        // Bugünün tarihini minimum olarak ayarla
        const today = new Date();
        const todayString = today.toISOString().split('T')[0];
        reservationDateInput.min = todayString;
        
        // Tarih değiştiğinde saat kontrollerini güncelle
        reservationDateInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            const isToday = selectedDate.toDateString() === today.toDateString();
            
            if (isToday) {
                // Bugün seçilirse, geçmiş saatleri engelle
                const currentHour = today.getHours();
                const currentMinute = today.getMinutes();
                const currentTime = String(currentHour).padStart(2, '0') + ':' + String(currentMinute).padStart(2, '0');
                reservationTimeInput.min = currentTime;
            } else {
                // Gelecek tarih seçilirse, normal saat aralığı
                reservationTimeInput.min = '09:00';
            }
        });
        
        // Form submit edilmeden önce son kontrol
        const bookingForm = document.getElementById('booking-form');
        if (bookingForm) {
            bookingForm.addEventListener('submit', function(e) {
                const selectedDate = new Date(reservationDateInput.value);
                const today = new Date();
                const isToday = selectedDate.toDateString() === today.toDateString();
                
                // Eski tarih kontrolü
                if (selectedDate < today.setHours(0, 0, 0, 0)) {
                    e.preventDefault();
                    alert('Keçmiş tarix seçə bilməzsiniz. Zəhmət olmasa gələcək tarix seçin.');
                    return false;
                }
                
                // Bugün için saat kontrolü
                if (isToday) {
                    const selectedTime = reservationTimeInput.value;
                    const currentTime = new Date();
                    const currentTimeString = String(currentTime.getHours()).padStart(2, '0') + ':' + String(currentTime.getMinutes()).padStart(2, '0');
                    
                    if (selectedTime <= currentTimeString) {
                        e.preventDefault();
                        alert('Keçmiş saat seçə bilməzsiniz. Zəhmət olmasa gələcək saat seçin.');
                        return false;
                    }
                }
                
                // Çalışma saatleri kontrolü
                const selectedTime = reservationTimeInput.value;
                if (selectedTime < '09:00' || selectedTime > '18:00') {
                    e.preventDefault();
                    alert('Rezervasiya saatları: 09:00 - 18:00 arası. Zəhmət olmasa bu saatlar arasında seçin.');
                    return false;
                }
            });
        }
    }

    document.querySelectorAll('.product-item button').forEach(button => {
        button.addEventListener('click', (e) => {
            const productItem = e.target.closest('.product-item');
            const productName = productItem.querySelector('h3').textContent;
            // Fiyattaki sayısal kısmı ayıkla
            const priceText = productItem.querySelector('p').textContent;
            const numericMatch = priceText.replace(/[^0-9.,]/g, '').replace(',', '.');
            const productPrice = parseFloat(numericMatch) || 0;

            const cartItem = cart.find(item => item.name === productName);
            if (cartItem) {
                cartItem.quantity++;
            } else {
                cart.push({ name: productName, price: productPrice, quantity: 1 });
            }
            updateCart();
        });
    });

    function updateCart() {
        if (!cartItemsContainer || !cartTotal) return;
        cartItemsContainer.innerHTML = '';
        let total = 0;

        cart.forEach(item => {
            total += item.price * item.quantity;
            const cartItemElement = document.createElement('div');
            cartItemElement.classList.add('cart-item');
            cartItemElement.innerHTML = `
                <p>${item.name} x${item.quantity} - ₼${(item.price * item.quantity).toFixed(2)}</p>
                <button class="remove-item">Sil</button>
            `;
            cartItemElement.querySelector('.remove-item').addEventListener('click', () => {
                removeCartItem(item.name);
            });
            cartItemsContainer.appendChild(cartItemElement);
        });

        cartTotal.textContent = total.toFixed(2);
    }

    function removeCartItem(name) {
        const itemIndex = cart.findIndex(item => item.name === name);
        if (itemIndex !== -1) {
            cart.splice(itemIndex, 1);
            updateCart();
        }
    }

    // Smooth scroll tüm nav linkleri için
    document.querySelectorAll('.nav-links a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });

    // Hero karşılama animasyonu
    const heroContent = document.querySelector('.hero-content');
    if (heroContent) {
        heroContent.style.opacity = 0;
        setTimeout(() => {
            heroContent.style.transition = 'opacity 2s';
            heroContent.style.opacity = 1;
        }, 500);
    }

    // Modal görsel büyütme
    const modal = document.getElementById('myModal');
    const modalImg = document.getElementById('img01');
    const captionText = document.getElementById('caption');

    document.querySelectorAll('.product-item img, .service-item img').forEach(img => {
        img.addEventListener('click', function() {
            if (!modal || !modalImg || !captionText) return;
            modal.style.display = 'flex';
            setTimeout(() => { modal.classList.add('show'); }, 10);
            modalImg.src = this.src;
            captionText.innerHTML = this.alt;
        });
    });

    const closeBtn = document.querySelector('.close');
    if (closeBtn && modal) {
        closeBtn.addEventListener('click', () => {
            modal.classList.remove('show');
            setTimeout(() => { modal.style.display = 'none'; }, 400);
        });
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.classList.remove('show');
                setTimeout(() => { modal.style.display = 'none'; }, 400);
            }
        });
    }
});


