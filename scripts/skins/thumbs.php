<?php
/**
 * Step 2/3 - regenerate every thumb.png (idempotent).
 *
 * The wardrobe grid and any static close-up use thumb.png: a TIGHT crop of
 * the best idle frame's own visible bbox (most pixels). Art fills 100% of
 * the thumb so background-size: contain centers it perfectly. The anim
 * strips keep the wider union bbox (attacks must not clip) - only single
 * static frames get the tight crop.
 */

require __DIR__ . '/lib.php';

$cfg = require SKINS_CONFIG;

foreach (['alice', 'boss'] as $t) {
    foreach ($cfg[$t] as $k => $skin) {
        $dir = SKINS_DIR . "/$t/$k";
        $fw = $skin['frame']['w'];
        $fh = $skin['frame']['h'];
        $n = $skin['anims']['idle']['frames'];

        $img = imagecreatefrompng("$dir/idle.png");
        if (!$img) {
            throw new RuntimeException("$dir/idle.png missing");
        }

        $best = null;
        $bestN = -1;
        for ($i = 0; $i < $n; $i++) {
            $f = imagecreatetruecolor($fw, $fh);
            imagealphablending($f, false);
            imagesavealpha($f, true);
            imagefilledrectangle($f, 0, 0, $fw, $fh, imagecolorallocatealpha($f, 0, 0, 0, 127));
            imagecopy($f, $img, 0, 0, $i * $fw, 0, $fw, $fh);
            $count = visiblePx($f, $fw, $fh);
            if ($count > $bestN) {
                if ($best !== null) {
                    imagedestroy($best);
                }
                $best = $f;
                $bestN = $count;
            } else {
                imagedestroy($f);
            }
        }
        imagedestroy($img);

        $minX = $fw;
        $minY = $fh;
        $maxX = -1;
        $maxY = -1;
        for ($y = 0; $y < $fh; $y++) {
            for ($x = 0; $x < $fw; $x++) {
                if (((imagecolorat($best, $x, $y) >> 24) & 0x7F) < VIS_ALPHA) {
                    if ($x < $minX) $minX = $x;
                    if ($x > $maxX) $maxX = $x;
                    if ($y < $minY) $minY = $y;
                    if ($y > $maxY) $maxY = $y;
                }
            }
        }
        $w = $maxX - $minX + 1;
        $h = $maxY - $minY + 1;

        $thumb = imagecreatetruecolor($w, $h);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagefilledrectangle($thumb, 0, 0, $w, $h, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
        imagecopy($thumb, $best, 0, 0, $minX, $minY, $w, $h);
        imagepng($thumb, "$dir/thumb.png");
        imagedestroy($thumb);
        imagedestroy($best);

        echo "$t/$k thumb {$w}x{$h}\n";
    }
}
echo "THUMBS OK - now run pivot.php\n";
