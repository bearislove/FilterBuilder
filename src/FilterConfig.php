<?php

namespace AnhTT\FilterBuilder;

class FilterConfig
{
    protected array $filters        = [];
    protected array $joins          = [];
    protected array $joinPriorities = [];
    protected array $sorts          = [];
    protected string $defaultSort   = '';

    /** @var array<string, callable> Additional formulas merged on top of builtins. */
    protected array $customFormulas = [];

    /** @var string[] Extra keys in the request payload treated as arrays of filter items. */
    protected array $arrayInputKeys = [];

    /** @var string[] Columns passed to ->select(). Empty means select *. */
    protected array $selects = [];

    /** @var array Relations passed to ->with(). */
    protected array $withs = [];

    private array $tablesUsed = [];

    // -------------------------------------------------------------------------
    // Filters
    // -------------------------------------------------------------------------

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function setFilters(array $filters): static
    {
        $this->filters = $filters;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Sorts
    // -------------------------------------------------------------------------

    public function getSorts(): array
    {
        return $this->sorts;
    }

    public function setSorts(array $sorts): static
    {
        $this->sorts = $sorts;
        return $this;
    }

    public function defaultSort(): string
    {
        return $this->defaultSort;
    }

    public function setDefaultSort(string $sort): static
    {
        $this->defaultSort = $sort;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Joins
    // -------------------------------------------------------------------------

    public function getJoins(): array
    {
        return $this->joins;
    }

    public function setJoins(array $joins): static
    {
        $this->joins = $joins;
        return $this;
    }

    public function getJoinPriority(): array
    {
        return $this->joinPriorities;
    }

    public function setJoinPriority(array $joinPriorities): static
    {
        $this->joinPriorities = $joinPriorities;
        return $this;
    }

    public function addJoin($fields): static
    {
        if (is_string($fields)) {
            [$table] = explode('.', $fields);

            $priorities = $this->getJoinPriority();
            if (isset($priorities[$table])) {
                $this->addJoin($priorities[$table]);
            }

            if (isset($this->joins[$table]) && !isset($this->tablesUsed[$table])) {
                $this->tablesUsed[$table] = $this->joins[$table];
            }
        } elseif (is_array($fields)) {
            foreach ($fields as $field) {
                $this->addJoin($field);
            }
        }

        return $this;
    }

    public function getTablesUsed(): array
    {
        return $this->tablesUsed;
    }

    // -------------------------------------------------------------------------
    // Custom formulas
    // -------------------------------------------------------------------------

    public function getCustomFormulas(): array
    {
        return $this->customFormulas;
    }

    /**
     * Register a custom filter formula.
     * Usage in filter config: 'field' => 'column:my_formula'
     */
    public function addFormula(string $name, callable $formula): static
    {
        $this->customFormulas[$name] = $formula;
        return $this;
    }

    public function setCustomFormulas(array $formulas): static
    {
        $this->customFormulas = $formulas;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Array input keys (wrapping keys in request payload)
    // -------------------------------------------------------------------------

    public function getArrayInputKeys(): array
    {
        return $this->arrayInputKeys;
    }

    /**
     * Override the keys in the request that contain arrays of filter items.
     * When empty, falls back to config('filter-builder.array_input_keys').
     */
    public function setArrayInputKeys(array $keys): static
    {
        $this->arrayInputKeys = $keys;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Select columns
    // -------------------------------------------------------------------------

    public function getSelects(): array
    {
        return $this->selects;
    }

    /**
     * Columns to pass to ->select(). Leave empty to select all columns.
     *
     * @param string[] $columns
     */
    public function setSelects(array $columns): static
    {
        $this->selects = $columns;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Eager loads
    // -------------------------------------------------------------------------

    public function getWiths(): array
    {
        return $this->withs;
    }

    /**
     * Relations to eager-load via ->with().
     *
     * @param array $relations
     */
    public function setWiths(array $relations): static
    {
        $this->withs = $relations;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Query builder factories
    // -------------------------------------------------------------------------

    public function getFilterQueries(array $requestData): array
    {
        return (new FilterWhere())->getQueries($this, $requestData);
    }

    public function getSortQueries(?string $sort = null): array
    {
        return (new FilterSort())->getQueries($this, $sort);
    }

    public function getJoinQueries(): array
    {
        return (new FilterJoin())->getQueries($this);
    }
}
