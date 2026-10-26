<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Self-healing cover fetcher for the curated Alice Library. The import only
 * writes cover paths into the database; the JPGs live on the public disk and
 * can go missing (delete + re-import cycles, fresh checkouts - the covers
 * directory is git-ignored). After an import this port of tools/fetch_covers.ps1
 * re-downloads whatever is missing from Open Library: search.json for cover
 * candidates, then /b/id/N-L.jpg with size + JPEG + dimension validation so
 * blank stubs never land on disk. Existing files are skipped, so reruns are
 * free. The PowerShell script remains the offline/bulk tool; this class only
 * heals the delta during import.
 */
class CoverFetcher
{
    private const UA = 'alice-library-covers/1.0';
    private const MIN_BYTES = 10000;
    private const MIN_WIDTH = 180;
    private const MIN_HEIGHT = 250;

    /**
     * Search metadata per catalog slug - keep in sync with
     * tools/fetch_covers.ps1 (same table, same countermeasures).
     *
     * t = search title, a = author (null = authorless search), f = pass-2
     * fallback title, s = candidate skip depth (collision-aware distinct
     * editions for sibling slugs sharing one collection scan).
     */
    private const BOOKS = [
        'aesop-the-boy-who-cried-wolf' => ['t' => 'The Boy Who Cried Wolf'],
        'aesop-the-tortoise-and-the-hare' => ['t' => 'The Tortoise and the Hare'],
        'aesop-the-lion-and-the-mouse' => ['t' => 'The Lion and the Mouse'],
        'aesop-town-mouse-and-country-mouse' => ['t' => 'The Town Mouse and the Country Mouse'],
        'aesop-the-fox-and-the-grapes' => ['t' => 'The Fox and the Grapes'],
        'aesop-the-ants-and-the-grasshopper' => ['t' => 'The Ants and the Grasshopper'],
        'aesop-the-crow-and-the-pitcher' => ['t' => 'The Crow and the Pitcher'],
        'aesop-the-hen-and-the-golden-eggs' => ['t' => 'The Goose that Laid the Golden Eggs'],
        'aesop-the-north-wind-and-the-sun' => ['t' => 'The North Wind and the Sun'],
        'aesop-the-miller-his-son-and-their-ass' => ['t' => 'The Miller his Son and the Donkey'],
        'fairy-the-frog-prince' => ['t' => 'The Frog Prince'],
        'fairy-briar-rose' => ['t' => 'Sleeping Beauty'],
        'fairy-little-red-cap' => ['t' => 'Little Red Riding Hood'],
        'fairy-rumpelstiltskin' => ['t' => 'Rumpelstiltskin'],
        'fairy-wolf-seven-kids' => ['t' => 'The Wolf and the Seven Little Kids'],
        'fairy-golden-goose' => ['t' => 'The Golden Goose'],
        'fairy-emperor-new-clothes' => ['t' => "The Emperor's New Clothes", 'a' => 'Hans Christian Andersen'],
        'fairy-real-princess' => ['t' => 'The Princess and the Pea'],
        'fairy-leap-frog' => ['t' => 'The Leap-Frog'],
        'fairy-match-girl' => ['t' => 'The Little Match Girl'],
        'short-magi' => ['t' => 'The Gift of the Magi', 'a' => 'O. Henry'],
        'short-cop-anthem' => ['t' => 'The Four Million', 'a' => 'O. Henry'],
        'short-after-twenty-years' => ['t' => 'After Twenty Years', 'a' => 'O. Henry'],
        'short-tell-tale-heart' => ['t' => 'The Tell-Tale Heart', 'a' => 'Edgar Allan Poe'],
        'short-cask-amontillado' => ['t' => 'The Cask of Amontillado', 'a' => 'Edgar Allan Poe'],
        'short-owl-creek' => ['t' => 'An Occurrence at Owl Creek Bridge', 'a' => 'Ambrose Bierce'],
        'short-necklace' => ['t' => 'The Necklace', 'a' => 'Guy de Maupassant', 'f' => 'Original Short Stories of Maupassant'],
        'short-open-window' => ['t' => 'The Open Window', 'a' => 'Saki', 'f' => 'Beasts and Super-Beasts'],
        'short-jumping-frog' => ['t' => 'The Celebrated Jumping Frog of Calaveras County', 'a' => 'Mark Twain'],
        'short-golden-touch' => ['t' => 'The Golden Touch', 'a' => 'Nathaniel Hawthorne'],
        'classic-lady-or-the-tiger' => ['t' => 'The Lady or the Tiger', 'a' => 'Frank Stockton'],
        'classic-the-interlopers' => ['t' => 'The Interlopers', 'a' => 'Saki'],
        'classic-pair-of-silk-stockings' => ['t' => 'The Awakening', 'a' => 'Kate Chopin'],
        'classic-franklin-philadelphia' => ['t' => 'The Autobiography of Benjamin Franklin', 'a' => 'Benjamin Franklin'],
        'classic-monkeys-paw' => ['t' => "The Monkey's Paw", 'a' => 'W. W. Jacobs'],
        'classic-bride-yellow-sky' => ['t' => 'The Open Boat', 'a' => 'Stephen Crane'],
        'classic-build-a-fire' => ['t' => 'To Build a Fire', 'a' => 'Jack London'],
        'classic-rip-van-winkle' => ['t' => 'Rip Van Winkle', 'a' => 'Washington Irving'],
        'classic-springfield-fox' => ['t' => 'Wild Animals I Have Known', 'a' => 'Ernest Thompson Seton'],
        'classic-treasure-island' => ['t' => 'Treasure Island', 'a' => 'Robert Louis Stevenson'],
        'classic-frankenstein' => ['t' => 'Frankenstein', 'a' => 'Mary Shelley'],
        'classic-prince-pauper' => ['t' => 'The Prince and the Pauper', 'a' => 'Mark Twain'],
        'classic-call-wild' => ['t' => 'The Call of the Wild', 'a' => 'Jack London'],
        'classic-pride-prejudice' => ['t' => 'Pride and Prejudice', 'a' => 'Jane Austen'],
        'classic-time-machine' => ['t' => 'The Time Machine', 'a' => 'H. G. Wells'],
        'classic-war-worlds' => ['t' => 'The War of the Worlds', 'a' => 'H. G. Wells'],
        'classic-dracula' => ['t' => 'Dracula', 'a' => 'Bram Stoker'],
        'classic-three-musketeers' => ['t' => 'The Three Musketeers', 'a' => 'Alexandre Dumas'],
        'classic-monte-cristo' => ['t' => 'The Count of Monte Cristo', 'a' => 'Alexandre Dumas'],
        'classic-tom-sawyer' => ['t' => 'The Adventures of Tom Sawyer', 'a' => 'Mark Twain'],
        'classic-wizard-oz' => ['t' => 'The Wonderful Wizard of Oz', 'a' => 'L. Frank Baum', 'f' => 'The Wizard of Oz'],
        'classic-secret-garden' => ['t' => 'The Secret Garden', 'a' => 'Frances Hodgson Burnett'],
        'classic-jungle-book' => ['t' => 'The Jungle Book', 'a' => 'Rudyard Kipling'],
        'classic-gulliver' => ['t' => "Gulliver's Travels", 'a' => 'Jonathan Swift'],
        'mystery-scandal-bohemia' => ['t' => 'The Adventures of Sherlock Holmes', 'a' => 'Arthur Conan Doyle', 's' => 0],
        'mystery-red-headed-league' => ['t' => 'The Adventures of Sherlock Holmes', 'a' => 'Arthur Conan Doyle', 's' => 1],
        'mystery-blue-carbuncle' => ['t' => 'The Adventures of Sherlock Holmes', 'a' => 'Arthur Conan Doyle', 's' => 2],
        'mystery-speckled-band' => ['t' => 'The Adventures of Sherlock Holmes', 'a' => 'Arthur Conan Doyle', 's' => 3],
        'mystery-silver-blaze' => ['t' => 'The Memoirs of Sherlock Holmes', 'a' => 'Arthur Conan Doyle', 's' => 3],
        'mystery-final-problem' => ['t' => 'The Memoirs of Sherlock Holmes', 'a' => 'Arthur Conan Doyle', 's' => 4],
        'mystery-blue-cross' => ['t' => 'The Blue Cross', 'a' => 'G. K. Chesterton', 'f' => 'The Innocence of Father Brown'],
        'classic-around-world' => ['t' => 'Around the World in Eighty Days', 'a' => 'Jules Verne'],
        'classic-leagues-under-sea' => ['t' => 'Twenty Thousand Leagues Under the Sea', 'a' => 'Jules Verne'],
        'nature-lobo-wolf' => ['t' => 'Lobo the King of Currumpaw', 'f' => 'Wild Animals I Have Known', 's' => 1],
        'bio-bruce-spider' => ['t' => "Scotland's Story", 'a' => 'H. E. Marshall'],
        'bio-king-alfred-cakes' => ['t' => 'King Alfred of England'],
        'classic-william-tell' => ['t' => 'William Tell Told Again'],
        'classic-damon-pythias' => ['t' => 'Damon and Pythias'],
        'classic-androclus-lion' => ['t' => 'Androcles and the Lion', 'a' => 'Bernard Shaw'],
        'bio-saving-birds' => ['t' => 'Fifty Famous People', 'a' => 'James Baldwin'],
        'nature-silverspot' => ['t' => 'Wild Animals I Have Known', 'a' => 'Ernest Thompson Seton', 's' => 2],
        'nature-bingo' => ['t' => 'Wild Animals I Have Known', 'a' => 'Ernest Thompson Seton', 's' => 3],
        'romance-busy-broker' => ['t' => 'The Four Million', 'a' => 'O. Henry', 's' => 1],
        'romance-springtime-carte' => ['t' => 'The Four Million', 'a' => 'O. Henry', 's' => 2],
        'bio-the-horseshoe-nails' => ['t' => 'The Horseshoe Nails'],
        'bio-king-canute-seashore' => ['t' => 'King Canute on the Seashore', 'a' => 'James Baldwin', 'f' => 'Canute the Great'],
        'horror-masque-red-death' => ['t' => 'The Masque of the Red Death', 'a' => 'Edgar Allan Poe'],
        'horror-oval-portrait' => ['t' => 'The Oval Portrait', 'a' => 'Edgar Allan Poe'],
        'romance-purple-dress' => ['t' => 'The Trimmed Lamp', 'a' => 'O. Henry'],
        'romance-count-wedding-guest' => ['t' => 'The Trimmed Lamp', 'a' => 'O. Henry', 's' => 1],
        'scifi-davidsons-eyes' => ['t' => 'The Stolen Bacillus and Other Incidents', 'a' => 'H. G. Wells'],
        'nature-raggylug' => ['t' => 'Wild Animals I Have Known', 'a' => 'Ernest Thompson Seton', 's' => 4],
        'nature-redruff' => ['t' => 'Wild Animals I Have Known', 'a' => 'Ernest Thompson Seton', 's' => 5],
        'bio-cornelias-jewels' => ['t' => 'Famous Men of Rome', 'a' => 'John H. Haaren'],
        'bio-sir-philip-sidney' => ['t' => 'Sir Philip Sidney'],
        'romance-mammon-archer' => ['t' => 'Sixes and Sevens', 'a' => 'O. Henry'],
        'scifi-strange-orchid' => ['t' => 'The Plattner Story and Others', 'a' => 'H. G. Wells'],
        'horror-valdemar' => ['t' => 'The Facts in the Case of M. Valdemar', 'a' => 'Edgar Allan Poe'],
        'scifi-diamond-lens' => ['t' => 'The Diamond Lens', 'a' => "Fitz-James O'Brien"],
        'nature-white-heron' => ['t' => 'A White Heron', 'a' => 'Sarah Orne Jewett'],
        'romance-last-leaf' => ['t' => 'Whirligigs', 'a' => 'O. Henry'],
        'nature-pacing-mustang' => ['t' => 'Smoky the Cowhorse', 'a' => 'Will James'],
        'adventure-rikki-tikki' => ['t' => 'Rikki-Tikki-Tavi', 'a' => 'Rudyard Kipling'],
        'hf-man-without-country' => ['t' => 'The Man Without a Country', 'a' => 'Edward Everett Hale'],
        'bio-the-whistle' => ['t' => 'Benjamin Franklin', 's' => 1],
        'romance-gift-magi' => ['t' => 'Strictly Business', 'a' => 'O. Henry', 'f' => 'Options', 's' => 1],
        'scifi-the-star' => ['t' => 'The Island of Doctor Moreau', 'a' => 'H. G. Wells'],
        'nature-stickeen' => ['t' => 'Stickeen', 'a' => 'John Muir'],
        'hf-sleepy-hollow' => ['t' => 'The Legend of Sleepy Hollow', 'a' => 'Washington Irving'],
        'hf-horseman-sky' => ['t' => 'In the Midst of Life', 'a' => 'Ambrose Bierce'],
        'adventure-love-of-life' => ['t' => 'Love of Life', 'a' => 'Jack London'],
        'horror-black-cat' => ['t' => 'The Black Cat', 'a' => 'Edgar Allan Poe'],
        'mystery-purloined-letter' => ['t' => 'The Purloined Letter', 'a' => 'Edgar Allan Poe'],
        'scifi-flying-man' => ['t' => 'The War in the Air', 'a' => 'H. G. Wells'],
    ];

    /**
     * Download every declared catalog cover that is missing from the public
     * disk. Only slugs the current manifest still declares are fetched, so a
     * retired story never resurrects an orphan JPG. Unit tests skip entirely
     * (no network); the dev machine keeps covers current via this class or
     * tools/fetch_covers.ps1.
     *
     * @return array{fetched: int, failed: array<int, string>}
     */
    public static function ensureMissing(?array $manifest = null): array
    {
        if (app()->runningUnitTests()) {
            return ['fetched' => 0, 'failed' => []];
        }

        $wanted = collect($manifest ?? AliceLibrary::manifest())
            ->pluck('cover')
            ->filter(fn ($cover) => is_string($cover) && ! str_starts_with($cover, 'http'))
            ->flip();

        $fetched = 0;
        $failed = [];
        $disk = Storage::disk('public');

        foreach (self::BOOKS as $slug => $meta) {
            $cover = 'covers/'.$slug.'.jpg';

            if (! $wanted->has($cover) || $disk->exists($cover)) {
                continue;
            }

            if (self::fetchOne($slug, $meta, $cover)) {
                $fetched++;
            } else {
                $failed[] = $slug;
            }
        }

        return ['fetched' => $fetched, 'failed' => $failed];
    }

    /**
     * Two-pass search (title+author, then fallback title alone), honoring the
     * collision-aware skip depth, exactly like the PowerShell tool.
     */
    private static function fetchOne(string $slug, array $meta, string $cover): bool
    {
        $passes = [
            [$meta['t'], $meta['a'] ?? null],
            [$meta['f'] ?? $meta['t'], null],
        ];

        foreach ($passes as [$title, $author]) {
            $candidates = self::candidateIds($title, $author);

            if (($meta['s'] ?? 0) > 0) {
                $candidates = array_slice($candidates, (int) $meta['s']);
            }

            foreach ($candidates as $coverId) {
                if (self::downloadCandidate($coverId, $cover)) {
                    return true;
                }
                usleep(600_000);
            }

            usleep(800_000);
        }

        return false;
    }

    private static function candidateIds(string $title, ?string $author): array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::UA])
                ->timeout(20)
                ->get('https://openlibrary.org/search.json', array_filter([
                    'title' => $title,
                    'author' => $author,
                    'fields' => 'cover_i',
                    'limit' => 8,
                ], fn ($value) => $value !== null));
        } catch (\Throwable) {
            return [];
        }

        if (! $response->ok()) {
            return [];
        }

        return collect($response->json('docs') ?? [])
            ->pluck('cover_i')
            ->filter()
            ->values()
            ->all();
    }

    private static function downloadCandidate(int $coverId, string $cover): bool
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::UA])
                ->timeout(25)
                ->get('https://covers.openlibrary.org/b/id/'.$coverId.'-L.jpg', ['default' => 'false']);
        } catch (\Throwable) {
            return false;
        }

        if (! $response->ok() || ! self::isValidJpeg($response->body())) {
            return false;
        }

        Storage::disk('public')->put($cover, $response->body());

        return true;
    }

    /**
     * Same countermeasures as the PowerShell script: min size, JPEG magic
     * bytes and real portrait dimensions so Open Library blank stubs never
     * land on disk.
     */
    private static function isValidJpeg(string $bytes): bool
    {
        if (strlen($bytes) < self::MIN_BYTES
            || ! str_starts_with($bytes, "\xFF\xD8")) {
            return false;
        }

        $info = @getimagesizefromstring($bytes);

        return $info !== false
            && $info[0] >= self::MIN_WIDTH
            && $info[1] >= self::MIN_HEIGHT;
    }
}
