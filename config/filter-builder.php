<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request data key
    |--------------------------------------------------------------------------
    | When null  → the scope reads $request->all() as filter input.
    | When a string (e.g. 'filters') → reads $request->input('filters', [])
    | so your API can namespace all filter params under a single key:
    |
    |   GET /users?filters[name]=john&filters[status]=active
    |
    | Can be overridden per-call by passing an array or Request explicitly.
    */
    'request_data_key' => null,

    /*
    |--------------------------------------------------------------------------
    | Sort key
    |--------------------------------------------------------------------------
    | The request parameter that carries the sort string.
    | Default: 'sort'  →  GET /users?sort=name:asc,created_at:desc
    |
    | Change this if your API uses a different key, e.g. 'order_by'.
    */
    'sort_key' => 'sort',

    /*
    |--------------------------------------------------------------------------
    | Array input keys
    |--------------------------------------------------------------------------
    | Keys in the request payload whose values are arrays of
    | { "name": "...", "value": "..." } filter items.
    | Extend this list to support additional wrapper keys.
    */
    'array_input_keys' => ['keywords', 'periods'],

    /*
    |--------------------------------------------------------------------------
    | Default join type
    |--------------------------------------------------------------------------
    | Used when a join config entry does not specify a 'type'.
    | Accepted: 'join' | 'leftJoin' | 'rightJoin'
    */
    'default_join_type' => 'leftJoin',

    /*
    |--------------------------------------------------------------------------
    | Default sort direction
    |--------------------------------------------------------------------------
    | Fallback when the request provides a sort field but omits direction
    | (e.g. "name" instead of "name:asc").
    | Accepted: 'asc' | 'desc'
    */
    'default_sort_direction' => 'desc',

    /*
    |--------------------------------------------------------------------------
    | Strict mode
    |--------------------------------------------------------------------------
    | When true, using an unknown formula (e.g. 'column:typo') throws an
    | InvalidArgumentException instead of silently skipping the filter.
    | Useful during development to catch typos early.
    */
    'strict_mode' => env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Global formulas
    |--------------------------------------------------------------------------
    | Register additional filter formulas available to every FilterConfig
    | instance. Each value must be a callable:
    |   fn ($query, $column, $value) => $query->...
    |
    | These are merged after built-in formulas, so they can override defaults.
    | Per-FilterConfig formulas (addFormula / setCustomFormulas) have highest
    | priority and override both built-ins and these global ones.
    |
    | Example:
    |   'formulas' => [
    |       'year'  => fn ($q, $col, $val) => $q->whereYear($col, $val),
    |       'month' => fn ($q, $col, $val) => $q->whereMonth($col, $val),
    |   ],
    */
    'formulas' => [],

    /*
    |--------------------------------------------------------------------------
    | Handler classes
    |--------------------------------------------------------------------------
    | The three handler classes that build WHERE, ORDER BY, and JOIN clauses.
    | Swap any of them with your own implementation to customise behaviour
    | globally without touching FilterConfig or FilterForm.
    |
    | The classes are resolved via the Laravel service container, so you can
    | also bind alternatives in AppServiceProvider:
    |   $this->app->bind(FilterWhere::class, MyFilterWhere::class);
    |
    | Example: replace only the WHERE handler
    |   'handlers' => [
    |       'where' => \App\FilterBuilder\MyFilterWhere::class,
    |   ],
    */
    'handlers' => [
        'where' => \AnhTT\FilterBuilder\FilterWhere::class,
        'sort'  => \AnhTT\FilterBuilder\FilterSort::class,
        'join'  => \AnhTT\FilterBuilder\FilterJoin::class,
    ],

];
