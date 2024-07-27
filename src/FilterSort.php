<?php

namespace AnhTT\FilterBuilder;

class FilterSort
{
    public function getQueries(FilterConfig $filerConfig, $sort): array
    {
        $configs = $filerConfig->getSorts();
        $sortQueries = [];
        if ($sort) {
            $sortGroups = explode(',', $sort);
            foreach ($sortGroups as $sortGroup) {
                if (!$sortGroup) continue;
                $this->getQuery($sortGroup, $configs, $sortQueries, $filerConfig);
            }
        }
        $defaultOrder = $filerConfig->defaultSort();
        if ($defaultOrder) {
            $this->getQuery($defaultOrder, $configs, $sortQueries, $filerConfig);
        }
        return $sortQueries;
    }

    private function getQuery(string $sort, array $config, array &$sortQueries, FilterConfig $filerConfig): void
    {
        [$sortField, $sortDirection] = explode(':', $sort, 2);
        if ($this->sortable($sortField, $config, $sortQueries)) {
            $sortConfig = $config[$sortField];
            if ($sortDirection && !in_array($sortDirection, ['asc', 'desc'])) {
                $sortDirection = 'desc';
            }
            if (is_string($sortConfig)) {
                $column = $sortConfig;
                $filerConfig->addJoin($column);
                $sortQueries[$sortField] = function ($query) use ($column, $sortDirection) {
                    $query->orderBy($column, $sortDirection);
                };
            } else if (is_callable($sortConfig)) {
                $sortQueries[$sortField] = $sortConfig($sortDirection, $filerConfig);
            }
        }
    }

    private function sortable(string $sortField, array $config, array $sortQueries): bool
    {
        return $sortField && isset($config[$sortField]) && !isset($sortQueries[$sortField]);
    }
}