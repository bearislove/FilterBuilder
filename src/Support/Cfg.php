<?php

namespace AnhTT\FilterBuilder\Support;

/**
 * Centralized config accessor for the filter-builder package.
 *
 * All values are cached after the first read so that repeated calls within
 * the same request pay nothing beyond a hash-map lookup.
 *
 * Call Cfg::flushCache() in test teardowns when you need a clean slate.
 */
class Cfg
{
    /** Built-in defaults — used when Laravel's config() is unavailable. */
    private static array $defaults = [
        'array_input_keys'       => ['keywords', 'periods'],
        'default_join_type'      => 'leftJoin',
        'default_sort_direction' => 'desc',
        'strict_mode'            => false,
        'formulas'               => [],
        'handlers'               => [
            'where' => 'AnhTT\\FilterBuilder\\FilterWhere',
            'sort'  => 'AnhTT\\FilterBuilder\\FilterSort',
            'join'  => 'AnhTT\\FilterBuilder\\FilterJoin',
        ],
    ];

    /**
     * Runtime value cache — populated on first access per key.
     * Static so it survives within a single PHP process / request.
     *
     * @var array<string, mixed>
     */
    private static array $cache = [];

    /**
     * Resolved handler instances — stateless objects reused across calls.
     *
     * @var array<string, object>
     */
    private static array $handlerCache = [];

    // -------------------------------------------------------------------------

    /**
     * Read a config value by dot-notation key (e.g. "handlers.where").
     * Result is cached so subsequent calls are a single array lookup.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, static::$cache)) {
            return static::$cache[$key];
        }

        $fallback = static::defaultFor($key) ?? $default;
        $value    = function_exists('config')
            ? config("filter-builder.{$key}", $fallback)
            : $fallback;

        return static::$cache[$key] = $value;
    }

    /**
     * Resolve a handler instance ('where' | 'sort' | 'join').
     *
     * Handlers are stateless — the same instance is reused for the lifetime
     * of the PHP process, avoiding repeated new / app()->make() calls.
     * The Laravel container is used when available so that
     *   $app->bind(FilterWhere::class, MyFilterWhere::class)
     * is honoured without any change to this package.
     */
    public static function resolveHandler(string $type, string $fallbackClass): object
    {
        if (isset(static::$handlerCache[$type])) {
            return static::$handlerCache[$type];
        }

        $class = static::get("handlers.{$type}", $fallbackClass);

        $instance = function_exists('app')
            ? app()->make($class)
            : new $class();

        return static::$handlerCache[$type] = $instance;
    }

    /**
     * Clear all caches.
     * Call this in test teardowns when config values or container bindings
     * need to change between test cases.
     */
    public static function flushCache(): void
    {
        static::$cache        = [];
        static::$handlerCache = [];
    }

    // -------------------------------------------------------------------------

    /** Dot-notation lookup into the $defaults array. */
    private static function defaultFor(string $key): mixed
    {
        $segments = explode('.', $key);
        $value    = static::$defaults;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
