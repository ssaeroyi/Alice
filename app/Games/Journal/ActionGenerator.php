<?php

namespace App\Games\Journal;

use App\Models\Story;

/**
 * One exercise type. Feasibility 0 = the story cannot support it and it is
 * never emitted. generate() must return an action whose payload is solvable
 * (guidelines/JOURNAL_BATTLES.md, solvability invariant).
 *
 * Returned shape:
 * [
 *   'kind'          => 'find'|'fill'|'choice',
 *   'key'           => string (short stable id, e.g. 'find-ing')
 *   'flavor'        => display name, e.g. 'SPELL STRIKE'
 *   'prompt'        => one-line task description
 *   'payload'       => full payload incl. answers (server-only)
 *   'public_payload'=> what the UI may render (no answers for fill)
 * ]
 */
interface ActionGenerator
{
    public function key(): string;

    /** 0 = unfit for this story. Higher = richer candidate pool. */
    public function feasibility(StoryProfile $profile): int;

    /** @return array{kind:string,key:string,flavor:string,prompt:string,payload:array,public_payload:array} */
    public function generate(StoryProfile $profile, Story $story): array;
}
