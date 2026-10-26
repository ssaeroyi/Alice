<?php

namespace App\Models;

use App\Support\Section;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A teacher's class. Students join by entering the join code (or accepting an
 * inbox invite), giving every student a first-class view of "my class": the
 * teacher, their classmates, and class-scoped assignments. This is the
 * self-serve enrollment that replaces the old free-text section-string wiring.
 */
#[Fillable(['teacher_id', 'name', 'join_code', 'section_label'])]
class Group extends Model
{
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members', 'group_id', 'user_id')
            ->withTimestamps();
    }

    public function memberPivots(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'group_id');
    }

    /**
     * A short, human-friendly code students type (or paste) to join.
     * Generated lazily on create; guarantees uniqueness up front.
     */
    public static function makeJoinCode(): string
    {
        do {
            $code = 'CLS-'.strtoupper(Str::random(4));
        } while (static::query()->where('join_code', $code)->exists());

        return $code;
    }

    /**
     * Case-insensitive lookup by join code (students often mistype case).
     */
    public static function findByJoinCode(string $code): ?Group
    {
        $code = self::normalizeCode($code);

        return static::query()
            ->whereRaw('UPPER(join_code) = ?', [$code])
            ->first();
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    public function isMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    /**
     * Add a student to the class (idempotent enrollment). All entry paths —
     * typing the code, pasting an invite link, accepting an inbox invite —
     * funnel through here so the result is always identical.
     */
    public function addMember(User $user): bool
    {
        if ($this->isMember($user)) {
            return false;
        }

        $this->members()->attach($user->id, ['joined_at' => now()]);

        return true;
    }

    public function removeMember(User $user): bool
    {
        if (! $this->isMember($user)) {
            return false;
        }

        $this->members()->detach($user->id);

        return true;
    }

    public function displayLabel(): string
    {
        return $this->section_label ?: 'Class';
    }

    public static function normalizeSectionLabel(string $label): string
    {
        return Section::normalize($label);
    }
}
