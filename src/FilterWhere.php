<?php

namespace AnhTT\FilterBuilder;

class FilterWhere
{
    public function getQueries(FilterConfig $filerConfig, array $requestData): array
    {
        $configs = $filerConfig->getFilters();
        $formulas = $this->formulas();
        $filterQueries = [];
        foreach ($requestData as $requestItem) {
            $name = $requestItem['name'] ?? false;
            $value = $requestItem['value'] ?? false;
            if ($name && $value && isset($configs[$name])) {
                $filterQuery = $this->getFilterQuery($configs[$name], $value, $formulas, $filerConfig);
                $filterQuery && $filterQueries[] = $filterQuery;
            }
        }
        return $filterQueries;
    }

    private function formulas(): array
    {
        return [
            'eq' => function ($query, $column, $value) {
                return $query->where($column, $value);
            },
            'ne' => function ($query, $column, $value) {
                return $query->whereNot($column, $value);
            },
            'in' => function ($query, $column, $value) {
                return $query->whereIn($column, $value);
            },
            'ni' => function ($query, $column, $value) {
                return $query->whereNotIn($column, $value);
            },
            'cn' => function ($query, $column, $value) {
                return $query->where($column, 'like', "%$value%");
            },
            'sw' => function ($query, $column, $value) {
                return $query->where($column, 'like', "$value%");
            },
            'ew' => function ($query, $column, $value) {
                return $query->where($column, 'like', "%$value");
            },
            'bw' => function ($query, $column, $value) {
                if (isset($value['from'])) {
                    $query->where($column, '>=', $value['from']);
                }
                if (isset($value['to'])) {
                    $query->where($column, '<=', $value['to']);
                }
                return $query;
            },
            'dbw' => function ($query, $column, $value) {
                if (isset($value['from'])) {
                    $from = date_parse($value['from']);
                    $from &&
                    $query->where($column, '>=', "{$from['year']}-{$from['month']}-{$from['day']} {$from['hour']}:{$from['minute']}:{$from['second']}");

                }
                if (isset($value['to'])) {
                    $to = date_parse($value['to']);
                    $to &&
                    $query->where($column, '<=', "{$to['year']}-{$to['month']}-{$to['day']} {$to['hour']}:{$to['minute']}:{$to['second']}");
                }
                return $query;
            }
        ];
    }

    private function getFilterQuery($filter, $value, $formulas, FilterConfig $filerConfig): ?\Closure
    {
        if (is_string($filter)) {
            [$column, $syntax] = explode(':', $filter, 2);
            $filerConfig->addJoin($column);
            return function ($query) use ($syntax, $column, $value, $formulas) {
                return $formulas[$syntax]($query, $column, $value);
            };
        } elseif (is_callable($filter)) {
            return function ($query) use ($filter, $value, $filerConfig) {
                return $query->where(function ($query) use ($filter, $value, $filerConfig) {
                    return $filter($query, $value, $filerConfig);
                });
            };

        } elseif (is_array($filter)) {
            return function ($query) use ($filter, $value, $formulas, $filerConfig) {
                return $query->where(function ($query) use ($filter, $value, $formulas, $filerConfig) {
                    foreach ($filter as $item) {
                        $query->orWhere($this->getFilterQuery($item, $value, $formulas, $filerConfig));
                    }
                });
            };
        }
        return null;
    }
}