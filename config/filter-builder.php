<?php

return [
    /*
     * Keys in the request payload that contain arrays of filter items
     * (each element is itself a ['name' => ..., 'value' => ...] pair).
     * Extend this list to support additional wrapper keys without touching core code.
     */
    'array_input_keys' => ['keywords', 'periods'],

    /*
     * Default join type used when a join config entry does not specify a 'type'.
     * Accepted values: 'join' | 'leftJoin' | 'rightJoin'
     */
    'default_join_type' => 'leftJoin',

    /*
     * Fallback sort direction when the request provides a sort field but omits
     * the direction (e.g. "name" instead of "name:asc").
     * Accepted values: 'asc' | 'desc'
     */
    'default_sort_direction' => 'desc',

    /*
     * Global custom filter formulas merged into every FilterWhere instance.
     * Each entry is a closure: function ($query, $column, $value) { ... }
     * These can be overridden per-FilterConfig via FilterConfig::addFormula().
     *
     * Example:
     * 'custom_formulas' => [
     *     'null' => fn ($query, $column) => $query->whereNull($column),
     *     'not_null' => fn ($query, $column) => $query->whereNotNull($column),
     * ],
     */
    'custom_formulas' => [],
];
