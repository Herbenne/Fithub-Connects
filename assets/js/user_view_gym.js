document.addEventListener('DOMContentLoaded', () => {
    const loader = document.querySelector('.page-loader');
    const images = document.querySelectorAll('img[src]');
    let loadedImages = 0;

    function hideLoader() {
        loader.classList.add('hidden');
        document.body.classList.remove('loading');
        setTimeout(() => {
            loader.style.display = 'none';
        }, 300);
    }

    function checkAllImagesLoaded() {
        loadedImages++;
        if (loadedImages >= images.length) {
            hideLoader();
        }
    }

    // Handle each image load
    images.forEach(img => {
        if (img.complete) {
            checkAllImagesLoaded();
        } else {
            img.addEventListener('load', checkAllImagesLoaded);
            img.addEventListener('error', checkAllImagesLoaded);
        }
    });

    // Fallback if no images or loading takes too long
    setTimeout(hideLoader, 3000);
});

function initializeLightbox() {
    let currentLightbox = null;

    window.showLightbox = function(src) {
        // Remove existing lightbox if any
        if (currentLightbox) {
            currentLightbox.remove();
        }

        const lightbox = document.createElement('div');
        lightbox.className = 'lightbox';
        lightbox.innerHTML = `
            <div class="lightbox-content">
                <img src="${src}" alt="Equipment">
                <button class="close-lightbox">&times;</button>
            </div>
        `;

        document.body.appendChild(lightbox);
        currentLightbox = lightbox;

        lightbox.addEventListener('click', function(e) {
            if (e.target === lightbox || e.target.className === 'close-lightbox') {
                lightbox.remove();
                currentLightbox = null;
            }
        });
    };
}

// Update lightbox function
function showLightbox(src) {
    const lightbox = document.createElement('div');
    lightbox.className = 'lightbox';
    lightbox.innerHTML = `
        <div class="lightbox-content">
            <img src="${src}" alt="Equipment">
            <button class="close-lightbox">&times;</button>
        </div>
    `;

    document.body.appendChild(lightbox);

    const close = () => {
        lightbox.style.opacity = '0';
        setTimeout(() => lightbox.remove(), 300);
    };

    lightbox.querySelector('.close-lightbox').onclick = close;
    lightbox.onclick = e => {
        if (e.target === lightbox) close();
    };

    // Fade in
    requestAnimationFrame(() => {
        lightbox.style.opacity = '1';
    });
}

// Add to user_view_gym.js or inline in the file
function toggleReplyForm(reviewId) {
    const form = document.getElementById(`reply-form-${reviewId}`);
    if (!form) return;

    // Close all other open forms first
    document.querySelectorAll('.comment-form').forEach(otherForm => {
        if (otherForm !== form && otherForm.style.display === 'block') {
            otherForm.style.display = 'none';
        }
    });

    // Toggle the clicked form
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}