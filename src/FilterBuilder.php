<?php

namespace AnhTT\FilterBuilder;

use AnhTT\FilterBuilder\Support\Cfg;

class FilterBuilder
{
    private array $filterQueries;
    private array $joinQueries;
    private array $sortQueries;
    private array $selects;
    private array $with;

    public function __construct(array $requestData, FilterConfig $filterConfig, string $sort = '')
    {
        $this->filterQueries = $filterConfig->getFilterQueries($this->extractFilterData($requestData, $filterConfig));
        $this->sortQueries   = $filterConfig->getSortQueries($sort);
        $this->joinQueries   = $filterConfig->getJoinQueries();
        $this->selects       = $filterConfig->getSelects();
        $this->with         = $filterConfig->getWith();
    }

    public function apply($query)
    {
        if ($this->selects) {
            $query->select($this->selects);
        }

        if ($this->with) {
            $query->with($this->with);
        }

        foreach ($this->joinQueries   as $fn) { $fn($query); }
        foreach ($this->filterQueries as $fn) { $fn($query); }
        foreach ($this->sortQueries   as $fn) { $fn($query); }

        return $query;
    }

    private function extractFilterData(array $requestData, FilterConfig $filterConfig): array
    {
        // Flip to a hash map so membership checks are O(1) instead of O(n).
        $arrayKeys  = array_flip(
            $filterConfig->getArrayInputKeys() ?: Cfg::get('array_input_keys', ['keywords', 'periods'])
        );

        $filterData = [];

        foreach ($requestData as $key => $value) {
            if (isset($arrayKeys[$key])) {
                // Unwrap array-of-filter-items — push each item directly to
                // avoid creating a new array on every array_merge call.
                if (is_array($value)) {
                    foreach ($value as $item) {
                        $filterData[] = $item;
                    }
                }
            } else {
                $filterData[] = ['name' => $key, 'value' => $value];
            }
        }

        return $filterData;
    }
}
