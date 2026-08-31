<?php

namespace App\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait HandlesListQueries
{
    protected function applyListFilters(Builder $query, Request $request, array $searchable = []): Builder
    {
        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($search, $searchable) {
                foreach ($searchable as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        return $query;
    }
}
