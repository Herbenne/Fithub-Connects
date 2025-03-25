document.addEventListener('DOMContentLoaded', function() {
    // Add fade-in animation for gym cards
    const gymCards = document.querySelectorAll('.gym-card');
    gymCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Add lazy loading for images
    const images = document.querySelectorAll('.gym-image img');
    images.forEach(img => {
        img.loading = 'lazy';
    });

    // Add error handling for images
    images.forEach(img => {
        img.addEventListener('error', function() {
            this.src = '../assets/images/default-gym.jpg';
        });
    });

    const searchInput = document.getElementById('gymSearch');
    const noGymsMessage = document.createElement('div');
    noGymsMessage.className = 'no-gyms';
    noGymsMessage.innerHTML = '<i class="fas fa-dumbbell"></i><p>No gyms found matching your search.</p>';

    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            let hasVisibleCards = false;

            gymCards.forEach(card => {
                const gymName = card.querySelector('h3').textContent.toLowerCase();
                const gymLocation = card.querySelector('.gym-location').textContent.toLowerCase();
                
                if (gymName.includes(searchTerm) || gymLocation.includes(searchTerm)) {
                    card.style.display = '';
                    hasVisibleCards = true;
                } else {
                    card.style.display = 'none';
                }
            });

            // Show/hide no results message
            const gymGrid = document.querySelector('.gym-grid');
            const existingNoGyms = gymGrid.querySelector('.no-gyms');
            
            if (!hasVisibleCards) {
                if (!existingNoGyms) {
                    gymGrid.appendChild(noGymsMessage);
                }
            } else if (existingNoGyms) {
                existingNoGyms.remove();
            }
        });

        // Add clear search functionality
        const searchBox = document.querySelector('.search-box');
        const clearButton = document.createElement('button');
        clearButton.className = 'clear-search';
        clearButton.innerHTML = '<i class="fas fa-times"></i>';
        clearButton.style.display = 'none';
        searchBox.appendChild(clearButton);

        searchInput.addEventListener('input', function() {
            clearButton.style.display = this.value ? 'block' : 'none';
        });

        clearButton.addEventListener('click', function() {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
            this.style.display = 'none';
        });
    }
});