<?php

namespace AnhTT\FilterBuilder;

use AnhTT\FilterBuilder\Support\Cfg;
use InvalidArgumentException;

class FilterWhere
{
    /**
     * Built-in formulas cached as a static property — allocated once per
     * PHP process instead of once per request.
     *
     * @var array<string, \Closure>|null
     */
    private static ?array $builtinCache = null;

    public function getQueries(FilterConfig $filterConfig, array $requestData): array
    {
        $configs = $filterConfig->getFilters();

        // Build the merged formula map.
        // In the common case (no global or per-config additions) we skip
        // array_merge entirely and reuse the static cache directly.
        $globalFormulas = Cfg::get('formulas', []);
        $customFormulas = $filterConfig->getCustomFormulas();
        $builtins       = $this->builtinFormulas();

        $formulas = ($globalFormulas || $customFormulas)
            ? array_merge($builtins, $globalFormulas, $customFormulas)
            : $builtins;

        $strict        = Cfg::get('strict_mode', false);
        $filterQueries = [];

        foreach ($requestData as $requestItem) {
            $name  = $requestItem['name']  ?? null;
            $value = $requestItem['value'] ?? null;

            if ($name === null || $value === null || $value === '' || !isset($configs[$name])) {
                continue;
            }

            $filterQuery = $this->buildQuery($configs[$name], $value, $formulas, $filterConfig, $strict);
            if ($filterQuery !== null) {
                $filterQueries[] = $filterQuery;
            }
        }

        return $filterQueries;
    }

    // -------------------------------------------------------------------------

    /** Returns built-in formulas, building the array only on the first call. */
    private function builtinFormulas(): array
    {
        if (static::$builtinCache !== null) {
            return static::$builtinCache;
        }

        return static::$builtinCache = [

            // ------------------------------------------------------------------
            // Comparison
            // ------------------------------------------------------------------
            'eq'  => fn ($q, $col, $val) => $q->where($col, $val),
            'ne'  => fn ($q, $col, $val) => $q->where($col, '!=', $val),
            'gt'  => fn ($q, $col, $val) => $q->where($col, '>', $val),
            'gte' => fn ($q, $col, $val) => $q->where($col, '>=', $val),
            'lt'  => fn ($q, $col, $val) => $q->where($col, '<', $val),
            'lte' => fn ($q, $col, $val) => $q->where($col, '<=', $val),

            // ------------------------------------------------------------------
            // Array membership
            // ------------------------------------------------------------------
            'in' => fn ($q, $col, $val) => $q->whereIn($col, (array) $val),
            'ni' => fn ($q, $col, $val) => $q->whereNotIn($col, (array) $val),

            // ------------------------------------------------------------------
            // Null checks
            // ------------------------------------------------------------------
            'null'     => fn ($q, $col) => $q->whereNull($col),
            'not_null' => fn ($q, $col) => $q->whereNotNull($col),

            // ------------------------------------------------------------------
            // String / LIKE
            // ------------------------------------------------------------------
            'cn'  => fn ($q, $col, $val) => $q->where($col, 'like', "%{$val}%"),
            'ncn' => fn ($q, $col, $val) => $q->where($col, 'not like', "%{$val}%"),
            'sw'  => fn ($q, $col, $val) => $q->where($col, 'like', "{$val}%"),
            'ew'  => fn ($q, $col, $val) => $q->where($col, 'like', "%{$val}"),

            // ------------------------------------------------------------------
            // Numeric / generic range  {from, to}
            // ------------------------------------------------------------------
            'bw' => static function ($q, $col, $val) {
                if (isset($val['from']) && $val['from'] !== null && $val['from'] !== '') {
                    $q->where($col, '>=', $val['from']);
                }
                if (isset($val['to']) && $val['to'] !== null && $val['to'] !== '') {
                    $q->where($col, '<=', $val['to']);
                }
                return $q;
            },

            // ------------------------------------------------------------------
            // Date-time range  {from, to}  — values parsed to Y-m-d H:i:s
            // ------------------------------------------------------------------
            'dbw' => static function ($q, $col, $val) {
                if (isset($val['from']) && $val['from'] !== null && $val['from'] !== '') {
                    $q->where($col, '>=', date('Y-m-d H:i:s', strtotime($val['from'])));
                }
                if (isset($val['to']) && $val['to'] !== null && $val['to'] !== '') {
                    $q->where($col, '<=', date('Y-m-d H:i:s', strtotime($val['to'])));
                }
                return $q;
            },

            // ------------------------------------------------------------------
            // Date parts  (work on DATETIME / TIMESTAMP columns)
            // ------------------------------------------------------------------

            // WHERE DATE(col) = '2024-06-01'  — strips the time portion
            'date'  => fn ($q, $col, $val) => $q->whereDate($col, $val),

            // WHERE YEAR(col) = 2024
            'year'  => fn ($q, $col, $val) => $q->whereYear($col, $val),

            // WHERE MONTH(col) = 6
            'month' => fn ($q, $col, $val) => $q->whereMonth($col, $val),

            // WHERE DAY(col) = 15
            'day'   => fn ($q, $col, $val) => $q->whereDay($col, $val),

            // WHERE TIME(col) = '08:00:00'
            'time'  => fn ($q, $col, $val) => $q->whereTime($col, '=', $val),

            // ------------------------------------------------------------------
            // JSON columns  (MySQL 5.7+ / PostgreSQL with jsonb)
            // ------------------------------------------------------------------

            // WHERE JSON_CONTAINS(col, '"value"')
            'json' => fn ($q, $col, $val) => $q->whereJsonContains($col, $val),

            // ------------------------------------------------------------------
            // Column-to-column comparison  (value = another column name)
            // 'price_field' => 'orders.total:col'  +  request value = 'orders.discount'
            // → WHERE orders.total = orders.discount
            // ------------------------------------------------------------------
            'col' => fn ($q, $col, $val) => $q->whereColumn($col, $val),

        ];
    }

    private function buildQuery(
        mixed $filter,
        mixed $value,
        array $formulas,
        FilterConfig $filterConfig,
        bool $strict
    ): ?\Closure {
        if (is_string($filter)) {
            [$column, $syntax] = explode(':', $filter, 2);

            if (!isset($formulas[$syntax])) {
                if ($strict) {
                    throw new InvalidArgumentException(
                        "Unknown filter formula \"{$syntax}\". Register it in config('filter-builder.formulas') or via FilterConfig::addFormula()."
                    );
                }
                return null;
            }

            $filterConfig->addJoin($column);

            return fn ($query) => $formulas[$syntax]($query, $column, $value);
        }

        if (is_callable($filter)) {
            return fn ($query) => $query->where(
                fn ($q) => $filter($q, $value, $filterConfig)
            );
        }

        if (is_array($filter)) {
            // addJoin must be called NOW (eager), not inside the deferred closure.
            // getJoinQueries() is called before apply(), so any join registered
            // inside the closure would be too late and silently dropped.
            foreach ($filter as $item) {
                if (is_string($item)) {
                    $filterConfig->addJoin(explode(':', $item, 2)[0]);
                }
            }

            return fn ($query) => $query->where(
                function ($q) use ($filter, $value, $formulas, $filterConfig, $strict) {
                    foreach ($filter as $item) {
                        $sub = $this->buildQuery($item, $value, $formulas, $filterConfig, $strict);
                        if ($sub !== null) {
                            $q->orWhere($sub);
                        }
                    }
                }
            );
        }

        return null;
    }
}
