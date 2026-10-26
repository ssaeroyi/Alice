<?php

namespace App\Rules;

use App\Models\Story;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The story-text cap (batch 9, part B): teacher-authored content must
 * stay inside the grammar check's window. Saving a story WITHOUT
 * touching the text never trips the cap — long imported books keep
 * their full text — but any new or edited text is measured against
 * max_words/max_chars.
 */
class StoryContentLimit implements ValidationRule
{
    public function __construct(private readonly ?Story $existing = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;

        if ($this->existing && trim($value) === trim((string) $this->existing->content)) {
            return;
        }

        $maxWords = config('stories.content.max_words');
        $maxChars = config('stories.content.max_chars');

        if ($maxWords && str_word_count($value) > $maxWords) {
            $fail('The story text is too long — keep it under '.number_format($maxWords).' words.');
        }

        if ($maxChars && strlen($value) > $maxChars) {
            $fail('The story text is too long — keep it under '.number_format($maxChars).' characters.');
        }
    }
}
