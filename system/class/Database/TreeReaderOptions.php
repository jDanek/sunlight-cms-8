<?php

namespace Sunlight\Database;

class TreeReaderOptions
{
    /** @var string[] list of additional columns to load */
    public array $columns = [];
    /** @var int|null only load this node and its children */
    public ?int $nodeId = null;
    /** @var int|null node depth, if known (can also be used to limit depth) */
    public ?int $nodeDepth = null;
    /** @var string|null name of column to use for sorting */
    public ?string $sortBy = null;
    /** @var bool sort mode */
    public bool $sortAsc = true;
    /** @var TreeFilterInterface|null tree filter */
    public ?TreeFilterInterface $filter = null;
}
