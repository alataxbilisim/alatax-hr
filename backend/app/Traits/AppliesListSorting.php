<?php

namespace App\Traits;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Liste uçlarında istemci sıralama parametresi yalnızca beyaz listedeki kolonlara uygulanır;
 * listede olmayan kolon/yön 422 döner (hassas kolona göre sıralama sızıntısını önler).
 */
trait AppliesListSorting
{
    /**
     * @param  list<string>  $allowedColumns
     */
    protected function applyListSorting(
        Builder $query,
        Request $request,
        array $allowedColumns,
        string $defaultColumn = 'created_at',
        string $sortKey = 'sort_by',
        string $directionKey = 'sort_dir',
    ): void {
        $validated = $request->validate([
            $sortKey => ['nullable', 'string', Rule::in($allowedColumns)],
            $directionKey => ['nullable', 'string', Rule::in(['asc', 'desc', 'ASC', 'DESC'])],
        ]);

        $query->orderBy(
            $validated[$sortKey] ?? $defaultColumn,
            strtolower($validated[$directionKey] ?? 'desc'),
        );
    }
}
