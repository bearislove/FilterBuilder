<?php

namespace AnhTT\FilterBuilder;

/**
 * Declares a request key's column, filter formula, and sort column in one place.
 * Use with FilterConfig::make(fields: [...]) to avoid repeating the same column
 * in both the filters and sorts arrays.
 *
 * Examples:
 *
 *   // Filter + sort on the same column
 *   'name' => FilterField::make('users.name')->filter('cn')->sortable()
 *
 *   // Filter only
 *   'email' => FilterField::make('users.email')->filter('eq')
 *
 *   // Sort only (no filter formula)
 *   'created_at' => FilterField::make('users.created_at')->sortable()
 *
 *   // Sort on a different column than the filter column
 *   'role' => FilterField::make('roles.name')->filter('eq')->sortable('roles.sort_order')
 *
 *   // OR-group: WHERE (col1 LIKE ? OR col2 LIKE ?)
 *   'search' => FilterField::any('users.name:cn', 'users.email:cn')
 */
class FilterField
{
    private string|array $column;
    private ?string $formula    = null;
    private bool $isSortable    = false;
    private ?string $sortColumn = null;

    private function __construct(string|array $column)
    {
        $this->column = $column;
    }

    // -------------------------------------------------------------------------
    // Named constructors
    // -------------------------------------------------------------------------

    /**
     * Single-column field.
     * Chain ->filter('formula') and/or ->sortable() to configure it.
     */
    public static function make(string $column): static
    {
        return new static($column);
    }

    /**
     * OR-group field — matches when ANY of the given column:formula pairs match.
     * Produces a WHERE (col1 op val OR col2 op val ...) clause.
     *
     * FilterField::any('users.name:cn', 'users.email:cn')
     */
    public static function any(string ...$columnFormulas): static
    {
        return new static(array_values($columnFormulas));
    }

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    /**
     * Set the filter formula for this field (e.g. 'cn', 'eq', 'in').
     * Not needed when using FilterField::any() — formulas are part of each entry.
     */
    public function filter(string $formula): static
    {
        $this->formula = $formula;
        return $this;
    }

    /**
     * Mark this field as sortable.
     *
     * @param string|null $column  Override sort column (defaults to the filter column).
     */
    public function sortable(?string $column = null): static
    {
        $this->isSortable = true;
        $this->sortColumn = $column;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Internal accessors used by FilterConfig::make()
    // -------------------------------------------------------------------------

    public function hasFilter(): bool
    {
        // OR-group (array column) always has filter definitions baked in
        return is_array($this->column) || $this->formula !== null;
    }

    public function toFilterValue(): string|array
    {
        if (is_array($this->column)) {
            return $this->column;
        }
        return "{$this->column}:{$this->formula}";
    }

    public function isSortableField(): bool
    {
        return $this->isSortable;
    }

    public function toSortColumn(): string
    {
        // OR-group fields cannot be sorted — caller should not mark them sortable,
        // but return the first entry's column as a safe fallback.
        if (is_array($this->column)) {
            $first = $this->column[0] ?? '';
            return explode(':', $first, 2)[0];
        }

        return $this->sortColumn ?? $this->column;
    }
}
