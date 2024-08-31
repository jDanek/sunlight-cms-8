<?php

namespace Sunlight\Database;

/**
 * Raw SQL value
 *
 * Bypasses {@see Database::val()}. Use with caution.
 */
class RawSqlValue
{
    public string $sql;

    function __construct(string $sql)
    {
        $this->sql = $sql;
    }
}
