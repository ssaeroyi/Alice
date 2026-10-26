<?php

namespace App\Http\Requests;

use App\Rules\StoryContentLimit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStoryRequest extends FormRequest
{
    /**
     * Drafts are allowed to be incomplete — the completeness meter shows
     * what is missing and publishing enforces the core fields. Only a
     * title is mandatory so a story can be started with a blank page.
     *
     * reading_level is deliberately NOT a rule: it is derived from the
     * story text by the readability analyzer on save, so a posted value
     * is ignored and the field cannot be tampered with.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'author' => ['nullable', 'string', 'max:120'],
            'license' => ['nullable', 'string', 'max:100', Rule::in(config('stories.licenses'))],
            'source_url' => ['nullable', 'url', 'max:255'],
            'synopsis' => ['nullable', 'string', 'max:2000'],
            'content' => ['nullable', 'string', new StoryContentLimit($this->route('story'))],
            'genres' => ['nullable', 'array'],
            'genres.*' => ['integer', 'exists:genres,id'],
            'cover_mode' => ['nullable', 'in:upload,url'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'cover_url' => ['nullable', 'url', 'max:255'],
            'remove_cover' => ['nullable', 'boolean'],
        ];
    }
}
