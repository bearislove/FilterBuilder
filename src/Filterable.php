<?php

namespace AnhTT\FilterBuilder;

use AnhTT\FilterBuilder\Support\Cfg;
use Illuminate\Http\Request;

trait Filterable
{
    /**
     * Apply filters, sorts, and joins to the query.
     *
     * Signatures:
     *
     *   // Auto-resolve request from container (keys from config)
     *   Model::filterBuilder($filterConfig)->paginate();
     *
     *   // Pass a specific Request instance
     *   Model::filterBuilder($filterConfig, $request)->paginate();
     *
     *   // Pass raw data + sort string explicitly (full control)
     *   Model::filterBuilder($filterConfig, $request->all(), 'name:asc')->paginate();
     *
     * @param mixed  $query
     * @param FilterConfig        $filterConfig
     * @param Request|array|null  $requestOrData  Request, raw array, or null (auto-resolve)
     * @param string|null         $sort           Sort string override; null = read from request
     */
    public function scopeFilterBuilder(
        $query,
        FilterConfig $filterConfig,
        Request|array|null $requestOrData = null,
        ?string $sort = null
    ) {
        [$requestData, $sort] = $this->resolveRequestArgs($filterConfig, $requestOrData, $sort);

        return (new FilterBuilder($requestData, $filterConfig, $sort))->apply($query);
    }

    private function resolveRequestArgs(
        FilterConfig $filterConfig,
        Request|array|null $requestOrData,
        ?string $sort
    ): array {
        // ── Explicit array ───────────────────────────────────────────────────
        if (is_array($requestOrData)) {
            return [$requestOrData, $sort ?? ''];
        }

        // ── Request instance or auto-resolve ─────────────────────────────────
        $request = $requestOrData instanceof Request
            ? $requestOrData
            : request();

        $dataKey = Cfg::get('request_data_key');
        $sortKey = Cfg::get('sort_key', 'sort');

        $requestData = $dataKey
            ? (array) $request->input($dataKey, [])
            : $request->all();

        // Per-config sort key override takes precedence over config default,
        // but an explicit $sort string passed by the caller wins over both.
        $resolvedSort = $sort ?? $request->input($sortKey, '');

        return [$requestData, (string) $resolvedSort];
    }
}
