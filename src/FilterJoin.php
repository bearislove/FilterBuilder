<?php

namespace AnhTT\FilterBuilder;

class FilterJoin
{
    public function getQueries(FilterConfig $filerConfig): array
    {
        $tables = $filerConfig->getTablesUsed();
        $joinQueries = [];
        foreach ($tables as $tableConfig) {
            $joinQueries[] = function ($query) use ($tableConfig) {
                return $query->join(...$tableConfig);
            };
        }
        return $joinQueries;
    }
}