function editUser(id) {
    window.location.href = `edit_user.php?id=${id}`;
}

function deleteUser(id) {
    if (confirm('Are you sure you want to delete this user from the system? This action cannot be reversed.')) {
        window.location.href = `../actions/delete_user.php?id=${id}`;
    }
}