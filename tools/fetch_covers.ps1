<#
.SYNOPSIS
    Whole-library cover fetcher (self-healing, rerunnable). See tools/FETCHING.md.

.DESCRIPTION
    Open Library covers pipeline proven 42/42 in batch 5 and extended to all
    library slugs. For each book: search.json (title+author, then title-only)
    -> first cover_i candidates -> /b/id/N-L.jpg?default=false -> validate
    (>=10KB, JPEG magic bytes, >=180x250 via System.Drawing) -> save to
    storage/app/public/covers/<slug>.jpg. Existing files are skipped, so the
    script can be rerun any time; it only fetches what is missing.

    COUNTERMEASURES (all earned the hard way - do not remove):
    - search.json results live under $res.docs (NOT .documents - a wrong
      property here silently yields zero candidates).
    - Structured ?title=&author= params beat generic ?q= for precision.
    - Two-pass fallback: title+author, then title-only (or per-book 'f'
      fallback title, e.g. canonical collection titles that carry scans).
    - System.Drawing validation rejects OL blank/stub images that pass a
      naive size check.
    - TLS12 forced (PS 5.1 default is too old), custom UA, pacing 600ms per
      candidate / 800ms per attempt.
#>
$ErrorActionPreference = 'Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$coversDir = 'C:\laragon\www\Alice-Ver2\storage\app\public\covers'

$books = @(
    # --- batch 1: aesop fables (authorless searches hit better editions) ---
    @{ s='aesop-the-boy-who-cried-wolf';        t='The Boy Who Cried Wolf'; a=$null },
    @{ s='aesop-the-tortoise-and-the-hare';     t='The Tortoise and the Hare'; a=$null },
    @{ s='aesop-the-lion-and-the-mouse';        t='The Lion and the Mouse'; a=$null },
    @{ s='aesop-town-mouse-and-country-mouse';  t='The Town Mouse and the Country Mouse'; a=$null },
    @{ s='aesop-the-fox-and-the-grapes';        t='The Fox and the Grapes'; a=$null },
    @{ s='aesop-the-ants-and-the-grasshopper';  t='The Ants and the Grasshopper'; a=$null },
    @{ s='aesop-the-crow-and-the-pitcher';      t='The Crow and the Pitcher'; a=$null },
    @{ s='aesop-the-hen-and-the-golden-eggs';   t='The Goose that Laid the Golden Eggs'; a=$null },
    @{ s='aesop-the-north-wind-and-the-sun';    t='The North Wind and the Sun'; a=$null },
    @{ s='aesop-the-miller-his-son-and-their-ass'; t='The Miller his Son and the Donkey'; a=$null },
    # --- batch 2: fairy tales ---
    @{ s='fairy-the-frog-prince';          t='The Frog Prince'; a=$null },
    @{ s='fairy-briar-rose';               t='Sleeping Beauty'; a=$null },
    @{ s='fairy-little-red-cap';           t='Little Red Riding Hood'; a=$null },
    @{ s='fairy-rumpelstiltskin';          t='Rumpelstiltskin'; a=$null },
    @{ s='fairy-wolf-seven-kids';          t='The Wolf and the Seven Little Kids'; a=$null },
    @{ s='fairy-golden-goose';             t='The Golden Goose'; a=$null },
    @{ s='fairy-emperor-new-clothes';      t="The Emperor's New Clothes"; a='Hans Christian Andersen' },
    @{ s='fairy-real-princess';            t='The Princess and the Pea'; a=$null },
    @{ s='fairy-leap-frog';                t='The Leap-Frog'; a=$null },
    @{ s='fairy-match-girl';               t='The Little Match Girl'; a=$null },
    # --- batch 3: classic shorts (collection-title overrides where story-level editions lack scans) ---
    @{ s='short-magi';              t='The Gift of the Magi'; a='O. Henry' },
    @{ s='short-cop-anthem';        t='The Four Million'; a='O. Henry' },
    @{ s='short-after-twenty-years'; t='After Twenty Years'; a='O. Henry' },
    @{ s='short-tell-tale-heart';   t='The Tell-Tale Heart'; a='Edgar Allan Poe' },
    @{ s='short-cask-amontillado';  t='The Cask of Amontillado'; a='Edgar Allan Poe' },
    @{ s='short-owl-creek';         t='An Occurrence at Owl Creek Bridge'; a='Ambrose Bierce' },
    @{ s='short-necklace';          t='The Necklace'; a='Guy de Maupassant'; f='Original Short Stories of Maupassant' },
    @{ s='short-open-window';       t='The Open Window'; a='Saki'; f='Beasts and Super-Beasts' },
    @{ s='short-jumping-frog';      t='The Celebrated Jumping Frog of Calaveras County'; a='Mark Twain' },
    @{ s='short-golden-touch';      t='The Golden Touch'; a='Nathaniel Hawthorne' },
    # --- batch 4: adapted classics I (title overrides = edition with real scan) ---
    @{ s='classic-lady-or-the-tiger';    t='The Lady or the Tiger'; a='Frank Stockton' },
    @{ s='classic-the-interlopers';      t='The Interlopers'; a='Saki' },
    @{ s='classic-pair-of-silk-stockings'; t='The Awakening'; a='Kate Chopin' },
    @{ s='classic-franklin-philadelphia'; t='The Autobiography of Benjamin Franklin'; a='Benjamin Franklin' },
    @{ s='classic-monkeys-paw';          t="The Monkey's Paw"; a='W. W. Jacobs' },
    # classic-the-star removed 2026-08-23: story retired in batch 12 (duplicate
    # resolution, see ALICE_MASTER_PLAN.md); keeping the entry made every rerun
    # resurrect an orphan jpg for a story that no longer exists.
    @{ s='classic-bride-yellow-sky';     t='The Open Boat'; a='Stephen Crane' },
    @{ s='classic-build-a-fire';         t='To Build a Fire'; a='Jack London' },
    @{ s='classic-rip-van-winkle';       t='Rip Van Winkle'; a='Washington Irving' },
    @{ s='classic-springfield-fox';      t='Wild Animals I Have Known'; a='Ernest Thompson Seton' },
    # --- batch 5: adapted classics II ---
    @{ s='classic-treasure-island'; t='Treasure Island'; a='Robert Louis Stevenson' },
    @{ s='classic-frankenstein';    t='Frankenstein'; a='Mary Shelley' },
    @{ s='classic-prince-pauper';   t='The Prince and the Pauper'; a='Mark Twain' },
    @{ s='classic-call-wild';       t='The Call of the Wild'; a='Jack London' },
    @{ s='classic-pride-prejudice'; t='Pride and Prejudice'; a='Jane Austen' },
    # --- batch 6: adapted classics III ---
    @{ s='classic-time-machine';    t='The Time Machine'; a='H. G. Wells' },
    @{ s='classic-war-worlds';      t='The War of the Worlds'; a='H. G. Wells' },
    @{ s='classic-dracula';         t='Dracula'; a='Bram Stoker' },
    @{ s='classic-three-musketeers'; t='The Three Musketeers'; a='Alexandre Dumas' },
    @{ s='classic-monte-cristo';    t='The Count of Monte Cristo'; a='Alexandre Dumas' },
    @{ s='classic-tom-sawyer';      t='The Adventures of Tom Sawyer'; a='Mark Twain' },
    @{ s='classic-wizard-oz';       t='The Wonderful Wizard of Oz'; a='L. Frank Baum'; f='The Wizard of Oz' },
    @{ s='classic-secret-garden';   t='The Secret Garden'; a='Frances Hodgson Burnett' },
    @{ s='classic-jungle-book';     t='The Jungle Book'; a='Rudyard Kipling' },
    @{ s='classic-gulliver';        t="Gulliver's Travels"; a='Jonathan Swift' },
    # --- batch 7: detective unit ('skip' = take the Nth candidate instead of
    #     the first, so sibling slugs sharing one collection title get distinct
    #     edition scans and never byte-collide) ---
    @{ s='mystery-scandal-bohemia';   t='The Adventures of Sherlock Holmes'; a='Arthur Conan Doyle'; skip=0 },
    @{ s='mystery-red-headed-league'; t='The Adventures of Sherlock Holmes'; a='Arthur Conan Doyle'; skip=1 },
    @{ s='mystery-blue-carbuncle';    t='The Adventures of Sherlock Holmes'; a='Arthur Conan Doyle'; skip=2 },
    @{ s='mystery-speckled-band';     t='The Adventures of Sherlock Holmes'; a='Arthur Conan Doyle'; skip=3 },
    @{ s='mystery-silver-blaze';      t='The Memoirs of Sherlock Holmes'; a='Arthur Conan Doyle'; skip=3 },
    @{ s='mystery-final-problem';     t='The Memoirs of Sherlock Holmes'; a='Arthur Conan Doyle'; skip=4 },
    @{ s='mystery-blue-cross';        t='The Blue Cross'; a='G. K. Chesterton'; f='The Innocence of Father Brown' },
    @{ s='classic-around-world';      t='Around the World in Eighty Days'; a='Jules Verne' },
    @{ s='classic-leagues-under-sea'; t='Twenty Thousand Leagues Under the Sea'; a='Jules Verne' },
    @{ s='nature-lobo-wolf';          t='Lobo the King of Currumpaw'; a=$null; f='Wild Animals I Have Known'; skip=1 },
    # --- batch 8: micro-shorts unit (OL has exactly ONE scan for the FFSR
    #     collection, so each sibling takes a different story-appropriate
    #     edition instead of sharing bytes; romances ride The Four Million
    #     editions behind short-cop-anthem which took candidate 0 years ago;
    #     Seton book continues the wild-animals chain springfield-fox(implicit
    #     0) lobo(skip=1)) ---
    @{ s='bio-bruce-spider';          t='Scotland''s Story'; a='H. E. Marshall' },
    @{ s='bio-king-alfred-cakes';     t='King Alfred of England'; a=$null },
    @{ s='classic-william-tell';      t='William Tell Told Again'; a=$null },
    @{ s='classic-damon-pythias';     t='Damon and Pythias'; a=$null },
    @{ s='classic-androclus-lion';    t='Androcles and the Lion'; a='Bernard Shaw' },
    @{ s='bio-saving-birds';          t='Fifty Famous People'; a='James Baldwin' },
    @{ s='nature-silverspot';         t='Wild Animals I Have Known'; a='Ernest Thompson Seton'; skip=2 },
    @{ s='nature-bingo';              t='Wild Animals I Have Known'; a='Ernest Thompson Seton'; skip=3 },
    @{ s='romance-busy-broker';       t='The Four Million'; a='O. Henry'; skip=1 },
    @{ s='romance-springtime-carte';  t=('The Four Million'); a='O. Henry'; skip=2 },
    # --- batch 9: micro-shorts unit II (chains continue: Fifty Famous People
    #     behind saving-birds(0); wild-animals chain extends springfield-fox(0)
    #     lobo(1) silverspot(2) bingo(3); Trimmed Lamp pair split like the b7
    #     Sherlock siblings) ---
    @{ s='bio-the-horseshoe-nails';     t='The Horseshoe Nails'; a=$null },
    @{ s='bio-king-canute-seashore';    t='King Canute on the Seashore'; a='James Baldwin'; f='Canute the Great' },
    @{ s='horror-masque-red-death';     t='The Masque of the Red Death'; a='Edgar Allan Poe' },
    @{ s='horror-oval-portrait';        t='The Oval Portrait'; a='Edgar Allan Poe' },
    @{ s='romance-purple-dress';        t='The Trimmed Lamp'; a='O. Henry' },
    @{ s='romance-count-wedding-guest'; t='The Trimmed Lamp'; a='O. Henry'; skip=1 },
    @{ s='scifi-davidsons-eyes';        t='The Stolen Bacillus and Other Incidents'; a='H. G. Wells' },
    @{ s='nature-raggylug';             t='Wild Animals I Have Known'; a='Ernest Thompson Seton'; skip=4 },
    @{ s='nature-redruff';              t='Wild Animals I Have Known'; a='Ernest Thompson Seton'; skip=5 },
    # --- batch 10: micro-shorts unit III (chains continue where depth
    #     allows: wild-animals chain springfield-fox(0) lobo(1) silverspot(2)
    #     bingo(3) raggylug(4) redruff(5) is EXHAUSTED at 6 covered editions,
    #     so mustang takes a story-appropriate override; FFSR / Stolen
    #     Bacillus / Trimmed Lamp each carry only ONE scan, so their second
    #     riders also take themed editions instead of skip depths) ---
    @{ s='bio-cornelias-jewels';        t='Famous Men of Rome'; a='John H. Haaren' },
    @{ s='bio-sir-philip-sidney';       t='Sir Philip Sidney'; a=$null },
    @{ s='romance-mammon-archer';       t='Sixes and Sevens'; a='O. Henry' },
    @{ s='scifi-strange-orchid';        t='The Plattner Story and Others'; a='H. G. Wells' },
    @{ s='horror-valdemar';             t='The Facts in the Case of M. Valdemar'; a='Edgar Allan Poe' },
    @{ s='scifi-diamond-lens';          t='The Diamond Lens'; a='Fitz-James O''Brien' },
    @{ s='nature-white-heron';          t='A White Heron'; a='Sarah Orne Jewett' },
    @{ s='romance-last-leaf';           t='Whirligigs'; a='O. Henry' },
    @{ s='nature-pacing-mustang';       t='Smoky the Cowhorse'; a='Will James' },
    @{ s='adventure-rikki-tikki';       t='Rikki-Tikki-Tavi'; a='Rudyard Kipling' },
    # --- batch 11: balance unit x5 (collision-aware: short-magi(b3) owns the
    #     Gift of the Magi scan so magi rides an unused O. Henry collection;
    #     classic-the-star(b4) holds its Wells edition so
    #     star takes depth 1 of Tales of Space and Time; whistle avoids both
    #     the single-scan Fifty Famous People pool and the autobiography
    #     edition held by franklin-philadelphia via depth 1 of a BF bio
    #     search) ---
    @{ s='hf-man-without-country';      t='The Man Without a Country'; a='Edward Everett Hale' },
    @{ s='bio-the-whistle';             t='Benjamin Franklin'; a=$null; skip=1 },
    @{ s='romance-gift-magi';           t='Strictly Business'; a='O. Henry'; f='Options'; skip=1 },
    @{ s='scifi-the-star';              t='The Island of Doctor Moreau'; a='H. G. Wells' },
    @{ s='nature-stickeen';             t='Stickeen'; a='John Muir' },
    # --- batch 12: balance completion unit x6 (collision-aware: Stolen
    #     Bacillus carries only ONE scan and davidsons-eyes(b9) holds it at
    #     depth 0, so flying-man takes the thematically ideal Wells aviation
    #     novel The War in the Air instead; horseman takes the In the Midst
    #     of Life collection because story-level editions of the Bierce tale
    #     carry no scans; sleepy-hollow / love-of-life / black-cat /
    #     purloined-letter each have plentiful own-title editions) ---
    @{ s='hf-sleepy-hollow';            t='The Legend of Sleepy Hollow'; a='Washington Irving' },
    @{ s='hf-horseman-sky';             t='In the Midst of Life'; a='Ambrose Bierce' },
    @{ s='adventure-love-of-life';      t='Love of Life'; a='Jack London' },
    @{ s='horror-black-cat';            t='The Black Cat'; a='Edgar Allan Poe' },
    @{ s='mystery-purloined-letter';    t='The Purloined Letter'; a='Edgar Allan Poe' },
    @{ s='scifi-flying-man';            t='The War in the Air'; a='H. G. Wells' }
)

Add-Type -AssemblyName System.Drawing

function Test-Jpeg([string]$path) {
    $bytes = [IO.File]::ReadAllBytes($path)
    if ($bytes.Length -lt 10000) { return $false }
    if ($bytes[0] -ne 0xFF -or $bytes[1] -ne 0xD8) { return $false }
    try {
        $img = [System.Drawing.Image]::FromFile($path)
        $ok = $img.Width -ge 180 -and $img.Height -ge 250
        $img.Dispose()
        return $ok
    } catch { return $false }
}

$okCount = 0; $failList = @()
foreach ($b in $books) {
    $dest = Join-Path $coversDir ($b.s + '.jpg')
    if (Test-Path $dest) { Write-Output "$($b.s): EXISTS-SKIP"; continue }

    $pass2Title = if ($b.f) { $b.f } else { $b.t }
    $skipN = if ($b.skip) { [int]$b.skip } else { 0 }
    $done = $false
    foreach ($attempt in @(@($b.t, $b.a), @($pass2Title, $null))) {
        if ($done) { break }
        $q = 'https://openlibrary.org/search.json?title=' + [Uri]::EscapeDataString($attempt[0]) + '&fields=cover_i,title&limit=8'
        if ($attempt[1]) { $q += '&author=' + [Uri]::EscapeDataString($attempt[1]) }
        try {
            $res = Invoke-RestMethod -Uri $q -TimeoutSec 20 -Headers @{ 'User-Agent' = 'alice-library-covers/1.0' }
            $ids = @($res.docs | Where-Object { $_.cover_i } | Select-Object -ExpandProperty cover_i)
            if ($skipN -gt 0) { $ids = @($ids | Select-Object -Skip $skipN) }
            foreach ($cid in $ids) {
                $url = "https://covers.openlibrary.org/b/id/$cid-L.jpg?default=false"
                try {
                    $tmpFile = Join-Path $env:TEMP ('cov_' + $b.s + '.jpg')
                    Invoke-WebRequest -Uri $url -OutFile $tmpFile -TimeoutSec 25 -Headers @{ 'User-Agent' = 'alice-library-covers/1.0' }
                    if (Test-Jpeg $tmpFile) {
                        Move-Item -Force $tmpFile $dest
                        Write-Output "$($b.s): OK (cover_i=$cid)"
                        $done = $true
                        break
                    } else {
                        Remove-Item -Force $tmpFile -ErrorAction SilentlyContinue
                    }
                } catch { }
                Start-Sleep -Milliseconds 600
            }
        } catch {
            Write-Output "$($b.s): search-error $($_.Exception.Message)"
        }
        Start-Sleep -Milliseconds 800
    }
    if (-not $done) {
        if (-not (Test-Path $dest)) { Write-Output "$($b.s): FAIL"; $failList += $b.s } else { $okCount++ }
    } else { $okCount++ }
}
Write-Output "DONE ok=$okCount fail=$($failList.Count)"
if ($failList.Count -gt 0) { Write-Output ('FAILED: ' + ($failList -join ', ')) }
