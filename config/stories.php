<?php

return [

    /*
    |--------------------------------------------------------------------------
    | License presets (story authoring)
    |--------------------------------------------------------------------------
    |
    | The license a teacher picks when adding a story. "Original work"
    | covers stories written in-house; the rest are the common open
    | licenses for sourced texts. Stored as the human-readable label so
    | the story table and the audit trail read naturally.
    |
    */

    'licenses' => [
        'Original work',
        'Public domain',
        'CC BY',
        'CC BY-SA',
        'CC BY-NC',
        'CC BY-NC-SA',
    ],

    /*
    |--------------------------------------------------------------------------
    | Story quiz (batch 5)
    |--------------------------------------------------------------------------
    |
    | questions_max caps the pool a teacher can attach to one story. The
    | student quiz (reading batches) draws a balanced random set from the
    | pool — quiz_recommended is the floor for a meaningful draw.
    |
    */

    'questions_max' => 20,

    'quiz_recommended' => 5,

    'sections_max' => 8,

    /*
    |--------------------------------------------------------------------------
    | Narration metadata (TTS_AND_DICTIONARY.md §3)
    |--------------------------------------------------------------------------
    |
    | Purely informational: stamped into export.json and the per-story
    | manifests so audits can tell which voice/model produced a batch.
    | Generation itself happens offline on the dev machine; production
    | only serves the resulting static MP3s.
    |
    */

    'narration' => [
        'voice' => env('NARRATION_VOICE', 'af_heart'),
        'model' => env('NARRATION_MODEL', 'kokoro-v1.0'),

        /*
        | Tokens guarded feed used once Alice is hosted: the dev machine pulls
        | wanted stories + export text and pushes progress heartbeats over
        | these routes. When the token is unset the routes 404 (localhost).
        */
        'feed_token' => env('NARRATION_FEED_TOKEN'),

        /*
        | Authoring-machine auto-run. When set to an absolute interpreter path,
        | the teacher "Generate narration" button spawns the repo's
        | tools/narration/generate_narration.py in the background for that one
        | story (--only <slug>) instead of queueing it for feed pickup —
        | the pill then flows Queued -> Generating N of M -> Narrated by itself.
        | Leave EMPTY on any hosted/deployed site: the button keeps today's
        | feed-mode handoff and nothing ever spawns a process.
        */
        'local_generator' => env('NARRATION_LOCAL_GENERATOR'),

        /* Where a spawned auto-run writes its stdout/stderr, per slug. */
        'local_generator_log' => env('NARRATION_LOCAL_GENERATOR_LOG'),

        /*
        | A running generator heartbeat older than this many seconds counts
        | as stalled (the authoring machine is offline or failed).
        */
        'stall_seconds' => (int) env('NARRATION_STALL_SECONDS', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Grammar & spelling check (batch 6)
    |--------------------------------------------------------------------------
    |
    | The story text is checked by harper.js, a WebAssembly grammar
    | engine that runs entirely in the teacher's browser. The binary is
    | served from public/ (no external service, no API keys, no rate
    | limits) and the check never blocks saving.
    |
    */

    'grammar' => [
        'engine' => 'harper.js',
        'binary' => '/vendor/harper/harper.wasm',
        'max_chars' => 20000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Story content limits (batch 9, part B)
    |--------------------------------------------------------------------------
    |
    | The teacher-authored story text is capped so every final story stays
    | inside the grammar check's window (the check handles ~20,000
    | characters) and short enough for a student to finish in one sitting.
| The caps apply to teacher-edited content only — imported long books
     | keep their full text untouched, and saving a story without touching
     | the text never trips the limit.
    |
    */

    'content' => [
        'max_words' => 3200,
        'max_chars' => 20000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cover search (batch 11, part D)
    |--------------------------------------------------------------------------
    |
    | "Get cover image from web" looks up cover candidates using the
    | story's own title and author — the teacher never types keywords.
    | Open Library book covers come first (ideal for classics), with
    | Wikipedia article thumbnails as the keyless fallback. The Open
    | Library thumbnail URL is rebuilt locally from the numeric cover id
    | and every returned URL must survive the host allowlist — a
    | third-party response can never point Alice (or the student page)
    | at an arbitrary host (no SSRF).
    |
    */

    'cover' => [
        'open_library' => [
            'search_endpoint' => 'https://openlibrary.org/search.json',
            'cover_url' => 'https://covers.openlibrary.org/b/id/{id}-M.jpg',
        ],
        'wikipedia' => [
            'endpoint' => 'https://en.wikipedia.org/w/api.php',
        ],
        'max_results' => 8,
        'timeout' => 8,
        'allowed_hosts' => [
            'openlibrary.org',
            'covers.openlibrary.org',
            'en.wikipedia.org',
            'upload.wikimedia.org',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | External integration registry (batch 8)
    |--------------------------------------------------------------------------
    |
    | Every third-party service Alice talks to, for the health panel on
    | the admin status page. Each entry maps to a probe in
    | App\Support\Integrations; probes and real calls record their
    | outcome in the integration_checks table. Keys are never stored —
    | the panel only reports whether a key is configured.
    |
    */

    'integrations' => [
        'harper' => ['label' => 'Harper grammar check'],
        'open_library' => ['label' => 'Open Library covers'],
        'wikipedia' => ['label' => 'Wikipedia covers'],
    ],

];
