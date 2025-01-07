function reply(id, name) {
    // Show the reply form when the user clicks "Reply"
    document.getElementById('reply_form_container').style.display = "block";
    document.getElementById('reply_title').innerHTML = "Reply to " + name; // Dynamically update the title
    document.getElementById('reply_id_reply').value = id;
}
