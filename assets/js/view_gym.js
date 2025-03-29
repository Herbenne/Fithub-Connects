function toggleReplyForm(reviewId) {
    const form = document.getElementById(`reply-form-${reviewId}`);
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

// Add event listeners when document loads
document.addEventListener('DOMContentLoaded', function() {
    // Confirm deletions
    document.querySelectorAll('form[onsubmit]').forEach(form => {
        form.onsubmit = function() {
            return confirm('Are you sure you want to delete this item?');
        };
    });
});