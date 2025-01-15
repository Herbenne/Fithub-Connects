<div class="comment">
  <h4><?php echo htmlspecialchars($data['name']); ?></h4>
  <p><?php echo htmlspecialchars($data['date']); ?></p>
  <p><?php echo nl2br(htmlspecialchars($data['comment'])); ?></p>
  <p><strong>Tags:</strong> <?php echo htmlspecialchars($data['tags']); ?></p>

  <?php
  $reply_id = $data['id'];
  ?>
  <button class="reply" onclick="reply(<?php echo $reply_id; ?>, '<?php echo addslashes(htmlspecialchars($data['name'])); ?>');">Reply</button>

  <?php
  // Fetch replies for this comment
  $stmt = $db_connection->prepare("SELECT * FROM tb_data WHERE reply_id = ?");
  $stmt->bind_param("i", $reply_id);
  $stmt->execute();
  $replies = $stmt->get_result();

  // Loop through each reply and include `reply.php` with isolated data
  while ($reply = $replies->fetch_assoc()) {
    $replyData = $reply; // Pass isolated reply data
    require 'reply.php';
  }
  ?>
</div>