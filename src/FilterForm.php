<?php

namespace AnhTT\FilterBuilder;

abstract class FilterForm extends FilterConfig
{

    public function __construct()
    {
        $this->filters = $this->getFilters();
        $this->sorts = $this->getSorts();
        $this->joins = $this->getJoins();
    }

    protected abstract function filters(): array;


    public function getFilters(): array
    {
        return $this->filters();
    }

}