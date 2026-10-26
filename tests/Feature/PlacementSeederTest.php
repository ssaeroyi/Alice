<?php

use App\Enums\PlacementAttemptStatus;
use App\Enums\PlacementStep;
use App\Models\PlacementAttempt;
use App\Models\PlacementResponse;
use App\Models\PlacementSet;
use App\Models\User;
use Database\Seeders\PlacementSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('placement seeder loads five sets with complete content', function () {
    $this->seed(PlacementSeeder::class);

    expect(PlacementSet::count())->toBe(5);

    $set = PlacementSet::where('slug', 'ant-and-the-grasshopper')->firstOrFail();

    expect($set->title)->toBe('The Ant and the Grasshopper');
    expect($set->author)->toBe('Aesop');
    expect($set->source_url)->toBe('https://www.gutenberg.org/ebooks/21');
    expect($set->content)->toHaveKeys(['vocab', 'cloze', 'comprehension']);
    expect($set->content['vocab'])->toHaveCount(10);
    expect($set->content['cloze']['blanks'])->toHaveCount(10);
    expect($set->content['comprehension']['questions'])->toHaveCount(5);
});

test('placement seeder is idempotent', function () {
    $this->seed(PlacementSeeder::class);
    $this->seed(PlacementSeeder::class);

    expect(PlacementSet::count())->toBe(5);
});

test('an attempt belongs to a user and a set and holds step responses', function () {
    $this->seed(PlacementSeeder::class);

    $user = User::factory()->create();
    $set = PlacementSet::first();

    $attempt = PlacementAttempt::create([
        'user_id' => $user->id,
        'set_id' => $set->id,
        'status' => PlacementAttemptStatus::InProgress,
        'started_at' => now(),
    ]);

    $response = PlacementResponse::create([
        'attempt_id' => $attempt->id,
        'step' => PlacementStep::Vocab,
        'payload' => ['vocab_1' => 'A field of grass'],
    ]);

    expect($attempt->user->is($user))->toBeTrue();
    expect($attempt->set->is($set))->toBeTrue();
    expect($attempt->responses)->toHaveCount(1);
    expect($attempt->responses->first()->is($response))->toBeTrue();
    expect($attempt->responses->first()->step)->toBe(PlacementStep::Vocab);
    expect($attempt->responses->first()->payload['vocab_1'])->toBe('A field of grass');
});

test('a user can have only one placement attempt', function () {
    $this->seed(PlacementSeeder::class);

    $user = User::factory()->create();
    $set = PlacementSet::first();

    PlacementAttempt::create([
        'user_id' => $user->id,
        'set_id' => $set->id,
    ]);

    expect(fn () => PlacementAttempt::create([
        'user_id' => $user->id,
        'set_id' => $set->id,
    ]))->toThrow(QueryException::class);
});
