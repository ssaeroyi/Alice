<?php
/**
 * Step 1/3 - normalize every skin's sheets (idempotent).
 *
 * For each skin listed in config/skins.php:
 *   - slice every anim strip, compute the union art bbox across ALL anims
 *     (so attacks never clip and poses don't jump between animations)
 *   - crop every frame to that bbox (art fills 100% of the frame)
 *   - horizontally flip any skin listed in $FLIP (must face RIGHT like
 *     every other boss; CSS mirrors bosses for the arena)
 *   - trim fully-empty trailing frames on hold anims (death fade-to-nothing)
 *   - rewrite config: new frame size, trimmed frame counts, uniform boxes
 *     (alice arena 120 tall / mascot 200 / boss 190, aspect width, caps
 *     150/220/240), feet=0, pivot reset to 0 (re-bake with pivot.php)
 *
 * NEW SKIN: add a stub config entry first (name, price=null, the RAW frame
 * size, raw anim frame counts, a thumb line) pointing at the raw strips in
 * public/assets/skins/<type>/<key>/, then run this, thumbs.php, pivot.php.
 */

require __DIR__ . '/lib.php';

// Skins whose source sheets natively face LEFT. Add keys here before running,
// run normalize ONCE, then remove them - $FLIP is one-shot, NOT idempotent.
$FLIP = [
    // 'boss/mushroom',  // flipped 2026-10-22 (cave pack faces left); one-shot, removed after run
];

$cfg = require SKINS_CONFIG;

foreach (['alice', 'boss'] as $type) {
    foreach ($cfg[$type] as $key => $skin) {
        $dir = SKINS_DIR . "/$type/$key";
        $flip = in_array("$type/$key", $FLIP, true);

        $loaded = [];
        $bbox = null;
        foreach ($skin['anims'] as $name => $a) {
            [$frames, $fw, $h] = loadStrip("$dir/{$name}.png", $a['frames']);
            $loaded[$name] = [$frames, $fw, $h];
            $b = unionBbox($frames, $fw, $h);
            if ($b === null) {
                continue;
            }
            if ($bbox === null) {
                $bbox = $b;
            } else {
                $bbox = [
                    min($bbox[0], $b[0]),
                    min($bbox[1], $b[1]),
                    max($bbox[0] + $bbox[2], $b[0] + $b[2]) - min($bbox[0], $b[0]),
                    max($bbox[1] + $bbox[3], $b[1] + $b[3]) - min($bbox[1], $b[1]),
                ];
            }
        }
        if ($bbox === null) {
            throw new RuntimeException("$type/$key: no visible pixels");
        }

        $frameW = $bbox[2];
        $frameH = $bbox[3];

        foreach ($skin['anims'] as $name => $a) {
            [$frames] = $loaded[$name];
            foreach ($frames as $i => $f) {
                $frames[$i] = cropFrame($f, $bbox);
                if ($flip) {
                    $frames[$i] = flipFrame($frames[$i], $frameW, $frameH);
                }
            }
            if (!empty($a['hold'])) {
                while (count($frames) > 1) {
                    if (visiblePx($frames[count($frames) - 1], $frameW, $frameH) <= EMPTY_PX) {
                        imagedestroy(array_pop($frames));
                    } else {
                        break;
                    }
                }
            }
            saveStrip($frames, "$dir/{$name}.png", $frameW, $frameH);
            $cfg[$type][$key]['anims'][$name]['frames'] = count($frames);
        }

        $cfg[$type][$key]['frame'] = ['w' => $frameW, 'h' => $frameH];
        if ($type === 'alice') {
            $cfg[$type][$key]['box'] = boxFor($frameW, $frameH, 120, 150);
            $cfg[$type][$key]['mascot'] = array_merge(boxFor($frameW, $frameH, 200, 220), ['feet' => 0]);
            unset($cfg[$type][$key]['mascot']['feet']);
        } else {
            $targetH = $skin['box_h'] ?? 190;
            $cfg[$type][$key]['box'] = boxFor($frameW, $frameH, $targetH, 240);
        }
        $cfg[$type][$key]['pivot'] = ['x' => 0, 'y' => 0];

        echo "$type/$key: frame {$frameW}x{$frameH}" . ($flip ? ' FLIPPED' : '') . "\n";
    }
}

exportConfig($cfg);
echo "NORMALIZE OK - now run thumbs.php then pivot.php\n";
