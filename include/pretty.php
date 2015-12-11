<?php
/**
 * Fix up retarded 2010 coding style.
 */
$files = [];
exec('find -name "*.php"', $files);
//$files = ['./KNNClusteringStats.php'];
foreach($files as $file) {
  print "$file\n";
  $content = file_get_contents($file);
  $content = preg_replace("/\(/", "(", $content);
  $content = preg_replace("/ \)/", ")", $content);
  $content = preg_replace("/\t/", "  ", $content);
  $content = preg_replace("/^\s+$/m", "\n", $content);
  $content = preg_replace("/\n\n\n+/", "\n\n", $content);
  file_put_contents($file, $content);
}
