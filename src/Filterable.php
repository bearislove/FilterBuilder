<?php

namespace AnhTT\FilterBuilder;

trait Filterable
{
    /**
     * @method static filterBuilder(array $requestData, FilterConfig $filterConfig): Builder
     */
    public function scopeFilterBuilder($query, array $requestData, FilterConfig $filterConfig, $sort = '')
    {
        $filterBuilder = new FilterBuilder($requestData, $filterConfig, $sort);
        return $filterBuilder->apply($query);
    }
}