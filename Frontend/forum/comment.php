<div class="comment">
  <h4><?php echo $data['name']; ?></h4>
  <p><?php echo $data['date']; ?></p>
  <p><?php echo $data['comment']; ?></p> <!-- Show the comment as-is -->

  <!-- Display the tags -->
  <p><strong>Tags:</strong> <?php echo htmlspecialchars($data['tags']); ?></p> <!-- Display tags here -->

  <?php $reply_id = $data['id']; ?>
  <button class="reply" onclick="reply(<?php echo $reply_id; ?>, '<?php echo $data['name']; ?>');">Reply</button>

  <?php
  unset($datas);
  $datas = mysqli_query($db_connection, "SELECT * FROM tb_data WHERE reply_id = $reply_id");
  if (mysqli_num_rows($datas) > 0) {
    foreach ($datas as $data) {
      require 'reply.php';
    }
  }
  ?>
</div>