<?php

namespace AnhTT\FilterBuilder;

use AnhTT\FilterBuilder\Support\Cfg;

class FilterJoin
{
    private const VALID_TYPES = ['join', 'leftJoin', 'rightJoin'];

    public function getQueries(FilterConfig $filterConfig): array
    {
        $tables      = $filterConfig->getTablesUsed();
        $defaultType = Cfg::get('default_join_type', 'leftJoin');
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

            $type = in_array($type, self::VALID_TYPES, true) ? $type : $defaultType;

            $joinQueries[] = fn ($query) => $query->{$type}(...$args);
        }

        return $joinQueries;
    }
}
