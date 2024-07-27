<?php

namespace AnhTT\FilterBuilder;

class FilterBuilder
{
    private array $filterQueries;
    private array $joinQueries;
    private array $sortQueries;

    public function __construct(array $requestData, FilterConfig $filterConfig, $sort = '')
    {
        $this->filterQueries = $filterConfig->getFilterQueries($this->getFilterData($requestData));
        $this->sortQueries = $filterConfig->getSortQueries($sort);
        $this->joinQueries = $filterConfig->getJoinQueries();
    }


    public function apply($query)
    {
        $this->applyQueries($query, $this->filterQueries);
        $this->applyQueries($query, $this->joinQueries);
        $this->applyQueries($query, $this->sortQueries);
        return $query;
    }

    protected function getFilterData(array $requestData): array
    {
        $filterData = [];
        $whiteWrapList = ['keywords', 'periods'];
        foreach ($requestData as $key => $value) {
            if (in_array($key, $whiteWrapList)) {
                $filterData = array_merge($filterData, $value);
            } else {
                $filterData[] = ['name' => $key, 'value' => $value];
            }
        }
        return $filterData;
    }

    private function applyQueries($query, array $queries): void
    {
        foreach ($queries as $filterQuery) {
            $filterQuery($query);
        }
    }
}