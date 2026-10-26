# Game asset attribution

ALICE's game-feel assets are vendored locally (no runtime CDN calls — school-network safe).

> **2026-10-05 cleanup:** unused vendored files were removed — 5 dead SFX cues,
> 3 unreferenced Kenney sprites, and the itch.io source packs in
> `storage/asset-packs/` pruned to their used-only sources (2,586 files /
> 112 MB). All credits in this file remain in force for the assets in use.

## Kenney (kenney.nl)

Packs vendored under `public/image/game/kenney/`.

License: **CC0 1.0 Universal** (public domain). No attribution required;
this file documents provenance. https://creativecommons.org/publicdomain/zero/1.0/

### Game Icons (vendored 2026-08-29, game-feel HUD + feedback)

Source: https://kenney.nl/assets/game-icons (105 icons, 1x/2x black + white).
Individual sprites copied under `public/image/game/kenney/`:

| File | Pack |
|---|---|
| `star_black.png` / `star_white.png` | Game Icons |
| `trophy_black.png` / `trophy_white.png` | Game Icons |
| `cross_black.png` | Game Icons |
| `coin_black.png` / `coin_white.png` | Game Icons (Expansion) |

Licenses archived alongside: `LICENSE-game-icons.txt`, `LICENSE-game-icons-expansion.txt`.
Used for: coin/star bursts, wrong flashes and result flair. Pure black/white
glyphs so they read cleanly against the retro-DS pixel-chrome (tinted via CSS
where needed). The three sprites no longer referenced by `arcade.js`
(`checkmark_black`, `target_black`, `warning_black`) were removed in the
2026-10-05 cleanup.

## Pictorial icon set (`public/image/game/icons/`, replaced 2026-10-05, restored 2026-10-05 pass-3)

The 41 tintable pictorial glyphs (31 `ui/` + 10 `genre/`) went through the
2026-10-05 itch.io art round and a same-day user revert: **rank, badge and
genre slots are back on the archived originals** (37 files, restored
byte-identical from `storage/asset-packs/_originals/icons/`); the remaining
**4 `ui/` files stay Nieobie CC0**. The `hourglass` slot rename was undone
with it (the badge/summary/points keys are `hourglass` again). Final split:
**36 game-icons + 1 Lucide (`flame`) + 4 Nieobie, 0 hand-authored** (the
interim `data-origin="alice-original"` drafts were overwritten by the
restore).

### game-icons.net (36 icons — 26 `ui/` rank/badge slots + 10 `genre/` tiles)

Source: https://game-icons.net
License: **CC BY 3.0** — free to use with credit to game-icons.net; individual
icon pages carry the per-icon author. Authors referenced in the project
history include **Lorc** (e.g. `lantern`, `light-bulb`) and **Delapouite**
(e.g. `open-book`, one genre tile); the full per-icon table was not preserved
across the rewrite, so credit is given collectively to the game-icons.net
contributors with this file as the attribution record. Files ship in the
standard tile format (opaque 512 background rect + `#fff` art);
`LibraryPresentation::iconGlyph()`/`genreGlyphSvg()` strip the rect and rewrite
`#fff` → `currentColor` at render time.

| `ui/` slot | Glyph | `ui/` slot | Glyph |
|---|---|---|---|
| `book-aura` | winged book | `book-cover` | closed book |
| `book-pile` | stacked books | `bookshelf` | bookshelf |
| `book-storm` | tumbling books | `calendar` | calendar |
| `cards` | cards | `cog` | gear |
| `compass` | compass | `crown` | crown |
| `hourglass` | hourglass | `key` | key |
| `lantern` | lantern | `laurel-crown` | laurel wreath |
| `light-bulb` | light bulb | `magnifying-glass` | magnifier |
| `map` | folded map | `maze` | maze |
| `notebook` | notebook | `open-book` | open book |
| `queen-crown` | queen crown | `scroll-quill` | scroll + quill |
| `snowflake` | snowflake | `spell-book` | spell book |
| `trophy` | trophy | `yarn` | ball of yarn |

| `genre/` tile | Glyph | `genre/` tile | Glyph |
|---|---|---|---|
| `adventure` | compass | `biography` | inkwell + quill |
| `fantasy` | crystal ball | `historical-fiction` | castle |
| `horror` | ghost | `mystery` | telescope |
| `realistic-fiction` | houses | `romance` | dancing figure |
| `science-fiction` | rocket | `science-nature` | leaves |

### Lucide — `ui/flame.svg` (1 icon, restored with the slate)

Not a game-icons tile — the archived set already carried this one. The file
self-credits in its header: `@license lucide-static v1.48.0 — ISC (flame,
filled)`; 24-grid art, no background rect, `#fff` art tinted via `#fff` →
`currentColor` like the tiles.

### Nieobie — Game Icon Pack (4 icons, kept from the 2026-10-05 round)

Source: https://nieobie.itch.io/free-icons ("Game Icon Pack" by Nieobie).
License: **CC0 1.0 Universal** — public domain, credit appreciated, none
required. Copied with only the fixed `width`/`height` attributes stripped;
art already ships as `currentColor`.

| File | Glyph |
|---|---|
| `ui/bell.svg` | bell |
| `ui/bookmark.svg` | ribbon bookmark (reader word-save) |
| `ui/sound-on.svg` | speaker on |
| `ui/sound-off.svg` | speaker muted |

Rendering: `LibraryPresentation::iconGlyph('ui'|'genre', name)` inlines the
file so art inherits `currentColor` (tile strip + `#fff` rewrite active for
the 37 restored files; the 4 Nieobie files pass through unchanged).
Icon holes are transparent and take the tile/surface colour — never hardcode
a fill.

Note: `ui/compass.svg` and `genre/adventure.svg` are the same game-icons
glyph by design — Story Explorer pairs with the Adventure genre (the original
set made the same pairing).

The student hub "People" trigger uses a standard two-person outline SVG
(heroicons-style, MIT) inlined in `resources/views/student/hub.blade.php` — it
is not part of this set.

### Rank / title ladder icons (`icons/ui/`)

Dedicated, **reserved** set for the reader title ladder — deliberately NOT shared
with the badge/task/game icon pool (unlike the previous tier icons, which reused
`book-cover`/`book-pile`/`map`/`crown`/`bookshelf`/`maze`/`spell-book`/`laurel-crown`/`trophy`
that also appeared on badges and games). Each rank owns its glyph; reference the
`icon` values in `config/gamification.php` `points.titles`:
`open-book` `magnifying-glass` `notebook` `compass` `queen-crown` `book-storm`
`scroll-quill` `book-aura` `yarn` `lantern` — all 10 are game-icons.net tiles
(restored in the pass-3 revert); no rank glyph uses the Nieobie remainder.

## Sound effects (`public/audio/ui/`, vendored 2026-10-05, itch.io SFX round)

19 UI/game cues pre-encoded from the packs below (trimmed, loudness-normalized
MP3s ≈ -16 LUFS) and played file-first by `resources/js/game/sfx.js`; the
module's oscillator synthesis is retained only as an autoplay/fallback layer.

| Cue(s) | Origin (folder in pack) |
|---|---|
| `go` `coin` | 400 Sounds — Retro |
| `pop` `select` | 400 Sounds — UI |
| `flip` | 400 Sounds — Card and Board |
| `match` | 400 Sounds — Match Three |
| `whoosh` | 400 Sounds — Other |
| `claim` `open` `close` | 400 Sounds — Items |
| `stamp` | 400 Sounds — Materials |
| `freeze` | 400 Sounds — Environment |
| `fanfare` `badge` | 400 Sounds — Musical Effects |
| `wrong` `tick` `turn` `nav` | Interface SFX Pack 1 |
| `correct` | 8-bit / 16-bit (x25) Pack |

The original 24-cue set lost 5 cues (`click` `rankUp` `sparkle` `save`
`toast`) in the 2026-10-05 unused-assets cleanup — they had no call site
anywhere; the encoded files and their `CUES`/`FILES` entries were removed
together with the unused pack sources.

### Battle cues (`public/audio/journal/`)

- `hit.ogg` `lose.ogg` `victory.ogg` — Kenney "Impact Sounds" + "Music
  Jingles" (CC0), licenses archived alongside the files.
- `return-by-death.mp3` — user-provided clip (Re:Zero "return by death"
  line), added 2026-10-23. Plays as cue `journalRestart` when the defeat
  RESTARTING overlay lifts.

### Packs

All packs were **pruned to their used sources only** on 2026-10-05 (2,586
files / 112 MB deleted; `storage/asset-packs/` keeps the 19 encoded cues'
wav/ogg originals + license/readme files). Credits below still apply to the
encoded MP3s regardless of the source files being pruned.

- **400 Sounds Pack** — Chequered Ink — any use including commercial, with or
  without credit; no resale/redistribution of the unaltered assets —
  https://ci.itch.io/400-sounds-pack
- **8-bit / 16-bit Sound Effects (x25) Pack** — JDWasabi — free for commercial
  and non-commercial use (credit appreciated) —
  https://jdwasabi.itch.io/8-bit-16-bit-sound-effects-pack
- **Interface SFX Pack 1** — obsydianx — CC0 1.0 Universal —
  https://obsydianx.itch.io/interface-sfx-pack-1

## Student avatars (`public/image/avatar/`, composited 2026-10-05, itch.io art round)

The 16 picker avatars (keys in `config/avatars.php`) are composited
paper-doll PNGs: body tone × outfit (farmer `pfpn` / forester `fstr`) ×
hairstyle (`bob1` / `dap1`), cropped to a 24×24px bust portrait and upscaled
×16 nearest-neighbour to 384². The previous DiceBear "Pixel Art" SVGs and
their backup folder live on under `storage/asset-packs/_originals/avatars/`.

### Mana Seed Character Base Demo 2.0 — Seliel the Shaper

Source: https://seliel-the-shaper.itch.io/ (pack: "FREE Mana Seed Character
Base Demo 2.0", extracted under `storage/asset-packs/`).

License: the pack's own note states **"You may use this demo asset
commercially or non-commercially."** No attribution required — credit
appreciated: Seliel the Shaper. The full (paid) pack:
https://seliel-the-shaper.itch.io/character-base

## Celebration FX (`public/image/game/fx/`, cut 2026-10-05, itch.io art round)

Three one-shot pixel-smoke bursts for reward / level-up / win moments
(`reward.png` 6 frames, `levelup.png` 11 frames, `win.png` 9 frames — horizontal
192px sprite strips, black keyed to transparent). Replay is pure CSS
(`.fx-sprite` keyframes in `resources/css/app.css`), triggered by
`resources/js/components/celebrate.js` (server elements tagged
`data-celebrate="reward|levelup|win"`; the arcade games call
`window.Celebrate('win', el)` at endGame).

### Free Smoke Fx Pixel 2 — bdragon1727

Source: https://bdragon1727.itch.io/ (pack: "FREE Smoke Fx Pixel 2",
extracted under `storage/asset-packs/`).

License: **free for commercial and non-commercial use; credit appreciated**
(bdragon1727). Frames were cut from the pack's preview GIF grids **04, 05 and
06** (grid 07 was never used and — like the pack's static PNG stills — was
removed in the 2026-10-05 unused-assets cleanup, which left only the three
cut sources); sprite strips live in `public/image/game/fx/`.

## Character art

The **ALICE mascot** sprites (the game's companion character) are original
artwork drawn by **Arañez John Ray**. They are the project's own commissioned
art — always credit Arañez John Ray when reused or shown.

| File | Purpose |
|---|---|
| `public/image/Alice/Light mode/alice-lt-talking.png` | talking/companion pose (light theme) |
| `public/image/Alice/Light mode/alice-lt-casual.png` | casual pose (light theme) |
| `public/image/Alice/Dark mode/alice-dk-talking.png` | talking/companion pose (dark theme) |
| `public/image/Alice/Dark mode/alice-dk-casual.png` | casual pose (dark theme) |

Attribution line used on the About Us page: "ALICE character art made by Arañez John Ray".

## Fonts

- Press Start 2P (Google Fonts, OFL) — pixel display face
- Quicksand (Google Fonts, OFL) — reading body face

## JS libraries (actually imported in the bundle)

- anime.js v4 — MIT — optional quiz animation
- chart.js v4 — MIT — hub stat charts
- vosk-browser — Apache 2.0 — offline speech-to-text pronunciation check

> Not shown here: `howler`, `canvas-confetti`, and the npm `openai`/`@ai-sdk`
> packages are declared in `package.json` but are NOT imported anywhere and are
> dead dependencies (Kokoro neural narration runs via the Python
> `tools/narration/generate_narration.py` tool, not the frontend bundle).
