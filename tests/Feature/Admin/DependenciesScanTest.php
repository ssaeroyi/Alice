<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\DictionaryEntry;
use App\Models\User;
use App\Support\ProcessRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function depsScanAdmin(): User
{
    return User::factory()->create(['role' => Role::Admin]);
}

/**
 * Seed the local dictionary so the WordNet asset row reads healthy on the
 * Dependencies tab (Local assets panel).
 */
function depsScanAuthoring(): void
{
    depsScanSeedDictionary();
}

function depsScanSeedDictionary(): void
{
    DictionaryEntry::create([
        'lemma' => 'test',
        'pos' => 'noun',
        'sense_num' => 1,
        'definition' => 'a fixture lemma so the local dictionary reads as healthy',
        'freq' => 1,
    ]);
}

/**
 * Swap in a scripted ProcessRunner so real composer/npm is never invoked. The
 * 'outdated' scenario marks one composer and one npm package as behind so the
 * update flow has something to act on.
 */
function depsScanFake(string $scenario = 'healthy'): void
{
    app()->instance(ProcessRunner::class, new class($scenario) extends ProcessRunner
    {
        public function __construct(public string $scenario) {}

        public array $composerUpdateCalls = [];

        public function composerUpdate(string $package, int $timeoutSeconds = 300): array
        {
            $this->composerUpdateCalls[] = $package;

            return ['ok' => true, 'output' => "Upgrading {$package}...\nNothing to install or update", 'timed_out' => false, 'error' => ''];
        }

        public array $npmUpdateCalls = [];

        public function npmUpdate(string $package, int $timeoutSeconds = 120): array
        {
            $this->npmUpdateCalls[] = $package;

            return ['ok' => true, 'output' => 'updated 1 package in 1s', 'timed_out' => false, 'error' => ''];
        }

        public int $npmBuildCalls = 0;

        public function npmBuild(int $timeoutSeconds = 120): array
        {
            $this->npmBuildCalls++;

            return ['ok' => true, 'output' => 'vite build succeeded', 'timed_out' => false, 'error' => ''];
        }

        public function capture(array $command, int $timeoutSeconds = 30): array
        {
            $binary = $command[0] ?? '';
            $flag = $command[1] ?? '';

            if ($binary === 'composer') {
                if ($flag === '--version') {
                    return ['ok' => true, 'output' => 'Composer version 2.7.0', 'timed_out' => false, 'error' => ''];
                }

                if ($flag === 'show') {
                    return ['ok' => true, 'output' => json_encode([
                        'installed' => [
                            ['name' => 'laravel/framework', 'version' => 'v13.17.0'],
                            ['name' => 'laravel/tinker', 'version' => 'v3.0.2'],
                            ['name' => 'fakerphp/faker', 'version' => 'v1.23.1'],
                        ],
                        'dev-package-names' => ['fakerphp/faker'],
                    ]), 'timed_out' => false, 'error' => ''];
                }

                if ($flag === 'outdated') {
                    $outdated = $this->scenario === 'outdated'
                        ? ['laravel/framework' => ['latest' => ['version' => 'v13.18.0']]]
                        : [];

                    return ['ok' => true, 'output' => json_encode(['installed' => $outdated]), 'timed_out' => false, 'error' => ''];
                }

                return ['ok' => true, 'output' => '{"installed":{}}', 'timed_out' => false, 'error' => ''];
            }

            if ($binary === 'npm') {
                if ($flag === '--version') {
                    return ['ok' => true, 'output' => '10.5.0', 'timed_out' => false, 'error' => ''];
                }

                if ($flag === 'ls') {
                    return ['ok' => true, 'output' => json_encode([
                        'dependencies' => [
                            'vite' => ['version' => '8.0.0', 'dev' => true],
                            'chart.js' => ['version' => '4.5.1'],
                            'howler' => ['version' => '2.2.4'],
                        ],
                    ]), 'timed_out' => false, 'error' => ''];
                }

                if ($flag === 'outdated') {
                    $outdated = $this->scenario === 'outdated'
                        ? ['vite' => ['current' => '8.0.0', 'latest' => '8.1.0']]
                        : [];

                    return ['ok' => true, 'output' => json_encode($outdated), 'timed_out' => false, 'error' => ''];
                }

                return ['ok' => true, 'output' => '{}', 'timed_out' => false, 'error' => ''];
            }

            return ['ok' => false, 'output' => '', 'timed_out' => false, 'error' => 'Unknown command'];
        }
    });
}

test('process runner reports subprocess timeouts as timed out, not failures', function () {
    $result = app(ProcessRunner::class)->capture(['php', '-r', 'usleep(4000000);'], 1);

    expect($result['ok'])->toBeFalse()
        ->and($result['timed_out'])->toBeTrue()
        ->and($result['error'])->toContain('Timed out');
});

test('process runner keeps structured JSON intact well past the old 4 KB cap', function () {
    $result = app(ProcessRunner::class)->capture(['php', '-r', 'echo json_encode(array_fill(0, 5000, null));'], 10);

    expect($result['ok'])->toBeTrue()
        ->and(strlen($result['output']))->toBeGreaterThan(4096)
        ->and(json_decode($result['output']))->toBeArray();
});

test('spawned subprocess always receives a resolvable SystemRoot on Windows', function () {
    $root = getenv('SystemRoot');
    $drive = getenv('SystemDrive');

    try {
        putenv('SystemRoot=');
        putenv('SystemDrive=');

        $result = app(ProcessRunner::class)->capture([PHP_BINARY, '-r', 'echo (string) getenv("SystemRoot");'], 10);
    } finally {
        if ($drive) {
            putenv("SystemDrive={$drive}");
        }
        if ($root) {
            putenv("SystemRoot={$root}");
        }
    }

    expect($result['ok'])->toBeTrue()
        ->and($result['output'])->not->toBeEmpty()
        ->and(is_dir($result['output']))->toBeTrue();
})->skip(PHP_OS_FAMILY !== 'Windows', 'Windows-only Winsock invariant');

test('the dependencies tab hides the narration panels on every machine', function () {
    depsScanAuthoring();
    depsScanFake('healthy');
    depsScanAdmin();

    $this->actingAs(depsScanAdmin())
        ->get(route('admin.status'))
        ->assertOk()
        ->assertDontSee('Narration toolchain')
        ->assertDontSee('Audio stamps')
        ->assertDontSee('Run maintenance')
        ->assertDontSee('Pip update outlook')
        ->assertDontSee('Scheduled dependency scan')
        ->assertSee('Local assets')
        ->assertSee('Packages');
});

test('the dependencies tab stays complete on production machines', function () {
    depsScanFake();
    config(['stories.narration.local_generator' => null]);
    depsScanSeedDictionary();
    depsScanAdmin();

    $this->actingAs(depsScanAdmin())
        ->get(route('admin.status'))
        ->assertOk()
        ->assertDontSee('Narration toolchain')
        ->assertDontSee('Audio stamps')
        ->assertDontSee('Scheduled dependency scan')
        ->assertSee('Local assets')
        ->assertSee('Packages');
});

test('the packages panel lists every direct dependency with its installed version', function () {
    depsScanAuthoring();
    depsScanFake('healthy');
    depsScanAdmin();

    $this->actingAs(depsScanAdmin())
        ->get(route('admin.status'))
        ->assertOk()
        ->assertSee('PHP · composer')
        ->assertSee('Frontend · npm')
        ->assertSee('laravel/framework')
        ->assertSee('v13.17.0')
        ->assertSee('fakerphp/faker')
        ->assertSee('(dev)')
        ->assertSee('chart.js')
        ->assertSee('vite')
        ->assertSee('All 3 direct dependencies are up to date.');
});

test('outdated packages show a version-pair pill and an update button, current ones fold away', function () {
    depsScanAuthoring();
    depsScanFake('outdated');
    depsScanAdmin();

    $this->actingAs(depsScanAdmin())
        ->get(route('admin.status'))
        ->assertOk()
        ->assertSee('v13.17.0 → v13.18.0')
        ->assertSee('8.0.0 → 8.1.0')
        ->assertSee('Show 2 current')
        ->assertSee('1 outdated')
        ->assertSee('Update');
});

test('the refresh route rebuilds the package cache and returns to the tab', function () {
    depsScanAuthoring();
    depsScanFake('healthy');
    depsScanAdmin();

    $this->actingAs(depsScanAdmin())
        ->post(route('admin.status.packages.refresh'))
        ->assertRedirect()
        ->assertSessionHas('toast', fn (array $toast) => $toast['type'] === 'success'
            && str_contains($toast['message'], 'refreshed'));
});

test('updating a composer package runs composer update and audits it', function () {
    depsScanAuthoring();
    depsScanFake('outdated');
    $admin = depsScanAdmin();

    $this->actingAs($admin)
        ->post(route('admin.status.packages.update'), ['ecosystem' => 'composer', 'package' => 'laravel/framework'])
        ->assertRedirect()
        ->assertSessionHas('toast', fn (array $toast) => $toast['type'] === 'success'
            && str_contains($toast['message'], 'laravel/framework'));

    $log = AuditLog::where('action', 'system.packages.composer-updated')->first();
    expect($log->meta['package'])->toBe('laravel/framework')
        ->and($log->meta['ok'])->toBeTrue();
});

test('updating an npm package also rebuilds the frontend bundle', function () {
    depsScanAuthoring();
    depsScanFake('outdated');
    $runner = app(ProcessRunner::class);

    $this->actingAs(depsScanAdmin())
        ->post(route('admin.status.packages.update'), ['ecosystem' => 'npm', 'package' => 'vite'])
        ->assertRedirect()
        ->assertSessionHas('toast', fn (array $toast) => $toast['type'] === 'success');

    expect($runner->npmUpdateCalls)->toBe(['vite'])
        ->and($runner->npmBuildCalls)->toBe(1);
});

test('updating a package that is not a known direct dependency is refused', function () {
    depsScanAuthoring();
    depsScanFake('healthy');
    $admin = depsScanAdmin();

    $this->actingAs($admin)
        ->post(route('admin.status.packages.update'), ['ecosystem' => 'composer', 'package' => 'not-a-real/package'])
        ->assertRedirect()
        ->assertSessionHas('toast', fn (array $toast) => $toast['type'] === 'error'
            && str_contains($toast['message'], 'not a known direct'));

    expect(AuditLog::where('action', 'system.packages.composer-updated')->exists())->toBeFalse();
});

test('the packages update route rejects an unknown ecosystem', function () {
    depsScanAuthoring();
    depsScanFake('healthy');
    depsScanAdmin();

    $this->actingAs(depsScanAdmin())
        ->post(route('admin.status.packages.update'), ['ecosystem' => 'pip', 'package' => 'kokoro'])
        ->assertSessionHasErrors('ecosystem');
});

test('spawned subprocess receives a complete Windows environment even when the parent is stripped', function () {
    $keep = array_intersect_key(getenv(), array_flip([
        'SystemRoot', 'SystemDrive', 'ComSpec', 'Path', 'PATHEXT', 'TEMP', 'TMP',
        'USERPROFILE', 'HOMEDRIVE', 'HOMEPATH', 'APPDATA', 'LOCALAPPDATA', 'OS',
    ]));

    try {
        foreach (array_keys($keep) as $key) {
            putenv("{$key}=");
        }

        $result = app(ProcessRunner::class)->capture([
            PHP_BINARY,
            '-r',
            'echo json_encode(["SystemRoot"=>getenv("SystemRoot"),"USERPROFILE"=>getenv("USERPROFILE"),"Path"=>getenv("Path"),"TEMP"=>getenv("TEMP")]);',
        ], 10);
    } finally {
        foreach ($keep as $key => $value) {
            if ($value !== null) {
                putenv("{$key}={$value}");
            }
        }
    }

    $payload = json_decode($result['output'], true);

    expect($result['ok'])->toBeTrue()
        ->and(is_string($payload['SystemRoot'] ?? null) && $payload['SystemRoot'] !== '')->toBeTrue()
        ->and(is_string($payload['USERPROFILE'] ?? null) && $payload['USERPROFILE'] !== '')->toBeTrue()
        ->and(is_string($payload['Path'] ?? null) && str_contains($payload['Path'], $payload['SystemRoot'].'\\System32'))->toBeTrue()
        ->and(is_string($payload['TEMP'] ?? null) && $payload['TEMP'] !== '')->toBeTrue();
})->skip(PHP_OS_FAMILY !== 'Windows', 'Windows-only environment reconstruction');
