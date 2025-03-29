document.addEventListener('DOMContentLoaded', function() {
    // Remove page loader immediately when DOM is ready
    document.querySelector('.page-loader').classList.add('hidden');

    // Handle images
    function loadImage(img) {
        const src = img.dataset.src;
        if (!src) return;

        img.onload = () => {
            img.classList.add('loaded');
            img.classList.remove('loading');
        };

        img.src = src;
        img.removeAttribute('data-src');
    }

    // Load thumbnail immediately
    const thumbnail = document.querySelector('.gym-thumbnail');
    if (thumbnail) {
        loadImage(thumbnail);
    }

    // Load gallery images with Intersection Observer
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                loadImage(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, {
        rootMargin: '50px 0px',
        threshold: 0.1
    });

    document.querySelectorAll('.gallery-item img[data-src]').forEach(img => {
        imageObserver.observe(img);
    });

    // Lightbox functionality
    function showLightbox(src) {
        const lightbox = document.createElement('div');
        lightbox.className = 'lightbox';
        lightbox.innerHTML = `
            <div class="lightbox-content">
                <img src="${src}" alt="Equipment">
                <button class="close-lightbox">&times;</button>
            </div>
        `;

        lightbox.onclick = e => {
            if (e.target === lightbox || e.target.className === 'close-lightbox') {
                lightbox.remove();
            }
        };

        document.body.appendChild(lightbox);
    }

    // Make showLightbox available globally
    window.showLightbox = showLightbox;

    // Confirm deletions
    document.querySelectorAll('form[onsubmit]').forEach(form => {
        form.onsubmit = function() {
            return confirm('Are you sure you want to delete this item?');
        };
    });
});

// Handle review form toggle
function toggleReplyForm(reviewId) {
    const form = document.getElementById(`reply-form-${reviewId}`);
    if (!form) return;

    const allForms = document.querySelectorAll('.comment-form');
    allForms.forEach(otherForm => {
        if (otherForm !== form && otherForm.style.display === 'block') {
            otherForm.style.display = 'none';
        }
    });

    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}