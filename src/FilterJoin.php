<?php

namespace AnhTT\FilterBuilder;

class FilterJoin
{
    public function getQueries(FilterConfig $filterConfig): array
    {
        $tables = $filterConfig->getTablesUsed();
        $defaultType = $this->defaultJoinType();
        $joinQueries = [];

        foreach ($tables as $tableConfig) {
            // Extended format: ['type' => 'leftJoin', 'args' => [...]]
            if (isset($tableConfig['args'])) {
                $type = $tableConfig['type'] ?? $defaultType;
                $args = $tableConfig['args'];
            } else {
                // Simple format: ['table', 'table.fk', '=', 'other.pk']
                $type = $defaultType;
                $args = $tableConfig;
            }

            $type = in_array($type, ['join', 'leftJoin', 'rightJoin'], true) ? $type : $defaultType;

            $joinQueries[] = function ($query) use ($type, $args) {
                return $query->{$type}(...$args);
            };
        }

        return $joinQueries;
    }

    private function defaultJoinType(): string
    {
        if (function_exists('config')) {
            return config('filter-builder.default_join_type', 'leftJoin');
        }
        return 'leftJoin';
    }
}
