<?php

namespace App\Support;

use App\Models\DictionaryEntry;

/**
 * Real, on-disk checks for the offline tools bundled with Alice (the
 * status page's "Local tools" panel). Nothing here makes a network call:
 * every tool is either present on this server or not, so the panel tells
 * the truth about files and imported data instead of assuming.
 */
class LocalServices
{
    /**
     * @return array<string, array{name: string, ok: bool, tone: string, status: string, detail: string}>
     */
    public static function check(): array
    {
        return [
            'dictionary' => static::dictionary(),
            'kokoro' => static::kokoro(),
            'vosk' => static::vosk(),
        ];
    }

    private static function dictionary(): array
    {
        $files = ['data.noun', 'data.verb', 'data.adj', 'data.adv', 'cntlist.rev'];
        $missing = collect($files)
            ->filter(fn (string $file) => ! is_file(database_path('dictionary/'.$file)))
            ->values();

        try {
            $entries = DictionaryEntry::count();
        } catch (\Throwable) {
            $entries = 0;
        }

        $ok = $missing->isEmpty() && $entries > 0;

        $detail = match (true) {
            $ok => 'Local WordNet 3.1 dump on disk with '.number_format($entries).' lemmas imported — lookups run fully offline.',
            $missing->isNotEmpty() && $entries === 0 => 'Raw dump missing (`'.implode('`, `', $missing->all()).'`). Place it in `database/dictionary/` then run `php artisan alice:dictionary:import`.',
            $missing->isNotEmpty() => 'Raw dump missing `'.implode('`, `', $missing->all()).'` — the '.number_format($entries).' already-imported lemmas still serve lookups.',
            default => 'Dump is on disk but the dictionary table is empty — run `php artisan alice:dictionary:import`.',
        };

        return [
            'name' => 'WordNet dictionary',
            'ok' => $ok,
            'tone' => $ok ? 'ok' : ($entries > 0 ? 'warn' : 'crit'),
            'status' => $ok ? 'Ready' : 'Needs attention',
            'detail' => $detail,
        ];
    }

    private static function kokoro(): array
    {
        $generator = config('stories.narration.local_generator');
        $generatorOk = is_string($generator) && $generator !== '' && is_file($generator);
        $scriptOk = is_file(base_path('tools/narration/generate_narration.py'));

        $ok = $generatorOk && $scriptOk;

        $detail = match (true) {
            $ok => 'Generator at `'.$generator.'` with `tools/narration/generate_narration.py` present.',
            $generator && ! $generatorOk => 'NARRATION_LOCAL_GENERATOR points at `'.$generator.'`, which is not a file'
                .($scriptOk ? '' : ' (and the generate script `tools/narration/generate_narration.py` is missing)').'.',
            $generatorOk && ! $scriptOk => 'Generator at `'.$generator.'` is a valid interpreter, but `tools/narration/generate_narration.py` is missing.',
            default => 'Offline TTS is disabled. Set `NARRATION_LOCAL_GENERATOR` to a Python interpreter on the authoring machine to enable "Generate narration".',
        };

        return [
            'name' => 'Kokoro (narration voice)',
            'ok' => $ok,
            'tone' => $ok ? 'ok' : ($generator ? 'warn' : 'crit'),
            'status' => $generatorOk ? 'Ready' : ($generator ? 'Path invalid' : 'Not configured'),
            'detail' => $detail,
        ];
    }

    private static function vosk(): array
    {
        $model = public_path('stt/vosk-model-small-en-us-0.15.tar.gz');
        $present = is_file($model);

        return [
            'name' => 'Vosk (offline speech-check)',
            'ok' => $present,
            'tone' => $present ? 'ok' : 'warn',
            'status' => $present ? 'Ready' : 'Not bundled',
            'detail' => $present
                ? 'Offline STT model at `/stt/vosk-model-small-en-us-0.15.tar.gz` — the say-it games grade with no network round-trip.'
                : 'Missing `public/stt/vosk-model-small-en-us-0.15.tar.gz` — the say-it games fall back to the browser speech API instead (still functional, needs a network).',
        ];
    }
}
