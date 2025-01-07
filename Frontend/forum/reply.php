<div class="reply">
  <h4><?php echo htmlspecialchars($data['name']); ?></h4> <!-- Escape special characters -->
  <p><?php echo htmlspecialchars($data['date']); ?></p> <!-- Escape special characters -->
  <p><?php echo nl2br(htmlspecialchars(preg_replace('/#\w+/', '', $data['comment']))); // Remove tags from replies 
      ?></p> <!-- Remove tags here -->
  <?php $reply_id = $data['id']; ?>
  <button class="reply" onclick="reply(<?php echo $reply_id; ?>, '<?php echo addslashes(htmlspecialchars($data['name'])); ?>');">Reply</button>

  <?php
  unset($datas);
  $datas = mysqli_query($db_connection, "SELECT * FROM tb_data WHERE reply_id = $reply_id");
  if (mysqli_num_rows($datas) > 0) {
    foreach ($datas as $data) {
      require 'reply.php';  // Display nested replies recursively
    }
  }
  ?>
</div>