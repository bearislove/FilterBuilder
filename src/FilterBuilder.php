<?php

namespace AnhTT\FilterBuilder;

class FilterBuilder
{
    private array $filterQueries;
    private array $joinQueries;
    private array $sortQueries;
    private array $selects;
    private array $withs;

    public function __construct(array $requestData, FilterConfig $filterConfig, string $sort = '')
    {
        $this->filterQueries = $filterConfig->getFilterQueries($this->extractFilterData($requestData, $filterConfig));
        $this->sortQueries   = $filterConfig->getSortQueries($sort);
        $this->joinQueries   = $filterConfig->getJoinQueries();
        $this->selects       = $filterConfig->getSelects();
        $this->withs         = $filterConfig->getWiths();
    }

    public function apply($query)
    {
        if (!empty($this->selects)) {
            $query->select($this->selects);
        }

        if (!empty($this->withs)) {
            $query->with($this->withs);
        }

        $this->applyQueries($query, $this->joinQueries);
        $this->applyQueries($query, $this->filterQueries);
        $this->applyQueries($query, $this->sortQueries);

        return $query;
    }

    private function extractFilterData(array $requestData, FilterConfig $filterConfig): array
    {
        $arrayInputKeys = $filterConfig->getArrayInputKeys() ?: $this->configArrayInputKeys();

        $filterData = [];
        foreach ($requestData as $key => $value) {
            if (in_array($key, $arrayInputKeys, true)) {
                if (is_array($value)) {
                    $filterData = array_merge($filterData, $value);
                }
            } else {
                $filterData[] = ['name' => $key, 'value' => $value];
            }
        }

        return $filterData;
    }

    private function configArrayInputKeys(): array
    {
        if (function_exists('config')) {
            return config('filter-builder.array_input_keys', ['keywords', 'periods']);
        }
        return ['keywords', 'periods'];
    }

    private function applyQueries($query, array $queries): void
    {
        foreach ($queries as $query_fn) {
            $query_fn($query);
        }
    }
}
