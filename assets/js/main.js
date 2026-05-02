// Hamburger menu
const hamburger = document.getElementById('hamburger');
const mobileMenu = document.getElementById('mobileMenu');
if (hamburger) {
    hamburger.addEventListener('click', () => {
        mobileMenu.classList.toggle('open');
    });
}

// Star rating hover effect
document.querySelectorAll('.star-rating-input label').forEach(label => {
    label.addEventListener('mouseover', function() {
        let prev = this;
        while (prev) {
            prev.style.color = '#c9a86c';
            prev = prev.previousElementSibling;
        }
    });
});

// Image lightbox
document.querySelectorAll('.review-images img').forEach(img => {
    img.addEventListener('click', function() {
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:pointer;';
        const bigImg = document.createElement('img');
        bigImg.src = this.src;
        bigImg.style.cssText = 'max-width:90vw;max-height:90vh;border-radius:12px;';
        overlay.appendChild(bigImg);
        overlay.addEventListener('click', () => overlay.remove());
        document.body.appendChild(overlay);
    });
});

// Auto hide alerts
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    }, 4000);
});

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function(e) {
        if (!confirm(this.dataset.confirm || 'Bạn có chắc không?')) {
            e.preventDefault();
        }
    });
});