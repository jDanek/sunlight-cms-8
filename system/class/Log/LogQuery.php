<?php

namespace Sunlight\Log;

class LogQuery
{
    public ?int $maxLevel = null;
    public ?string $category = null;
    public ?int $since = null;
    public ?int $until = null;
    public ?string $keyword = null;
    public ?string $method = null;
    public ?string $urlKeyword = null;
    public ?string $ip = null;
    public ?int $userId = null;
    public bool $desc = true;
    public int $offset = 0;
    public int $limit = 100;
}
