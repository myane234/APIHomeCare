<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class Controller
{
    protected function paginateQuery($query, Request $request, int $defaultPerPage = 15): array
    {
        $perPage = $request->input('per_page', $defaultPerPage);

        if ($perPage === 'all') {
            return [$query->get(), null];
        }

        $perPage = max(1, min(100, (int) $perPage ?: $defaultPerPage));
        $paginator = $query->paginate($perPage)->appends($request->query());

        return [$paginator->items(), $this->paginationMeta($paginator)];
    }

    protected function paginateCollection(Collection $items, Request $request, int $defaultPerPage = 15): array
    {
        $perPage = $request->input('per_page', $defaultPerPage);

        if ($perPage === 'all') {
            return [$items->values(), null];
        }

        $perPage = max(1, min(100, (int) $perPage ?: $defaultPerPage));
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            $items->forPage($currentPage, $perPage)->values(),
            $items->count(),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        return [$paginator->items(), $this->paginationMeta($paginator)];
    }

    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'count' => $paginator->count(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'total_pages' => $paginator->lastPage(),
            'has_more_pages' => $paginator->hasMorePages(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
