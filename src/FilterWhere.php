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
            'eq'       => fn ($q, $col, $val) => $q->where($col, $val),
            'ne'       => fn ($q, $col, $val) => $q->where($col, '!=', $val),
            'gt'       => fn ($q, $col, $val) => $q->where($col, '>', $val),
            'gte'      => fn ($q, $col, $val) => $q->where($col, '>=', $val),
            'lt'       => fn ($q, $col, $val) => $q->where($col, '<', $val),
            'lte'      => fn ($q, $col, $val) => $q->where($col, '<=', $val),
            'in'       => fn ($q, $col, $val) => $q->whereIn($col, (array) $val),
            'ni'       => fn ($q, $col, $val) => $q->whereNotIn($col, (array) $val),
            'null'     => fn ($q, $col)        => $q->whereNull($col),
            'not_null' => fn ($q, $col)        => $q->whereNotNull($col),
            'cn'       => fn ($q, $col, $val) => $q->where($col, 'like', "%{$val}%"),
            'sw'       => fn ($q, $col, $val) => $q->where($col, 'like', "{$val}%"),
            'ew'       => fn ($q, $col, $val) => $q->where($col, 'like', "%{$val}"),
            'bw'       => static function ($q, $col, $val) {
                if (isset($val['from']) && $val['from'] !== null && $val['from'] !== '') {
                    $q->where($col, '>=', $val['from']);
                }
                if (isset($val['to']) && $val['to'] !== null && $val['to'] !== '') {
                    $q->where($col, '<=', $val['to']);
                }
                return $q;
            },
            'dbw'      => static function ($q, $col, $val) {
                if (isset($val['from']) && $val['from'] !== null && $val['from'] !== '') {
                    $q->where($col, '>=', date('Y-m-d H:i:s', strtotime($val['from'])));
                }
                if (isset($val['to']) && $val['to'] !== null && $val['to'] !== '') {
                    $q->where($col, '<=', date('Y-m-d H:i:s', strtotime($val['to'])));
                }
                return $q;
            },
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
