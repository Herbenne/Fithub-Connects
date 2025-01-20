<div class="reply">
  <h4><?php echo htmlspecialchars($replyData['name']); ?></h4>
  <p><?php echo htmlspecialchars($replyData['date']); ?></p>
  <p><?php echo nl2br(htmlspecialchars($replyData['comment'])); ?></p>

  <?php
  $reply_id = $replyData['id'];
  ?>
  <button class="reply" onclick="reply(<?php echo $reply_id; ?>, '<?php echo addslashes(htmlspecialchars($replyData['name'])); ?>');">Reply</button>

  <?php
  // Fetch nested replies for this reply
  $stmt = $db_connection->prepare("SELECT * FROM tb_data WHERE reply_id = ?");
  $stmt->bind_param("i", $reply_id);
  $stmt->execute();
  $nestedReplies = $stmt->get_result();

  // Loop through each nested reply and include `reply.php` recursively
  while ($nestedReply = $nestedReplies->fetch_assoc()) {
    $replyData = $nestedReply; // Pass isolated nested reply data
    require 'reply.php';
  }
  ?>
</div>