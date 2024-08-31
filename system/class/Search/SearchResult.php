<?php

namespace Sunlight\Search;

class SearchResult
{
    public string $link;
    public string $title;
    public ?string $perex = null;
    /**
     * @var array
     * @see \Sunlight\GenericTemplates::renderInfos()
     */
    public array $infos = [];
}
