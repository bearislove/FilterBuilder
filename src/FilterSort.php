<?php

namespace AnhTT\FilterBuilder;

use AnhTT\FilterBuilder\Support\Cfg;

class FilterSort
{
    public function getQueries(FilterConfig $filterConfig, ?string $sort): array
    {
        $configs     = $filterConfig->getSorts();
        $sortQueries = [];

        if ($sort) {
            foreach (explode(',', $sort) as $sortGroup) {
                if ($sortGroup === '') continue;
                $this->buildQuery($sortGroup, $configs, $sortQueries, $filterConfig);
            }
        }

        $defaultSort = $filterConfig->defaultSort();
        if ($defaultSort) {
            $this->buildQuery($defaultSort, $configs, $sortQueries, $filterConfig);
        }

        return $sortQueries;
    }

    private function buildQuery(string $sort, array $config, array &$sortQueries, FilterConfig $filterConfig): void
    {
        $parts     = explode(':', $sort, 2);
        $field     = $parts[0];
        $direction = isset($parts[1]) && in_array(strtolower($parts[1]), ['asc', 'desc'], true)
            ? strtolower($parts[1])
            : Cfg::get('default_sort_direction', 'desc');

        if (!$this->sortable($field, $config, $sortQueries)) {
            return;
        }

        $sortConfig = $config[$field];

        if (is_string($sortConfig)) {
            $filterConfig->addJoin($sortConfig);
            $sortQueries[$field] = fn ($query) => $query->orderBy($sortConfig, $direction);
        } elseif (is_callable($sortConfig)) {
            $result = $sortConfig($direction, $filterConfig);
            if (is_callable($result)) {
                $sortQueries[$field] = $result;
            }
        }
    }

    private function sortable(string $field, array $config, array $sortQueries): bool
    {
        return $field !== '' && isset($config[$field]) && !isset($sortQueries[$field]);
    }
}
