<?php
/**
 * PHP Info Test
 */
echo "<pre>";
echo "upload_tmp_dir: " . ini_get('upload_tmp_dir') . "\n";
echo "sys_temp_dir: " . ini_get('sys_temp_dir') . "\n";
echo "session.save_path: " . ini_get('session.save_path') . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "</pre>";

echo "<h3>Test Upload</h3>";
echo "<form method='post' enctype='multipart/form-data'>";
echo "<input type='file' name='testfile'>";
echo "<button>Upload</button>";
echo "</form>";

if ($_FILES) {
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";
}
?>
