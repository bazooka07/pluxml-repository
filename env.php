<?php
header('Content-Type: text/plain; charset=utf-8');
echo 'PHP version '.phpversion()."\n\n";
foreach(explode("\n", trim('
output_buffering
post_max_size
upload_max_filesize
max_file_uploads
')) as $varname) {
	printf("%-20s : %s\n", $varname, ini_get($varname));
}
echo "\n";
?>
