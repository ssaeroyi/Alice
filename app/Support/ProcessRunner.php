<?php

namespace App\Support;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Runs the short-lived subprocesses behind the status page's "Dependencies"
 * tab: the read-only composer/npm list probes and the within-range update
 * commands. Bounded on purpose — there is no free-form shell here; the surface
 * is a fixed package-manager command plus a regex-validated package name.
 *
 * Everything runs with a hard timeout and the captured output is tail-capped
 * so the admin page can never be handed megabytes of noise. Feature tests
 * swap a recording/short-circuit fake in via the container (same pattern as
 * DetachedCommandLauncher).
 */
class ProcessRunner
{
    /**
     * Tail cap for captured output. Generous enough to keep structured JSON
     * intact for callers that decode it, still bounded so the page never
     * holds megabytes of noise.
     */
    public const OUTPUT_LIMIT = 65536;

    /**
     * Generic read-only capture for the composer/npm probes.
     * The command array is programmer-constructed (never user input) and a
     * non-zero exit is still reported with its captured output so callers can
     * decide how to grade it (npm outdated exits 1 with valid JSON).
     *
     * @param  array<int, string>  $command
     * @return array{ok: bool, output: string, timed_out: bool, error: string}
     */
    public function capture(array $command, int $timeoutSeconds = 30): array
    {
        return $this->run(new Process($command), $timeoutSeconds, allowFailure: true);
    }

    /**
     * `composer update <pkg>` — the everyday developer command. Updates the
     * named direct dependency INSIDE the composer.json constraint and rewrites
     * composer.lock only; the manifest is never touched, so a major-version
     * jump is impossible. The package name is the single varying argument and
     * is regex-validated here so no free-form shell can be reached.
     *
     * @return array{ok: bool, output: string, timed_out: bool, error: string}
     */
    public function composerUpdate(string $package, int $timeoutSeconds = 300): array
    {
        if (! static::validPackageName($package)) {
            return $this->failure('Refusing to run composer update — the package name is not a valid composer package identifier.');
        }

        return $this->run(new Process([
            'composer', 'update', $package, '--with-dependencies', '--no-interaction',
        ]), $timeoutSeconds);
    }

    /**
     * `npm update <pkg>` — the everyday developer command. Installs the newest
     * version the package.json range allows and rewrites package-lock.json
     * only; package.json is never touched. Frontend changes are only served
     * after a fresh `npm run build`, which the caller is expected to chain.
     *
     * @return array{ok: bool, output: string, timed_out: bool, error: string}
     */
    public function npmUpdate(string $package, int $timeoutSeconds = 120): array
    {
        if (! static::validPackageName($package)) {
            return $this->failure('Refusing to run npm update — the package name is not a valid npm package identifier.');
        }

        return $this->run(new Process(['npm', 'update', $package]), $timeoutSeconds);
    }

    /**
     * Rebuild the prebuilt frontend bundle so freshly-updated npm packages are
     * actually what the browser serves (public/build is what runs in
     * production; the dev-server pointer file is never used here).
     *
     * @return array{ok: bool, output: string, timed_out: bool, error: string}
     */
    public function npmBuild(int $timeoutSeconds = 120): array
    {
        return $this->run(new Process(['npm', 'run', 'build']), $timeoutSeconds);
    }

    /**
     * The only per-package argument that can vary across update calls. Accepts
     * plain names (laravel/framework, vite) and scoped npm names
     * (@ai-sdk/openai-compatible) — nothing else can reach the command array.
     */
    private static function validPackageName(string $package): bool
    {
        return preg_match('/\A[a-zA-Z0-9@][a-zA-Z0-9@\/._-]*\z/', $package) === 1
            && ! str_contains($package, '..')
            && ! str_starts_with($package, '-');
    }

    /**
     * @param  array{ok: bool, output: string, timed_out: bool, error: string}  $result
     */
    private function failure(string $message): array
    {
        return ['ok' => false, 'output' => '', 'timed_out' => false, 'error' => $message];
    }

    /**
     * @return array{ok: bool, output: string, timed_out: bool, error: string}
     */
    private function run(Process $process, int $timeoutSeconds, bool $allowFailure = false): array
    {
        try {
            $process->setTimeout($timeoutSeconds);

            if (PHP_OS_FAMILY === 'Windows') {
                $process->setEnv($this->windowsEnvironment());
            }

            $process->run();
        } catch (ProcessTimedOutException) {
            return ['ok' => false, 'output' => static::tail($process->getOutput()), 'timed_out' => true, 'error' => "Timed out after {$timeoutSeconds}s."];
        } catch (Throwable $error) {
            return $this->failure($error->getMessage());
        }

        $output = static::tail($process->getOutput());

        if (! $allowFailure && ! $process->isSuccessful()) {
            return ['ok' => false, 'output' => $output, 'timed_out' => false, 'error' => static::tail($process->getErrorOutput()) ?: 'Command failed.'];
        }

        return ['ok' => true, 'output' => $output, 'timed_out' => false, 'error' => ''];
    }

    /**
     * Environment invariants a spawned child needs on Windows.
     *
     * Some web-server contexts (e.g. Laragon's httpd started as an interactive
     * app) hand children a stripped environment - not just a missing SystemRoot
     * but also no USERPROFILE, Path, TEMP, etc. Winsock then cannot load its
     * provider (`%SystemRoot%\System32\mswsock.dll`) and the first socket call
     * dies with `[WinError 10106]`; torch/HF Hub additionally need `USERPROFILE`
     * for the weights cache, `Path` for tool/DLL lookup and `TEMP` for scratch.
     * Symfony's `Process` only inherits what the parent actually has
     * (Process::getDefaultEnv), so we reconstruct the essential set here from
     * the parent where sane and safe defaults otherwise. Symfony overlays these
     * over the inherited env, so nothing the parent did have is lost.
     */
    private function windowsEnvironment(): array
    {
        $systemRoot = rtrim(getenv('SystemRoot') ?: getenv('WINDIR') ?: 'C:\Windows', '\\');
        $systemDrive = getenv('SystemDrive') ?: substr($systemRoot, 0, 2);
        $system32 = $systemRoot.'\System32';

        $path = (string) getenv('Path');
        if (! preg_match('/(^|;)\Q'.$system32.'\E(;|$)/i', $path)) {
            $path = rtrim($path === '' ? $system32 : $path, ';').';'.$system32;
        }

        $profile = (string) getenv('USERPROFILE');
        if ($profile === '') {
            $profile = $systemDrive.'\\Users\\'.(getenv('USERNAME') ?: 'Public');
        }

        $temp = (string) getenv('TEMP');
        $temp = $temp !== '' ? $temp : ($profile.'\\AppData\\Local\\Temp');

        $homeDrive = getenv('HOMEDRIVE') ?: $systemDrive;
        $homePath = getenv('HOMEPATH') ?: (str_contains($profile, $systemDrive.'\\Users\\') ? '\\'.substr($profile, strlen($systemDrive.'\\Users\\')) : '\\');

        return [
            'SystemRoot' => $systemRoot,
            'SystemDrive' => $systemDrive,
            'ComSpec' => getenv('ComSpec') ?: $system32.'\\cmd.exe',
            'Path' => $path,
            'PATHEXT' => getenv('PATHEXT') ?: '.COM;.EXE;.BAT;.CMD;.VBS;.VBE;.JS;.JSE;.WSF;.WSH;.MSC',
            'TEMP' => $temp,
            'TMP' => getenv('TMP') ?: $temp,
            'USERPROFILE' => $profile,
            'HOMEDRIVE' => $homeDrive,
            'HOMEPATH' => $homePath,
            'APPDATA' => getenv('APPDATA') ?: $profile.'\\AppData\\Roaming',
            'LOCALAPPDATA' => getenv('LOCALAPPDATA') ?: $profile.'\\AppData\\Local',
            'OS' => getenv('OS') ?: 'Windows_NT',
            'NUMBER_OF_PROCESSORS' => getenv('NUMBER_OF_PROCESSORS') ?: '1',
        ];
    }

    private static function tail(string $text): string
    {
        $text = trim($text);

        if (mb_strlen($text) <= static::OUTPUT_LIMIT) {
            return $text;
        }

        return '…'.mb_substr($text, -static::OUTPUT_LIMIT);
    }
}
