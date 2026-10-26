<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Loads WordNet 3.1 (database/dictionary/, gitignored - download once per
 * machine) into dictionary_entries + dictionary_forms. Idempotent
 * truncate-and-load inside one transaction; safe to re-run any time.
 *
 * Parsed files: data.{noun,verb,adj,adv} (lemma + synset glosses),
 * cntlist.rev.* (usage tag counts -> frequency badge), exc.*
 * (irregular inflections). Regular inflections are generated for every
 * lemma so "running"/"rained" resolve even when WordNet's exception maps
 * skip them. Tests use micro fixtures via --path; the suite never needs
 * the real dump.
 */
class DictionaryImport extends Command
{
    protected $signature = 'alice:dictionary:import {--path= : Directory containing data.*, cntlist.rev.*, exc.* (default database/dictionary)} {--chunk=500}';

    protected $description = 'Import the offline WordNet 3.1 dictionary into the database';

    private const POS_FILES = ['noun' => 'noun', 'verb' => 'verb', 'adj' => 'adj', 'adv' => 'adv'];

    public function handle(): int
    {
        $dir = rtrim($this->option('path') ?: database_path('dictionary'), '/\\');
        $chunk = max(50, (int) $this->option('chunk'));

        if (! is_dir($dir)) {
            $this->error("Dictionary directory not found: {$dir}");

            return self::FAILURE;
        }

        foreach (['data.noun', 'data.verb', 'data.adj', 'data.adv'] as $file) {
            if (! file_exists($dir.DIRECTORY_SEPARATOR.$file)) {
                $this->error("Missing required file: {$file} in {$dir}");

                return self::FAILURE;
            }
        }

        if (! file_exists($dir.DIRECTORY_SEPARATOR.'cntlist.rev')) {
            $this->error("Missing required file: cntlist.rev in {$dir}");

            return self::FAILURE;
        }

        $started = microtime(true);

        try {
            DB::transaction(function () use ($dir, $chunk) {
                DB::table('dictionary_forms')->delete();
                DB::table('dictionary_entries')->delete();

                $lemmaIds = [];

                foreach (self::POS_FILES as $pos => $file) {
                    $lemmaIds = $this->importDataFile($pos, $dir.DIRECTORY_SEPARATOR."data.{$file}", $chunk, $lemmaIds);
                }

                // The distribution ships ONE combined cntlist.rev; sense
                // keys carry the POS digit (1=noun 2=verb 3=adj 4=adv
                // 5=satellite-adj).
                $this->applyTagCounts($dir.DIRECTORY_SEPARATOR.'cntlist.rev', $lemmaIds);

                foreach (self::POS_FILES as $pos => $file) {
                    $exc = $dir.DIRECTORY_SEPARATOR."{$file}.exc";

                    if (file_exists($exc)) {
                        $this->importExceptions($exc, $lemmaIds);
                    }
                }

                $this->generateRegularForms($lemmaIds, $chunk);

                $this->seedFunctionWords($chunk);
            });
        } catch (Throwable $e) {
            $this->error('Import failed and was rolled back: '.$e->getMessage());

            return self::FAILURE;
        }

        $entries = DB::table('dictionary_entries')->count();
        $forms = DB::table('dictionary_forms')->count();

        $this->info(sprintf(
            'Imported %s entries / %s forms in %.1fs.',
            number_format($entries), number_format($forms), microtime(true) - $started,
        ));

        return self::SUCCESS;
    }

    /**
     * One data.* line = one synset: offset lexfile ss_type wordcount
     * word%sense ... pointers... | gloss. Each lemma on the line gets an
     * entry row numbered by its own running sense order.
     */
    private function importDataFile(string $pos, string $path, int $chunk, array $lemmaIds): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new \RuntimeException("Cannot read {$path}");
        }

        $senseOrder = [];
        $buffer = [];
        $lineNo = 0;

        while (($line = fgets($handle)) !== false) {
            $lineNo++;

            if ($line === '' || $line[0] === ' ') {
                continue;
            }

            [$head, $gloss] = array_pad(explode(' | ', trim($line), 2), 2, null);

            if ($gloss === null || trim($gloss) === '') {
                continue;
            }

            [$definition, $example] = self::splitGloss(trim($gloss));

            if ($definition === '') {
                continue;
            }

            $fields = explode(' ', $head);

            if (count($fields) < 5 || ! ctype_xdigit($fields[0])) {
                continue;
            }

            // Format: ... w_cnt word lex_id [word lex_id ...] pointers | gloss.
            // Each lemma carries its own lex_id digit - walk in pairs.
            $wordCount = hexdec($fields[3]);
            $words = [];

            for ($i = 0; $i < $wordCount; $i++) {
                $candidate = $fields[4 + $i * 2] ?? null;

                if ($candidate !== null && $candidate !== '') {
                    $words[] = $candidate;
                }
            }

            foreach ($words as $token) {
                $token = preg_replace('/\(.+\)$/', '', $token);
                $token = str_replace('_', ' ', strtok($token, '%') ?: '');
                $token = mb_strtolower($token);

                if ($token === '' || ! preg_match("/^[\p{L}\p{N} '-]+$/u", $token)) {
                    continue;
                }

                $senseOrder[$token] = ($senseOrder[$token] ?? 0) + 1;

                $buffer[] = [
                    'lemma' => $token,
                    'pos' => $pos,
                    'sense_num' => $senseOrder[$token],
                    'definition' => mb_substr($definition, 0, 2000),
                    'example' => $example === null ? null : mb_substr($example, 0, 500),
                    'freq' => 0,
                ];

                if (! isset($lemmaIds["{$token}|{$pos}"])) {
                    $lemmaIds["{$token}|{$pos}"] = null;
                }

                if (count($buffer) >= $chunk) {
                    $this->flushEntries($buffer, $lemmaIds);
                    $buffer = [];
                }
            }
        }

        fclose($handle);
        $this->flushEntries($buffer, $lemmaIds);

        return $lemmaIds;
    }

    private function flushEntries(array $buffer, array &$lemmaIds): void
    {
        if ($buffer === []) {
            return;
        }

        foreach ($buffer as $row) {
            DB::table('dictionary_entries')->insert($row);

            if ($lemmaIds["{$row['lemma']}|{$row['pos']}"] === null) {
                $lemmaIds["{$row['lemma']}|{$row['pos']}"] = DB::getPdo()->lastInsertId();
            }
        }
    }

    /**
     * Glosses read "definition clause; clause; \"example one\"". Quoted
     * tails become the example sentence; the remainder (parenthetical
     * qualifiers like "(usually followed by `to')" stripped) is kept as
     * the definition.
     *
     * @return array{0: string, 1: ?string}
     */
    private static function splitGloss(string $gloss): array
    {
        $example = null;

        if (preg_match('/"([^"]{2,300})"/', $gloss, $m)) {
            $candidate = trim(preg_replace('/\s+/', ' ', $m[1]) ?? '');

            if ($candidate !== '' && preg_match("/^[\p{L}\p{N} ,.;:'!?()\-]+$/u", $candidate) === 1) {
                $example = $candidate;
            }

            $gloss = trim(str_replace($m[0], '', $gloss));
        }

        $definition = preg_replace('/^\([^)]*\)\s*/', '', $gloss) ?? $gloss;
        $definition = preg_replace('/\s+/', ' ', trim($definition, " ;\t\r\n")) ?? '';

        return [$definition, $example];
    }

    /**
     * Combined cntlist.rev lines: "<sense_key> <tag_count> <sense_number>"
     * where the sense key is lemma%ss_type:lexfile:lex_id[:head:id] with
     * ss_type 1=noun 2=verb 3=adj 4=adv 5=satellite-adj. Counts sum per
     * lemma+pos across every tagged sense.
     */
    private function applyTagCounts(string $path, array $lemmaIds): void
    {
        if (! file_exists($path)) {
            return;
        }

        $posByDigit = ['1' => 'noun', '2' => 'verb', '3' => 'adj', '4' => 'adv', '5' => 'adj'];
        $counts = [];
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return;
        }

        while (($line = fgets($handle)) !== false) {
            $fields = preg_split('/\s+/', trim($line));

            if (count($fields) < 3 || ! ctype_digit((string) $fields[1])) {
                continue;
            }

            $key = (string) $fields[0];
            $percent = strpos($key, '%');

            if ($percent === false || $percent === 0) {
                continue;
            }

            $lemma = str_replace('_', ' ', mb_strtolower(substr($key, 0, $percent)));
            $pos = $posByDigit[substr($key, $percent + 1, 1)] ?? null;

            if ($pos === null || ! isset($lemmaIds["{$lemma}|{$pos}"])) {
                continue;
            }

            $counts["{$lemma}|{$pos}"] = ($counts["{$lemma}|{$pos}"] ?? 0) + (int) $fields[1];
        }

        fclose($handle);

        foreach ($counts as $lemmaPos => $count) {
            [$lemma, $pos] = explode('|', $lemmaPos);

            DB::table('dictionary_entries')
                ->where('lemma', $lemma)
                ->where('pos', $pos)
                ->update(['freq' => $count]);
        }
    }

    /**
     * Function words close the gap WordNet leaves by design (it defines
     * content words only). Bundled data file, seeded as pos='function'
     * rows; generateRegularForms never touches them because they are not
     * part of $lemmaIds.
     */
    private function seedFunctionWords(int $chunk): void
    {
        $supplement = require __DIR__.'/data/function-words.php';
        $rows = [];

        foreach ($supplement as $lemma => $definition) {
            $rows[] = [
                'lemma' => $lemma,
                'pos' => 'function',
                'sense_num' => 1,
                'definition' => $definition,
                'example' => null,
                // These top every real frequency list - badge COMMON.
                'freq' => 9999,
            ];

            if (count($rows) >= $chunk) {
                DB::table('dictionary_entries')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('dictionary_entries')->insert($rows);
        }
    }

    /**
     * exc.* lines: "inflected replacement [replacement...]". Every
     * replacement that exists as a lemma gets a form row flagged irregular.
     */
    private function importExceptions(string $path, array $lemmaIds): void
    {
        $rows = [];
        $seen = [];

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new \RuntimeException("Cannot read {$path}");
        }

        while (($line = fgets($handle)) !== false) {
            $fields = preg_split('/\s+/', trim($line));

            if (count($fields) < 2) {
                continue;
            }

            $form = str_replace('_', ' ', mb_strtolower(array_shift($fields)));

            foreach ($fields as $replacement) {
                $replacement = str_replace('_', ' ', mb_strtolower($replacement));

                foreach (self::POS_FILES as $pos => $_) {
                    $key = "{$replacement}|{$pos}";

                    if (! isset($lemmaIds[$key]) || $lemmaIds[$key] === null) {
                        continue;
                    }

                    $rowKey = "{$form}|{$lemmaIds[$key]}";

                    if (isset($seen[$rowKey])) {
                        continue;
                    }

                    $seen[$rowKey] = true;
                    $rows[] = ['form' => $form, 'entry_id' => $lemmaIds[$key], 'irregular' => true];
                }
            }
        }

        fclose($handle);

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('dictionary_forms')->insertOrIgnore($chunk);
        }
    }

    /**
     * Generated regular inflections fill the gaps WordNet's exception maps
     * leave (most -ing/-ed forms). Irregular rows already present win:
     * inserts use insertOrIgnore against unique(form, entry_id).
     */
    private function generateRegularForms(array $lemmaIds, int $chunk): void
    {
        $rows = [];
        $insert = function (string $form, ?int $entryId) use (&$rows, $chunk) {
            if ($entryId === null || $form === '') {
                return;
            }

            $rows[] = ['form' => $form, 'entry_id' => $entryId, 'irregular' => false];

            if (count($rows) >= $chunk) {
                DB::table('dictionary_forms')->insertOrIgnore($rows);
                $rows = [];
            }
        };

        foreach ($lemmaIds as $key => $entryId) {
            [$lemma, $pos] = explode('|', $key);
            $base = str_replace(' ', '', $lemma);

            if ($base !== $lemma || ! preg_match('/^[a-z]+$/', $base)) {
                continue;
            }

            match ($pos) {
                'noun' => $insert(self::pluralize($base), $entryId),
                'verb' => (function () use ($base, $entryId, $insert) {
                    $insert(self::pluralize($base), $entryId);
                    $insert(self::pastTense($base), $entryId);
                    $insert(self::gerund($base), $entryId);
                })(),
                'adj' => (function () use ($base, $entryId, $insert) {
                    $insert(self::comparative($base), $entryId);
                    $insert(self::superlative($base), $entryId);
                })(),
                default => null,
            };
        }

        if ($rows !== []) {
            DB::table('dictionary_forms')->insertOrIgnore($rows);
        }
    }

    private static function pluralize(string $w): string
    {
        return match (true) {
            preg_match('/(s|x|z|ch|sh)$/', $w) === 1 => $w.'es',
            preg_match('/[^aeiou]y$/', $w) === 1 => substr($w, 0, -1).'ies',
            default => $w.'s',
        };
    }

    private static function pastTense(string $w): string
    {
        if (str_ends_with($w, 'e')) {
            return $w.'d';
        }

        if (preg_match('/[^aeiou]y$/', $w) === 1) {
            return substr($w, 0, -1).'ied';
        }

        return $w.'ed';
    }

    private static function gerund(string $w): string
    {
        if (str_ends_with($w, 'ie')) {
            return substr($w, 0, -2).'ying';
        }

        if (str_ends_with($w, 'e') && ! str_ends_with($w, 'ee')) {
            return substr($w, 0, -1).'ing';
        }

        return $w.'ing';
    }

    private static function comparative(string $w): string
    {
        if (preg_match('/[^aeiou]y$/', $w) === 1) {
            return substr($w, 0, -1).'ier';
        }

        if (str_ends_with($w, 'e')) {
            return $w.'r';
        }

        return $w.'er';
    }

    private static function superlative(string $w): string
    {
        if (preg_match('/[^aeiou]y$/', $w) === 1) {
            return substr($w, 0, -1).'iest';
        }

        if (str_ends_with($w, 'e')) {
            return $w.'st';
        }

        return $w.'est';
    }
}
