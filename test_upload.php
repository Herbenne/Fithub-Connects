<?php
$upload_dir = __DIR__ . '/uploads/profile_pictures';
echo "Upload directory: " . $upload_dir . "<br>";
echo "Directory exists: " . (file_exists($upload_dir) ? 'Yes' : 'No') . "<br>";
echo "Directory writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "<br>";
echo "PHP upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "PHP post_max_size: " . ini_get('post_max_size') . "<br>";