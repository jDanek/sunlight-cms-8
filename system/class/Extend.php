<?php

namespace Sunlight;

/**
 * Extend event system
 */
abstract class Extend
{
    /**
     * Register callback for an event
     *
     * @param callable $callback
     */
    static function reg(string $event, callable $callback, int $priority = 0): void
    {
        Core::$eventEmitter->on($event, $callback, $priority);
    }

    /**
     * Register multiple callbacks
     *
     * @param array $callbacks array(event1 => callback1, ...)
     */
    static function regm(array $callbacks, int $priority = 0): void
    {
        foreach ($callbacks as $event => $callback) {
            Core::$eventEmitter->on($event, $callback, $priority);
        }
    }

    /**
     * Create normalized event arguments
     *
     * @param string &$output output variable reference
     * @param array $args array with additional arguments
     */
    static function args(string &$output, array $args = []): array
    {
        $args['output'] = &$output;

        return $args;
    }

    /**
     * Trigger an event
     */
    static function call(string $event, array $args = []): void
    {
        Core::$eventEmitter->emit($event, $args);
    }

    /**
     * Trigger an event and fetch a value
     *
     * @param array $args ('value' is added automatically)
     * @param mixed|null $value initial value
     */
    static function fetch(string $event, array $args = [], mixed $value = null)
    {
        $args['value'] = &$value;
        self::call($event, $args);

        return $value;
    }

    /**
     * Trigger an event and fetch a string
     *
     * @param array $args ('output' is added automatically)
     */
    static function buffer(string $event, array $args = []): string
    {
        $output = '';
        $args['output'] = &$output;
        self::call($event, $args);

        return $output;
    }
}
