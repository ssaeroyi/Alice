<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Starts a detached background process for the local narration generator
 * (TTS_AND_DICTIONARY.md §3.6). This class is the ONLY place a real OS
 * process is spawned, so feature tests swap a recording fake in via the
 * container and never run popen.
 */
class DetachedCommandLauncher
{
    /**
     * @param  string  $command  fully-wrapped shell command (detach + redirect)
     * @param  string  $logKey  relative local-disk path to append failures into
     */
    public function launch(string $command, string $logKey): bool
    {
        try {
            $handle = popen($command, 'r');

            if ($handle === false) {
                report(new \RuntimeException('detached launcher: popen failed to start process'));
                $this->append($logKey, 'PHP popen failed to start the process.'.PHP_EOL);

                return false;
            }

            pclose($handle);

            return true;
        } catch (Throwable $error) {
            report($error);
            $this->append($logKey, $error->getMessage().PHP_EOL);

            return false;
        }
    }

    private function append(string $logKey, string $line): void
    {
        try {
            Storage::disk('local')->append($logKey, $line);
        } catch (Throwable) {
            // Failure logging must never break the calling request.
        }
    }
}
