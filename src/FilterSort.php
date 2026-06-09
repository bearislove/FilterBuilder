<?php

namespace AnhTT\FilterBuilder;

class FilterSort
{
    public function getQueries(FilterConfig $filterConfig, $sort): array
    {
        $configs = $filterConfig->getSorts();
        $sortQueries = [];

        if ($sort) {
            foreach (explode(',', $sort) as $sortGroup) {
                if ($sortGroup === '') continue;
                $this->getQuery($sortGroup, $configs, $sortQueries, $filterConfig);
            }
        }

        $defaultSort = $filterConfig->defaultSort();
        if ($defaultSort) {
            $this->getQuery($defaultSort, $configs, $sortQueries, $filterConfig);
        }

        return $sortQueries;
    }

    private function getQuery(string $sort, array $config, array &$sortQueries, FilterConfig $filterConfig): void
    {
        $parts = explode(':', $sort, 2);
        $sortField = $parts[0];
        $sortDirection = $parts[1] ?? null;

        if (!$this->sortable($sortField, $config, $sortQueries)) {
            return;
        }

        $validDirections = ['asc', 'desc'];
        if (!$sortDirection || !in_array(strtolower($sortDirection), $validDirections)) {
            $sortDirection = $this->defaultSortDirection();
        } else {
            $sortDirection = strtolower($sortDirection);
        }

        $sortConfig = $config[$sortField];

        if (is_string($sortConfig)) {
            $column = $sortConfig;
            $filterConfig->addJoin($column);
            $sortQueries[$sortField] = function ($query) use ($column, $sortDirection) {
                $query->orderBy($column, $sortDirection);
            };
        } elseif (is_callable($sortConfig)) {
            $sortQueries[$sortField] = $sortConfig($sortDirection, $filterConfig);
        }
    }

    private function sortable(string $sortField, array $config, array $sortQueries): bool
    {
        return $sortField !== '' && isset($config[$sortField]) && !isset($sortQueries[$sortField]);
    }

    private function defaultSortDirection(): string
    {
        if (function_exists('config')) {
            return config('filter-builder.default_sort_direction', 'desc');
        }
        return 'desc';
    }
}
