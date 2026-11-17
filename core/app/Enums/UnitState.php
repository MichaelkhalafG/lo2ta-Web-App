<?php

namespace App\Enums;

enum UnitState: string
{
    use HasLabel;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';
    case Published = 'published';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Withdrawn = 'withdrawn';

    /** States in which the duplicate keys are held (PLAN 3.4, 3.7 #8). */
    public function holdsDuplicateKeys(): bool
    {
        return in_array($this, [self::Submitted, self::ChangesRequested, self::Published, self::Reserved], true);
    }

    /** States a visitor can open on the public site. */
    public function isPublic(): bool
    {
        return in_array($this, [self::Published, self::Reserved], true);
    }

    /**
     * @return list<string>
     */
    public static function publicValues(): array
    {
        return [self::Published->value, self::Reserved->value];
    }
}
