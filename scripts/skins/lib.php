<?php
/**
 * Shared helpers for the skin pipeline (guidelines/SKINS_PIPELINE.md).
 * Standalone PHP + GD - no Laravel bootstrap needed. Run from anywhere:
 *   php scripts/skins/normalize.php
 *   php scripts/skins/thumbs.php
 *   php scripts/skins/pivot.php
 */

define('SKINS_ROOT', dirname(__DIR__, 2));
define('SKINS_DIR', SKINS_ROOT . '/public/assets/skins');
define('SKINS_CONFIG', SKINS_ROOT . '/config/skins.php');

const VIS_ALPHA = 120; // GD alpha: visible when < this (0 opaque, 127 clear)
const EMPTY_PX = 10;   // frame with <= this many visible px counts as empty

/** Slice a horizontal strip into per-frame canvases. */
function loadStrip(string $path, int $frames): array
{
    $img = @imagecreatefrompng($path);
    if (!$img) {
        throw new RuntimeException("cannot load $path");
    }
    $w = imagesx($img);
    $h = imagesy($img);
    if ($frames < 1 || $w % $frames !== 0) {
        throw new RuntimeException("$path: {$w}px wide not divisible by $frames frames");
    }
    $fw = intdiv($w, $frames);
    $out = [];
    for ($i = 0; $i < $frames; $i++) {
        $f = imagecreatetruecolor($fw, $h);
        imagealphablending($f, false);
        imagesavealpha($f, true);
        imagefilledrectangle($f, 0, 0, $fw, $h, imagecolorallocatealpha($f, 0, 0, 0, 127));
        imagecopy($f, $img, 0, 0, $i * $fw, 0, $fw, $h);
        $out[] = $f;
    }
    imagedestroy($img);
    return [$out, $fw, $h];
}

function visiblePx($img, int $w, int $h): int
{
    $n = 0;
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) < VIS_ALPHA) {
                $n++;
            }
        }
    }
    return $n;
}

/** Union art bbox over a set of same-size frames, or null if all empty. */
function unionBbox(array $frames, int $fw, int $h): ?array
{
    $minX = $fw;
    $minY = $h;
    $maxX = -1;
    $maxY = -1;
    foreach ($frames as $img) {
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $fw; $x++) {
                if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) < VIS_ALPHA) {
                    if ($x < $minX) $minX = $x;
                    if ($x > $maxX) $maxX = $x;
                    if ($y < $minY) $minY = $y;
                    if ($y > $maxY) $maxY = $y;
                }
            }
        }
    }
    return $maxX < 0 ? null : [$minX, $minY, $maxX - $minX + 1, $maxY - $minY + 1];
}

function cropFrame($src, array $bbox)
{
    [$x0, $y0, $w, $h] = $bbox;
    $dst = imagecreatetruecolor($w, $h);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefilledrectangle($dst, 0, 0, $w, $h, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopy($dst, $src, 0, 0, $x0, $y0, $w, $h);
    imagedestroy($src);
    return $dst;
}

function flipFrame($img, int $w, int $h)
{
    $dst = imagecreatetruecolor($w, $h);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefilledrectangle($dst, 0, 0, $w, $h, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    for ($x = 0; $x < $w; $x++) {
        imagecopy($dst, $img, $w - $x - 1, 0, $x, 0, 1, $h);
    }
    imagedestroy($img);
    return $dst;
}

function saveStrip(array $frames, string $path, int $fw, int $h): void
{
    $n = count($frames);
    $sheet = imagecreatetruecolor($fw * $n, $h);
    imagealphablending($sheet, false);
    imagesavealpha($sheet, true);
    imagefilledrectangle($sheet, 0, 0, $fw * $n, $h, imagecolorallocatealpha($sheet, 0, 0, 0, 127));
    foreach ($frames as $i => $f) {
        imagecopy($sheet, $f, $i * $fw, 0, 0, 0, $fw, $h);
        imagedestroy($f);
    }
    imagepng($sheet, $path);
    imagedestroy($sheet);
}

/** Uniform display box: target height, aspect width capped. feet always 0. */
function boxFor(int $artW, int $artH, int $targetH, int $capW): array
{
    $w = (int) round($targetH * $artW / $artH);
    if ($w > $capW) {
        $w = $capW;
        $h = (int) round($capW * $artH / $artW);
    } else {
        $h = $targetH;
    }
    return ['w' => $w, 'h' => $h, 'feet' => 0];
}

/** Deterministic full rewrite of config/skins.php from the in-memory array. */
function exportConfig(array $cfg): void
{
    $out = <<<'PHP'
<?php

/*
|--------------------------------------------------------------------------
| Field Journal skins catalog (guidelines/SKINS_PIPELINE.md)
|--------------------------------------------------------------------------
|
| Generated format - edit prices freely, but structural keys (frame/box/
| pivot/mascot/thumb/anims) are owned by scripts/skins/*.png strips are
| normalized: art fills every frame (union bbox incl. attacks), bosses
| face right, no blank tail frames. pivot = idle-art offset inside the
| frame (source px) used by --sox/--soy to center idle on slot anchors.
| price = null means free to own (SkinsController::owned()).
|
*/
return [

    'default_alice' => 'schoolgirl1',
    'default_boss' => 'demon',

PHP;

    foreach (['alice', 'boss'] as $type) {
        $out .= "\n    '$type' => [\n";
        foreach ($cfg[$type] as $key => $skin) {
            $out .= "        '$key' => [\n";
            $out .= '            ' . "'name' => " . var_export($skin['name'], true) . ",\n";
            $out .= '            ' . "'price' => " . var_export($skin['price'] ?? null, true) . ",\n";
            $out .= "            'frame' => ['w' => {$skin['frame']['w']}, 'h' => {$skin['frame']['h']}],\n";
            $out .= "            'box' => ['w' => {$skin['box']['w']}, 'h' => {$skin['box']['h']}, 'feet' => {$skin['box']['feet']}],\n";
            if (isset($skin['box_h'])) {
                $out .= "            'box_h' => {$skin['box_h']},\n";
            }
            $px = $skin['pivot'] ?? ['x' => 0, 'y' => 0];
            $out .= "            'pivot' => ['x' => {$px['x']}, 'y' => {$px['y']}],\n";
            if ($type === 'alice') {
                $out .= "            'mascot' => ['w' => {$skin['mascot']['w']}, 'h' => {$skin['mascot']['h']}],\n";
            }
            $out .= '            ' . "'thumb' => " . var_export($skin['thumb'], true) . ",\n";
            $out .= "            'anims' => [\n";
            foreach ($skin['anims'] as $an => $a) {
                $extra = !empty($a['hold']) ? ", 'hold' => true" : '';
                $loop = !empty($a['loop']) ? 'true' : 'false';
                $out .= "                '$an' => ['src' => " . var_export($a['src'], true)
                    . ", 'frames' => {$a['frames']}, 'fps' => {$a['fps']}, 'loop' => $loop$extra],\n";
            }
            $out .= "            ],\n";
            $out .= "        ],\n";
        }
        $out .= "    ],\n";
    }
    $out .= "\n];\n";

    file_put_contents(SKINS_CONFIG, $out);
}
