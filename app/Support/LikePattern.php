<?php

namespace App\Support;

/**
 * Builds LIKE patterns that treat the user's text literally. Use with ESCAPE '!',
 * which behaves the same on every database (the default differs: MySQL \, SQLite none).
 *
 * Letter matching still follows the database: MySQL's *_ci collation ignores case and
 * Polish diacritics ("lukasz" finds "Łukasz"), SQLite ignores case for ASCII only.
 */
class LikePattern
{
    public static function contains(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
    }
}
