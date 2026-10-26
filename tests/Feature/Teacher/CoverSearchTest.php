<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function coverSearchTeacher(): User
{
    return User::factory()->create(['role' => Role::Teacher]);
}

function coverSearchUrl(): string
{
    return route('teacher.stories.cover-search');
}

function coverSearchOpenLibraryResponse(array $coverIds): string
{
    $docs = [];

    foreach ($coverIds as $id) {
        $docs[] = ['title' => 'Pip and the Meadow', 'author_name' => ['Jane Doe'], 'cover_i' => $id];
    }

    return json_encode(['docs' => $docs]);
}

function coverSearchWikimediaResponse(array $thumbUrls): string
{
    $pages = [];

    foreach ($thumbUrls as $index => $url) {
        $pages['page_'.$index] = [
            'title' => 'Pip and the Meadow',
            'thumbnail' => ['source' => $url],
        ];
    }

    return json_encode(['query' => ['pages' => $pages]]);
}

beforeEach(function () {
    $this->teacher = coverSearchTeacher();
    $this->actingAs($this->teacher);
});

it('finds cover candidates from the open library using the story title and author', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response(coverSearchOpenLibraryResponse([9118082, 12000901])),
        'en.wikipedia.org/*' => Http::response(['query' => ['pages' => []]]),
    ]);

    $this->postJson(coverSearchUrl(), [
        'title' => 'Pip and the Meadow',
        'author' => 'Jane Doe',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'results')
        ->assertJsonPath('results.0.url', 'https://covers.openlibrary.org/b/id/9118082-M.jpg')
        ->assertJsonPath('results.0.source', 'Open Library');

    Http::assertSent(function (Request $request) {
        return str_starts_with($request->url(), 'https://openlibrary.org/search.json')
            && $request['q'] === 'Pip and the Meadow Jane Doe'
            && $request['fields'] === 'title,author_name,cover_i';
    });
});

it('falls back to wikimedia thumbnails when the open library has no covers', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response(coverSearchOpenLibraryResponse([])),
        'en.wikipedia.org/*' => Http::response(coverSearchWikimediaResponse([
            'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a1/pip.jpg/400px-pip.jpg',
        ])),
    ]);

    $this->postJson(coverSearchUrl(), [
        'title' => 'Pip and the Meadow',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.url', 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a1/pip.jpg/400px-pip.jpg')
        ->assertJsonPath('results.0.source', 'Wikimedia');
});

it('drops thumbnail urls from non-allowlisted hosts', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response(coverSearchOpenLibraryResponse([])),
        'en.wikipedia.org/*' => Http::response(coverSearchWikimediaResponse([
            'https://evil.example/cover.jpg',
            'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a1/pip.jpg/400px-pip.jpg',
        ])),
    ]);

    $this->postJson(coverSearchUrl(), [
        'title' => 'Pip and the Meadow',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.url', 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a1/pip.jpg/400px-pip.jpg');
});

it('caps the candidates at the configured maximum', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response(coverSearchOpenLibraryResponse([1, 2, 3, 4, 5, 6])),
        'en.wikipedia.org/*' => Http::response(coverSearchWikimediaResponse([
            'https://upload.wikimedia.org/wikipedia/commons/1/a/a.jpg',
            'https://upload.wikimedia.org/wikipedia/commons/1/b/b.jpg',
            'https://upload.wikimedia.org/wikipedia/commons/1/c/c.jpg',
            'https://upload.wikimedia.org/wikipedia/commons/1/d/d.jpg',
            'https://upload.wikimedia.org/wikipedia/commons/1/e/e.jpg',
            'https://upload.wikimedia.org/wikipedia/commons/1/f/f.jpg',
        ])),
    ]);

    $this->postJson(coverSearchUrl(), [
        'title' => 'Pip and the Meadow',
    ])
        ->assertOk()
        ->assertJsonCount(8, 'results');
});

it('reports an empty list when the cover services fail', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response([], 500),
        'en.wikipedia.org/*' => Http::response([], 500),
    ]);

    $this->postJson(coverSearchUrl(), [
        'title' => 'Pip and the Meadow',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(0, 'results');
});

it('rejects a missing or overlong title', function () {
    Http::fake(['*' => Http::response(coverSearchOpenLibraryResponse([9118082]))]);

    $this->postJson(coverSearchUrl(), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('title');

    $this->postJson(coverSearchUrl(), ['title' => str_repeat('a', 201)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('title');
});

it('students cannot search for covers', function () {
    $student = User::factory()->create(['role' => Role::Student]);
    $this->actingAs($student);

    $this->postJson(coverSearchUrl(), ['title' => 'Pip and the Meadow'])->assertForbidden();
});
