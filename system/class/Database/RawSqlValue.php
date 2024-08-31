<?php

namespace Sunlight\Database;

/**
 * Raw SQL value
 *
 * Bypasses {@see Database::val()}. Use with caution.
 */
class RawSqlValue
{
    function __construct(public string $sql)
    {}
}
