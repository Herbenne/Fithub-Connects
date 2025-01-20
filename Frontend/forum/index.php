<?php
require 'db_connection.php'; // Ensure this is included

// Handle form submission for comment and reply
if (isset($_POST["submit"])) {
  $name = $_POST["name"];
  $comment = $_POST["comment"];
  $date = date('F d Y, h:i:s A');
  $reply_id = $_POST["reply_id"];
  $tags = isset($_POST["tags"]) ? $_POST["tags"] : '';  // Check if tags are set

  // Insert new comment into the database with tags
  $query = "INSERT INTO tb_data (name, comment, date, reply_id, tags) VALUES ('$name', '$comment', '$date', '$reply_id', '$tags')";
  mysqli_query($db_connection, $query); // Use $db_connection here
}

// Handle the search request if the user is searching for a tag
$search_tag = '';
if (isset($_GET['tag'])) {
  $search_tag = $_GET['tag'];
  $datas = mysqli_query($db_connection, "SELECT * FROM tb_data WHERE tags LIKE '%$search_tag%' AND reply_id = 0"); // Use $db_connection here
} else {
  // If no search is performed, fetch all comments
  $datas = mysqli_query($db_connection, "SELECT * FROM tb_data WHERE reply_id = 0"); // Use $db_connection here
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forum</title>
  <link rel="stylesheet" href="styles.css">
</head>

<body>
  <div class="container">
    <!-- Home Button -->
    <form action="../html/index2.php" method="get">
      <button type="submit" class="home-btn">Home</button>
    </form>

    <!-- Search form stays on the same page -->
    <form action="index.php" method="get">
      <input type="text" name="tag" value="<?php echo htmlspecialchars($search_tag); ?>" placeholder="Search by tag">
      <button type="submit">Search</button>
    </form>

    <h3>Results for: <?php echo $search_tag ? htmlspecialchars($search_tag) : 'All comments'; ?></h3>

    <?php
    // Display the comments that match the search tag or all comments if no tag is searched
    foreach ($datas as $data) {
      require 'comment.php';  // Include a separate file for displaying a single comment
    }
    ?>

    <!-- Comment form is hidden when searching for a tag -->
    <div id="comment_form" class="<?php echo $search_tag ? 'hide' : ''; ?>">
      <form action="" method="post">
        <h3 id="title">Leave a Comment</h3>
        <input type="hidden" name="reply_id" id="reply_id">
        <input type="text" name="name" placeholder="Your name" required>
        <textarea name="comment" placeholder="Your comment" required></textarea>
        <input type="text" name="tags" placeholder="Enter tags (comma separated)" required>
        <button class="submit" type="submit" name="submit">Submit</button>
      </form>
    </div>

    <!-- Reply form will appear only when replying to a comment -->
    <div id="reply_form_container" style="display:none;">
      <form action="" method="post">
        <h3 id="reply_title">Reply to Comment</h3>
        <input type="hidden" name="reply_id" id="reply_id_reply">
        <input type="text" name="name" placeholder="Your name" required>
        <textarea name="comment" placeholder="Your reply" required></textarea>
        <button class="submit" type="submit" name="submit">Submit Reply</button>
      </form>
    </div>
  </div>

  <script src="script.js"></script>
</body>

</html>