<?php
$default_image = __DIR__ . '/../assets/images/default-profile.png';
$assets_dir = __DIR__ . '/../assets/images';

echo "Checking paths...\n";
echo "Default image path: $default_image\n";
echo "Assets directory: $assets_dir\n";

if (!file_exists($assets_dir)) {
    echo "ERROR: Assets directory does not exist!\n";
} else {
    echo "Assets directory exists.\n";
}

if (!file_exists($default_image)) {
    echo "ERROR: Default profile image does not exist!\n";
} else {
    echo "Default profile image exists.\n";
}