<?php

namespace AnhTT\FilterBuilder;

class FilterWhere
{
    public function getQueries(FilterConfig $filterConfig, array $requestData): array
    {
        $configs = $filterConfig->getFilters();
        $formulas = array_merge($this->builtinFormulas(), $this->globalFormulas(), $filterConfig->getCustomFormulas());
        $filterQueries = [];

        foreach ($requestData as $requestItem) {
            $name  = $requestItem['name']  ?? null;
            $value = $requestItem['value'] ?? null;

            if ($name === null || $value === null || $value === '' || !isset($configs[$name])) {
                continue;
            }

            $filterQuery = $this->getFilterQuery($configs[$name], $value, $formulas, $filterConfig);
            if ($filterQuery !== null) {
                $filterQueries[] = $filterQuery;
            }
        }

        return $filterQueries;
    }

    private function builtinFormulas(): array
    {
        return [
            'eq' => function ($query, $column, $value) {
                return $query->where($column, $value);
            },
            'ne' => function ($query, $column, $value) {
                return $query->where($column, '!=', $value);
            },
            'gt' => function ($query, $column, $value) {
                return $query->where($column, '>', $value);
            },
            'gte' => function ($query, $column, $value) {
                return $query->where($column, '>=', $value);
            },
            'lt' => function ($query, $column, $value) {
                return $query->where($column, '<', $value);
            },
            'lte' => function ($query, $column, $value) {
                return $query->where($column, '<=', $value);
            },
            'in' => function ($query, $column, $value) {
                return $query->whereIn($column, (array) $value);
            },
            'ni' => function ($query, $column, $value) {
                return $query->whereNotIn($column, (array) $value);
            },
            'null' => function ($query, $column) {
                return $query->whereNull($column);
            },
            'not_null' => function ($query, $column) {
                return $query->whereNotNull($column);
            },
            'cn' => function ($query, $column, $value) {
                return $query->where($column, 'like', "%{$value}%");
            },
            'sw' => function ($query, $column, $value) {
                return $query->where($column, 'like', "{$value}%");
            },
            'ew' => function ($query, $column, $value) {
                return $query->where($column, 'like', "%{$value}");
            },
            'bw' => function ($query, $column, $value) {
                if (isset($value['from']) && $value['from'] !== null && $value['from'] !== '') {
                    $query->where($column, '>=', $value['from']);
                }
                if (isset($value['to']) && $value['to'] !== null && $value['to'] !== '') {
                    $query->where($column, '<=', $value['to']);
                }
                return $query;
            },
            'dbw' => function ($query, $column, $value) {
                if (isset($value['from']) && $value['from'] !== null && $value['from'] !== '') {
                    $query->where($column, '>=', date('Y-m-d H:i:s', strtotime($value['from'])));
                }
                if (isset($value['to']) && $value['to'] !== null && $value['to'] !== '') {
                    $query->where($column, '<=', date('Y-m-d H:i:s', strtotime($value['to'])));
                }
                return $query;
            },
        ];
    }

    private function globalFormulas(): array
    {
        if (!function_exists('config')) {
            return [];
        }
        return config('filter-builder.custom_formulas', []);
    }

    private function getFilterQuery($filter, $value, array $formulas, FilterConfig $filterConfig): ?\Closure
    {
        if (is_string($filter)) {
            [$column, $syntax] = explode(':', $filter, 2);

            if (!isset($formulas[$syntax])) {
                return null;
            }

            $filterConfig->addJoin($column);

            return function ($query) use ($syntax, $column, $value, $formulas) {
                return $formulas[$syntax]($query, $column, $value);
            };
        }

        if (is_callable($filter)) {
            return function ($query) use ($filter, $value, $filterConfig) {
                return $query->where(function ($query) use ($filter, $value, $filterConfig) {
                    return $filter($query, $value, $filterConfig);
                });
            };
        }

        if (is_array($filter)) {
            return function ($query) use ($filter, $value, $formulas, $filterConfig) {
                return $query->where(function ($query) use ($filter, $value, $formulas, $filterConfig) {
                    foreach ($filter as $item) {
                        $subQuery = $this->getFilterQuery($item, $value, $formulas, $filterConfig);
                        if ($subQuery !== null) {
                            $query->orWhere($subQuery);
                        }
                    }
                });
            };
        }

        return null;
    }
}
