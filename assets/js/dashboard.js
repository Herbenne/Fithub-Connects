function initializeCharts(months, memberCounts, ratings) {
    // Membership Trends Chart
    const membershipCtx = document.getElementById('membershipTrends').getContext('2d');
    new Chart(membershipCtx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'New Members',
                data: memberCounts,
                borderColor: '#007bff',
                tension: 0.1,
                fill: false
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Monthly New Members (Last 6 Months)'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // Rating Trends Chart
    const ratingCtx = document.getElementById('ratingTrends').getContext('2d');
    new Chart(ratingCtx, {
        type: 'bar',
        data: {
            labels: months,
            datasets: [{
                label: 'Average Rating',
                data: ratings,
                backgroundColor: '#28a745'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Monthly Average Ratings (Last 6 Months)'
                }
            },
            scales: {
                y: {
                    min: 0,
                    max: 5,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

function showApplicationForm() {
    const form = document.getElementById('applicationForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

let currentPage = 0;

document.addEventListener('DOMContentLoaded', function() {
    const pages = document.querySelectorAll('.carousel-page');
    if (pages.length <= 1) {
        document.querySelectorAll('.carousel-btn').forEach(btn => {
            btn.style.display = 'none';
        });
    }
    updateButtonStates();
});

function moveCarousel(direction) {
    const pages = document.querySelectorAll('.carousel-page');
    const maxPage = pages.length - 1;
    
    // Remove active class from current page
    pages[currentPage].classList.remove('active');
    
    // Calculate new page
    currentPage = Math.max(0, Math.min(currentPage + direction, maxPage));
    
    // Add active class to new page
    pages[currentPage].classList.add('active');
    
    // Update button states
    updateButtonStates();
}

function updateButtonStates() {
    const prevBtn = document.querySelector('.carousel-btn.prev');
    const nextBtn = document.querySelector('.carousel-btn.next');
    const pages = document.querySelectorAll('.carousel-page');
    
    if (prevBtn && nextBtn) {
        prevBtn.disabled = currentPage === 0;
        nextBtn.disabled = currentPage === pages.length - 1;
    }
}