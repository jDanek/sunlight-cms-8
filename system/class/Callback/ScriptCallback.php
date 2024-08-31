<?php

namespace Sunlight\Callback;

use Sunlight\Core;

/**
 * Callable class that uses a callback returned by a PHP script
 *
 * The script is not executed until it is needed and will only be executed once.
 */
class ScriptCallback
{
    /** @var callable|null */
    private $callback;

    function __construct(
        public string $path,
        private ?CallbackObjectInterface $object
    ) {}

    function __invoke(...$args)
    {
        if ($this->callback === null) {
            $this->loadCallback();
        }

        return ($this->callback)(...$args);
    }

    private function loadCallback(): void
    {
        $callback = require $this->path;

        if (Core::$debug && !is_callable($callback)) {
            throw new \UnexpectedValueException(sprintf('Script "%s" should return a callable, got %s', $this->path, gettype($callback)));
        }

        if ($this->object !== null && $callback instanceof \Closure) {
            $callback = $callback->bindTo($this->object, $this->object);
        }

        $this->callback = $callback;
    }
}
