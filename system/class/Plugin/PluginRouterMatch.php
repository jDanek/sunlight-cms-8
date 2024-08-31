<?php

namespace Sunlight\Plugin;

class PluginRouterMatch
{
    /** @var callable */
    public $callback;
    public array $params;

    function __construct(callable $callback, array $params)
    {
        $this->callback = $callback;
        $this->params = $params;
    }
}
