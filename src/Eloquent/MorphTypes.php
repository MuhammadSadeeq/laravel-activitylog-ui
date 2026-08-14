<?php

namespace MuhammadSadeeq\ActivitylogUi\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolution checks for the class names recorded in causer_type / subject_type.
 */
class MorphTypes
{
    /** @var array<string, bool> */
    protected static array $resolved = [];

    /**
     * Whether a recorded morph type cannot be turned into a model class.
     *
     * Honours the morph map, so an aliased type that is still mapped resolves
     * normally. Results are memoised because this is consulted once per distinct
     * type per eager load.
     */
    public static function missing(?string $type): bool
    {
        if ($type === null || $type === '') {
            return false;
        }

        return !static::resolves($type);
    }

    /**
     * Whether a recorded morph type resolves to a loadable model class.
     */
    public static function resolves(string $type): bool
    {
        if (isset(static::$resolved[$type])) {
            return static::$resolved[$type];
        }

        try {
            $class = Model::getActualClassNameForMorph($type);
            // class_exists() runs the autoloader, which can itself fail when the
            // class file references something else that is gone.
            $resolves = class_exists($class);
        } catch (\Throwable) {
            $resolves = false;
        }

        return static::$resolved[$type] = $resolves;
    }

    /**
     * Forget memoised results. Intended for tests.
     */
    public static function flush(): void
    {
        static::$resolved = [];
    }
}
