<?php

namespace AnhTT\FilterBuilder;

abstract class FilterForm extends FilterConfig
{
    public function __construct()
    {
        $this->filters        = $this->filters();
        $this->sorts          = $this->sorts();
        $this->joins          = $this->joins();
        $this->joinPriorities = $this->joinPriorities();
        $this->defaultSort    = $this->defaultSort();
    }

    /**
     * Define filter field mappings.
     * Keys are request parameter names; values are column:formula strings,
     * callables, or arrays of the above for OR-grouped matching.
     *
     * @return array<string, string|callable|array>
     */
    abstract protected function filters(): array;

    /**
     * Define sortable field mappings.
     * Keys are sort field names; values are column strings or callables.
     *
     * @return array<string, string|callable>
     */
    protected function sorts(): array
    {
        return [];
    }

    /**
     * Define available JOIN configurations.
     * Simple format:  'table' => ['table', 'table.fk', '=', 'other.pk']
     * Extended format: 'table' => ['type' => 'leftJoin', 'args' => [...]]
     *
     * @return array<string, array>
     */
    protected function joins(): array
    {
        return [];
    }

    /**
     * Define join dependency chains.
     * If joining table A requires table B to be joined first, map A => B (or A => [B, C]).
     *
     * @return array<string, string|array>
     */
    protected function joinPriorities(): array
    {
        return [];
    }

    /**
     * Default sort applied when no sort parameter is present in the request.
     * Format: "field:direction" (e.g. "created_at:desc")
     */
    protected function defaultSort(): string
    {
        return '';
    }
}
