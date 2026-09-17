--TEST--
Bug #81145 (copy() and stream_copy_to_stream() fail for +4GB files)
--SKIPIF--
<?php
if (getenv("SKIP_SLOW_TESTS")) die("skip slow test");
if (PHP_INT_SIZE !== 8) die("skip this test is for 64bit platforms only");
if (disk_free_space(__DIR__) < 0x100100000) die("skip insufficient disk space");
if (PHP_OS_FAMILY !== "Windows") {
    $src = __DIR__ . "/bug81145_src.bin";
    define('SIZE_4G', 0x100000000);
    exec("fallocate -l " . (SIZE_4G-0x100) . " " . escapeshellarg($src), $output, $status);
    @unlink(__DIR__ . "/bug81145_src.bin");
    if ($status !== 0) die("skip fallocate() not supported");
}
?>
--CONFLICTS--
all
--FILE--
<?php
$src = __DIR__ . "/bug81145_src.bin";
$dst = __DIR__ . "/bug81145_dst.bin";
define('SIZE_4G', 0x100000000);

// A file that ends 0x100 bytes past the 4 GiB boundary, with known content
// across the boundary. Nothing below the boundary is ever written or read.
if (PHP_OS_FAMILY !== "Windows") {
    exec("fallocate -l " . (SIZE_4G-0x100) . " " . escapeshellarg($src));
} else {
    exec("fsutil file createnew " . escapeshellarg($src) . " 0");
    exec("fsutil sparse setflag " . escapeshellarg($src));
    exec("fsutil file seteof " . escapeshellarg($src) . " " . (SIZE_4G-0x100));
}
$tail = "";
for ($i = 0; $i < 0x200; $i++) {
    $tail .= chr(1 + $i % 255);
}
$fp = fopen($src, "ab");
fwrite($fp, $tail);
fclose($fp);
$expected = substr($tail, 0x100);

// fpassthru() maps the file from the current position where it can, so
// seeking to exactly 4 GiB asks for a mapping whose offset has a non-zero
// high word.
$fp = fopen($src, "rb");
fseek($fp, SIZE_4G, SEEK_SET);
ob_start();
fpassthru($fp);
$mapped = ob_get_clean();
fclose($fp);
echo ($mapped === $expected ? "Mapped copy identical" : "Mapped copy failed"), "\n";

// The same offset through the fd-level copy used by copy() and
// stream_copy_to_stream().
$in = fopen($src, "rb");
$out = fopen($dst, "wb");
$copied = stream_copy_to_stream($in, $out, 0x100, SIZE_4G);
fclose($in);
fclose($out);
echo ($copied === 0x100 && file_get_contents($dst) === $expected ? "Stream copy identical" : "Stream copy failed"), "\n";
?>
--CLEAN--
<?php
@unlink(__DIR__ . "/bug81145_src.bin");
@unlink(__DIR__ . "/bug81145_dst.bin");
?>
--EXPECT--
Mapped copy identical
Stream copy identical
