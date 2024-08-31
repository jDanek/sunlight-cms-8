<?php

namespace Sunlight\Util;

abstract class Math
{
    /**
     * Limit number range
     *
     * @param float|int $num the number
     * @param float|int|null $min minimum value or null (= unlimited)
     * @param float|int|null $max maximum value or null (= unlimited)
     */
    static function range(float|int $num, float|int|null $min, float|int|null $max): float|int
    {
        if (isset($min) && $num < $min) {
            return $min;
        }

        if (isset($max) && $num > $max) {
            return $max;
        }

        return $num;
    }
}
