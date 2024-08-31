<?php

namespace Sunlight\Plugin;

class PluginRouterMatch
{
    /** @var callable */
    public $callback;

    function __construct(
        callable $callback,
        public array $params
    ) {
        $this->callback = $callback;
    }
}
