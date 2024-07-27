<?php

namespace AnhTT\FilterBuilder;
class FilterConfig
{
    protected array $filters = [];
    protected array $joins = [];
    protected array $joinPriorities = [];
    protected array $sorts = [];
    private array $tablesUsed = [];
    private string $defaultSort = '';

    /**
     * @return array
     */
    public function getFilters(): array
    {
        return $this->filters;
    }


    public function setFilters(array $filters): FilterConfig
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * @return array
     */
    public function getSorts(): array
    {
        return $this->sorts;
    }

    public function setSorts(array $sorts): FilterConfig
    {
        $this->sorts = $sorts;
        return $this;
    }


    public function defaultSort(): string
    {
        return $this->defaultSort;
    }


    public function setDefaultSort(string $sort): FilterConfig
    {
        $this->defaultSort = $sort;
        return $this;
    }

    /**
     * @return array
     */
    public function getJoins(): array
    {
        return $this->joins;
    }

    public function setJoins(array $joins): FilterConfig
    {
        $this->joins = $joins;
        return $this;
    }

    public function getJoinPriority(): array
    {
        return $this->joinPriorities;
    }

    public function setJoinPriority(array $joinPriorities): FilterConfig
    {
        $this->joinPriorities = $joinPriorities;
        return $this;
    }

    public function addJoin($fields): FilterConfig
    {
        if (is_string($fields)) {
            [$table] = explode('.', $fields);
            $joinPriorities = $this->getJoinPriority();
            $tablePriorities = $joinPriorities[$table] ?? false;
            $tablePriorities && $this->addJoin($tablePriorities);
            $joinConfig = $this->joins[$table] ?? false;
            if ($joinConfig) {
                $this->tablesUsed[$table] = $joinConfig;
            }
        } else if (is_array($fields)) {
            foreach ($fields as $field) {
                $this->addJoin($field);
            }
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getTablesUsed(): array
    {
        return $this->tablesUsed;
    }

    public function getFilterQueries(array $requestData): array
    {
        $filterWhere = new FilterWhere();
        return $filterWhere->getQueries($this, $requestData);
    }

    public function getSortQueries($sort = null): array
    {
        $filterSort = new FilterSort();
        return $filterSort->getQueries($this, $sort);
    }

    public function getJoinQueries(): array
    {
        $filterJoin = new FilterJoin();
        return $filterJoin->getQueries($this);
    }
}