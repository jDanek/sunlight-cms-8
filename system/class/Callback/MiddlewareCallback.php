<?php

namespace Sunlight\Callback;

class MiddlewareCallback
{
    /**
     * @param callable $callback
     * @param callable[] $middlewares
     */
    function __construct(
        private $callback,
        private array $middlewares
    ) {}

    function __invoke(...$args)
    {
        $queue = new \SplQueue();

        foreach ($this->middlewares as $middleware) {
            $queue->enqueue($middleware);
        }

        $next = function (...$args) use (&$next, $queue) {
            if (!$queue->isEmpty()) {
                return $queue->dequeue()($next, ...$args);
            }

            return ($this->callback)(...$args);
        };

        return $next(...$args);
    }
}
