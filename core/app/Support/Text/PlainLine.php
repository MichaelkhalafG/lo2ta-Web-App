<?php

namespace App\Support\Text;

/**
 * A one-line value people type (a name, a company): no line breaks or other control characters (audit L6). Mail
 * headers already drop them, but a name with a hidden line break still breaks lists, exports and the dashboard.
 */
final class PlainLine
{
    /** Validation rule: refuse C0 controls, DEL and the Unicode line and paragraph separators. */
    public const RULE = 'not_regex:/[\x00-\x1F\x7F\x{2028}\x{2029}]/u';
}
