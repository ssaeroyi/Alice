<?php
/**
 * Step 3/3 - bake idle-art pivots into config/skins.php (idempotent).
 *
 * The animated display (battle slots, preview modal) is sized to the union
 * FRAME, but the idle pose sits somewhere inside that frame (attack arcs
 * widen it asymmetrically). Flex/anchors center the frame, not the art.
 * Game-dev fix (TexturePacker-style pivot): measure the idle-art bbox vs
 * the frame and store the offset. The client (journal.js setSlotBox /
 * openPreview) scales it into --sox/--soy and shifts the element so the
 * IDLE pose lands dead-center; attacks still play unclipped.
 *
 *   pivot.x = idle art center X - frame center X  (+ = idle sits right)
 *   pivot.y = gap from idle art bottom to frame bottom (+ = idle floats)
 *
 * Rewrites config/skins.php (prices are preserved via var_export).
 */

require __DIR__ . '/lib.php';

$cfg = require SKINS_CONFIG;

foreach (['alice', 'boss'] as $t) {
    foreach ($cfg[$t] as $k => $skin) {
        $fw = $skin['frame']['w'];
        $fh = $skin['frame']['h'];
        $n = $skin['anims']['idle']['frames'];
        $img = imagecreatefrompng(SKINS_DIR . "/$t/$k/idle.png");
        if (!$img) {
            throw new RuntimeException("$t/$k idle missing");
        }

        $minX = $fw;
        $minY = $fh;
        $maxX = -1;
        $maxY = -1;
        for ($i = 0; $i < $n; $i++) {
            for ($y = 0; $y < $fh; $y++) {
                for ($x = 0; $x < $fw; $x++) {
                    if (((imagecolorat($img, $i * $fw + $x, $y) >> 24) & 0x7F) < VIS_ALPHA) {
                        if ($x < $minX) $minX = $x;
                        if ($x > $maxX) $maxX = $x;
                        if ($y < $minY) $minY = $y;
                        if ($y > $maxY) $maxY = $y;
                    }
                }
            }
        }
        imagedestroy($img);

        if ($maxX < 0) {
            $ox = 0;
            $oy = 0;
        } else {
            $ox = (int) round(($minX + $maxX + 1) / 2 - $fw / 2);
            $oy = $fh - 1 - $maxY;
        }
        $cfg[$t][$k]['pivot'] = ['x' => $ox, 'y' => $oy];
        echo "$t/$k pivot x=$ox y=$oy\n";
    }
}

exportConfig($cfg);
echo "PIVOT OK - pipeline complete\n";
