<?php

namespace AnhTT\FilterBuilder;

use AnhTT\FilterBuilder\Support\Cfg;

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
    protected array $with = [];

    private array $tablesUsed = [];

    // -------------------------------------------------------------------------
    // Static factory
    // -------------------------------------------------------------------------

    /**
     * Create a new FilterConfig instance.
     *
     * All parameters are optional — pass only what you need.
     * Named arguments (PHP 8+) make this very readable:
     *
     *   FilterConfig::make(
     *       filters:     ['name' => 'users.name:cn'],
     *       sorts:       ['name' => 'users.name'],
     *       defaultSort: 'id:desc',
     *   )
     */
    public static function make(
        array  $filters        = [],
        array  $sorts          = [],
        array  $joins          = [],
        array  $joinPriorities = [],
        string $defaultSort    = '',
        array  $selects        = [],
        array  $with           = [],
        array  $customFormulas = [],
        array  $arrayInputKeys = [],
    ): static {
        $instance = new static();

        if ($filters)        $instance->filters        = $filters;
        if ($sorts)          $instance->sorts          = $sorts;
        if ($joins)          $instance->joins          = $joins;
        if ($joinPriorities) $instance->joinPriorities = $joinPriorities;
        if ($defaultSort)    $instance->defaultSort    = $defaultSort;
        if ($selects)        $instance->selects        = $selects;
        if ($with)           $instance->with           = $with;
        if ($customFormulas) $instance->customFormulas = $customFormulas;
        if ($arrayInputKeys) $instance->arrayInputKeys = $arrayInputKeys;

        return $instance;
    }

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
            // strstr with before_needle=true is faster than explode()[0]
            // for extracting the table prefix from "table.column".
            $table = ($dot = strstr($fields, '.', true)) !== false ? $dot : $fields;

            // Read the property directly — skips a method call on every invocation.
            if (isset($this->joinPriorities[$table])) {
                $this->addJoin($this->joinPriorities[$table]);
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

    public function getWith(): array
    {
        return $this->with;
    }

    /**
     * Relations to eager-load via ->with().
     *
     * @param array $relations
     */
    public function setWith(array $relations): static
    {
        $this->with = $relations;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Query builder factories
    // -------------------------------------------------------------------------

    public function getFilterQueries(array $requestData): array
    {
        return Cfg::resolveHandler('where', FilterWhere::class)->getQueries($this, $requestData);
    }

    public function getSortQueries(?string $sort = null): array
    {
        return Cfg::resolveHandler('sort', FilterSort::class)->getQueries($this, $sort);
    }

    public function getJoinQueries(): array
    {
        return Cfg::resolveHandler('join', FilterJoin::class)->getQueries($this);
    }
}
