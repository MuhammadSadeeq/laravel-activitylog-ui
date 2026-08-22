<?php

namespace MuhammadSadeeq\ActivitylogUi\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolution checks for the class names recorded in causer_type / subject_type.
 */
class MorphTypes
{
    /**
     * Memoised results, keyed by the RESOLVED class name.
     *
     * Keying on the resolved class rather than the recorded type means a morph
     * map registered or changed later produces a different key, so a stale
     * decision cannot be served to a long-lived worker. class_exists() re-runs
     * the autoloader on every miss, which is why the result is kept at all.
     *
     * @var array<string, bool>
     */
    protected static array $queryable = [];

    /**
     * Whether a recorded morph type cannot be turned into a model class.
     */
    public static function missing(?string $type): bool
    {
        if ($type === null || $type === '') {
            return false;
        }

        return !static::resolves($type);
    }

    /**
     * Whether a recorded morph type resolves to a model Eloquent can query.
     *
     * An autoloader failure is deliberately NOT caught. A class that exists but
     * cannot be loaded — a parse error, a missing dependency — is a deployment
     * problem, and silently reporting it as "missing" would quietly blank the
     * audit trail for every row of that type.
     */
    public static function resolves(string $type): bool
    {
        $class = Model::getActualClassNameForMorph($type);

        if (!is_string($class) || $class === '') {
            return false;
        }

        return static::$queryable[$class] ??= static::isQueryableModel($class);
    }

    /**
     * class_exists() alone is not enough to answer that.
     *
     * Everything that consumes this — eager loading a morph relation, building a
     * whereHasMorph — hands the class to Eloquent, which does `new $class` and
     * then calls newQuery() on it. A recorded type naming a class that is no
     * longer a model, has become abstract, or has gained a required constructor
     * argument passes class_exists() and then fatals at that point instead.
     */
    protected static function isQueryableModel(string $class): bool
    {
        if (!class_exists($class) || !is_a($class, Model::class, true)) {
            return false;
        }

        $reflection = new \ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            return false;
        }

        $constructor = $reflection->getConstructor();

        return $constructor === null || $constructor->getNumberOfRequiredParameters() === 0;
    }

    /**
     * Forget memoised results.
     *
     * Worth calling from a long-lived worker if the application registers morph
     * maps or autoloaders dynamically between requests.
     */
    public static function flush(): void
    {
        static::$queryable = [];
    }
}
