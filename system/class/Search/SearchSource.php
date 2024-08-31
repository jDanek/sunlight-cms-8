<?php

namespace Sunlight\Search;

abstract class SearchSource
{
    private bool $enabledByDefault = true;
    private int $limit = 100;

    function __construct(private string $key)
    {}

    function getKey(): string
    {
        return $this->key;
    }

    function isEnabledByDefault(): bool
    {
        return $this->enabledByDefault;
    }

    function setEnabledByDefault(bool $enabledByDefault): void
    {
        $this->enabledByDefault = $enabledByDefault;
    }

    function getLimit(): int
    {
        return $this->limit;
    }

    function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    function getLabel(): string
    {
        return _lang("search.{$this->key}");
    }

    /**
     * @return iterable<SearchResult>
     */
    abstract function search(string $query): iterable;
}
