<?php

namespace App\Support\Table;

use App\Support\Activity\ActivityLogger;
use App\Support\Dashboard\Text;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The server half of the dashboard's DataTable: reads sort, search, filters and page from the request, applies
 * them to a query through whitelists the screen declares (never a column the request names), and returns the
 * payload the React table expects. Optional CSV export of the same filtered set (gate `export-data`).
 *
 * @phpstan-type TableMeta array{page: int, per_page: int, total: int, last_page: int, from: int|null, to: int|null, sort: string|null, direction: string, search: string|null, filters: array<string, string|null>, sortable: list<string>, filterable: list<string>, virtual: list<string>, exportable: bool}
 */
final class TableQuery
{
    /**
     * @var list<string>
     */
    private array $sortable = [];

    private ?string $defaultSort = null;

    private string $defaultDirection = 'asc';

    private ?string $tiebreaker = null;

    /**
     * @var array<string, string>
     */
    private array $sortNames = [];

    /**
     * @var list<string>
     */
    private array $searchable = [];

    /**
     * @var array<string, list<string>|null> column => allowed values, or null for "any scalar"
     */
    private array $filterable = [];

    /**
     * @var list<string>
     */
    private array $virtual = [];

    private int $perPageDefault = 25;

    private int $perPageMax = 100;

    /**
     * @var callable(EloquentBuilder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder, string): void|null
     */
    private $searchUsing = null;

    /**
     * @var list<array{key: string, label: string, value: (callable(array<string, mixed>): string)|null}>
     */
    private array $exportColumns = [];

    private string $exportName = 'export';

    private function __construct(private readonly Request $request) {}

    public static function for(Request $request): self
    {
        return new self($request);
    }

    /**
     * @param  list<string>  $columns  columns (or `table.column`) a caller may sort by
     */
    public function sortable(array $columns, ?string $default = null, string $direction = 'asc', ?string $tiebreaker = null): self
    {
        $names = [];
        foreach ($columns as $column) {
            $name = self::publicSortName($column);
            if (isset($names[$name]) && $names[$name] !== $column) {
                throw new InvalidArgumentException(
                    "Two sortable columns share the public name [{$name}]: [{$names[$name]}] and [{$column}]. "
                    .'Give one of them an unambiguous column name, or the URL cannot tell them apart.'
                );
            }
            $names[$name] = $column;
        }

        $this->sortable = $columns;
        $this->sortNames = $names;
        $this->defaultSort = $default ?? ($columns[0] ?? null);
        $this->defaultDirection = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $this->tiebreaker = $tiebreaker;

        return $this;
    }

    private static function publicSortName(string $column): string
    {
        $at = strrpos($column, '.');

        return $at === false ? $column : substr($column, $at + 1);
    }

    /**
     * @param  list<string>  $columns
     */
    public function searchable(array $columns): self
    {
        $this->searchable = $columns;

        return $this;
    }

    /**
     * @param  callable(EloquentBuilder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder, string): void  $callback
     */
    public function searchUsing(callable $callback): self
    {
        $this->searchUsing = $callback;

        return $this;
    }

    /**
     * @param  array<string, list<string>|null>  $filters  column => allowed values (null = any scalar)
     */
    public function filterable(array $filters): self
    {
        $this->filterable = $filters;

        return $this;
    }

    /**
     * @param  list<string>  $keys  keys that also appear in `filterable()`
     */
    public function virtual(array $keys): self
    {
        $this->virtual = $keys;

        return $this;
    }

    public function isVirtual(string $key): bool
    {
        return in_array($key, $this->virtual, true);
    }

    /**
     * @param  array<string, string|array{0: string, 1: callable(array<string, mixed>): string}>  $columns
     * @param  string  $name  the filename stem, ASCII, e.g. `activity`
     */
    public function exportable(array $columns, string $name): self
    {
        $this->exportName = $name;
        $this->exportColumns = [];

        foreach ($columns as $key => $column) {
            $this->exportColumns[] = is_array($column)
                ? ['key' => $key, 'label' => $column[0], 'value' => $column[1]]
                : ['key' => $key, 'label' => $column, 'value' => null];
        }

        return $this;
    }

    public function wantsExport(): bool
    {
        if ($this->exportColumns === [] || $this->request->query('export') !== 'csv') {
            return false;
        }

        self::assertMayExport();

        return true;
    }

    public static function assertMayExport(): void
    {
        if (Gate::allows('export-data')) {
            return;
        }

        abort(403, Text::get('table.export_forbidden'));
    }

    /**
     * @template TQuery of EloquentBuilder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder
     *
     * @param  TQuery  $query
     * @param  callable(object): array<string, mixed>  $map  the SCREEN's row callback, unchanged
     * @param  (callable(list<object>): void)|null  $prepare  the screen's batch loader, per chunk
     */
    public function export(EloquentBuilder|QueryBuilder $query, callable $map, ?callable $prepare = null): StreamedResponse
    {
        $applied = $this->apply($query);
        $total = $applied->getCountForPagination();
        // G6: every export is logged with who, when, which list, the filters and the row count.
        app(ActivityLogger::class)->record('export.csv', null, null, ['list' => $this->exportName, 'rows' => $total, 'filters' => array_filter($this->resolvedFilters(), static fn ($v): bool => $v !== null), 'search' => $this->resolvedSearch()]);

        return TableExport::respond($this->exportName, $total, $this->exportColumns, $this->stream($applied, $map, $prepare));
    }

    /**
     * @param  EloquentBuilder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $applied
     * @param  callable(object): array<string, mixed>  $map
     * @param  (callable(list<object>): void)|null  $prepare
     * @return iterable<int, array<string, mixed>>
     */
    private function stream(EloquentBuilder|QueryBuilder $applied, callable $map, ?callable $prepare): iterable
    {
        /**
         * @var list<object> $batch
         */
        $batch = [];

        foreach ($applied->cursor() as $item) {
            $batch[] = $item;
            if (count($batch) < 500) {
                continue;
            }
            yield from self::mapBatch($batch, $map, $prepare);
            $batch = [];
        }

        if ($batch !== []) {
            yield from self::mapBatch($batch, $map, $prepare);
        }
    }

    /**
     * @param  list<object>  $batch
     * @param  callable(object): array<string, mixed>  $map
     * @param  (callable(list<object>): void)|null  $prepare
     * @return list<array<string, mixed>>
     */
    private static function mapBatch(array $batch, callable $map, ?callable $prepare): array
    {
        if ($prepare !== null) {
            $prepare($batch);
        }

        $rows = [];
        foreach ($batch as $item) {
            $rows[] = $map($item);
        }

        return $rows;
    }

    public function perPage(int $default = 25, int $max = 100): self
    {
        $this->perPageDefault = max(1, $default);
        $this->perPageMax = max($this->perPageDefault, $max);

        return $this;
    }

    public function resolvedPerPage(): int
    {
        $value = $this->request->query('per_page');
        $requested = is_numeric($value) ? (int) $value : $this->perPageDefault;

        return max(1, min($requested, $this->perPageMax));
    }

    public function resolvedPage(): int
    {
        $value = $this->request->query('page');

        return max(1, is_numeric($value) ? (int) $value : 1);
    }

    public function resolvedSort(): ?string
    {
        $value = $this->request->query('sort');
        $requested = is_string($value) ? $value : null;

        if ($requested !== null && isset($this->sortNames[$requested])) {
            return $requested;
        }
        if ($requested !== null && in_array($requested, $this->sortable, true)) {
            return self::publicSortName($requested);
        }

        return $this->defaultSort === null ? null : self::publicSortName($this->defaultSort);
    }

    private function resolvedSortColumn(): ?string
    {
        $name = $this->resolvedSort();

        return $name === null ? null : ($this->sortNames[$name] ?? null);
    }

    public function resolvedDirection(): string
    {
        $value = $this->request->query('direction');
        $requested = is_string($value) ? strtolower($value) : null;

        return in_array($requested, ['asc', 'desc'], true) ? $requested : $this->defaultDirection;
    }

    public function resolvedSearch(): ?string
    {
        $value = $this->request->query('q');
        if (! is_string($value)) {
            return null;
        }
        $term = trim($value);

        return $term === '' ? null : mb_substr($term, 0, 100);
    }

    /**
     * @return array<string, string|null>
     */
    public function resolvedFilters(): array
    {
        /**
         * @var array<string, mixed> $raw
         */
        $raw = is_array($this->request->query('filters')) ? $this->request->query('filters') : [];
        $out = [];

        foreach ($this->filterable as $column => $allowed) {
            $value = $raw[$column] ?? null;
            if (! is_scalar($value)) {
                $out[$column] = null;

                continue;
            }
            $value = (string) $value;
            if ($value === '') {
                $out[$column] = null;

                continue;
            }
            $out[$column] = $allowed === null || in_array($value, $allowed, true) ? $value : null;
        }

        return $out;
    }

    /**
     * @template TQuery of EloquentBuilder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function apply(EloquentBuilder|QueryBuilder $query): EloquentBuilder|QueryBuilder
    {
        $search = $this->resolvedSearch();
        if ($search !== null) {
            if ($this->searchUsing !== null) {
                ($this->searchUsing)($query, $search);
            } elseif ($this->searchable !== []) {
                $columns = $this->searchable;
                $query->where(function (BuilderContract $inner) use ($columns, $search): void {
                    foreach ($columns as $column) {
                        $inner->orWhere($column, 'like', '%'.self::escapeLike($search).'%');
                    }
                });
            }
        }

        foreach ($this->resolvedFilters() as $column => $value) {
            if ($value !== null && ! $this->isVirtual($column)) {
                $query->where($column, $value);
            }
        }

        $sort = $this->resolvedSortColumn();
        if ($sort !== null) {
            $direction = $this->resolvedDirection() === 'desc' ? 'desc' : 'asc';
            $query->orderBy($sort, $direction);
            $tiebreaker = $this->tiebreaker ?? (str_contains($sort, 'id') ? null : self::qualify($query, 'id'));
            if ($tiebreaker !== null && $tiebreaker !== $sort) {
                $query->orderBy($tiebreaker, $direction);
            }
        }

        return $query;
    }

    /**
     * @template TQuery of EloquentBuilder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder
     *
     * @param  TQuery  $query
     * @param  callable(object): array<string, mixed>  $map  one row of the response per DB row
     * @param  (callable(list<object>): void)|null  $prepare  run ONCE with the page's raw rows,
     * @return array{data: list<array<string, mixed>>, meta: TableMeta}
     */
    public function paginate(EloquentBuilder|QueryBuilder $query, callable $map, ?callable $prepare = null): array
    {
        $perPage = $this->resolvedPerPage();
        $page = $this->resolvedPage();

        $paginator = $this->apply($query)->paginate(perPage: $perPage, page: $page);

        $items = [];
        foreach ($paginator->items() as $item) {
            if (! is_object($item)) {
                throw new InvalidArgumentException('A table row must be an object (model or stdClass).');
            }
            $items[] = $item;
        }

        if ($prepare !== null) {
            $prepare($items);
        }

        $rows = [];
        foreach ($items as $item) {
            $rows[] = $map($item);
        }

        return [
            'data' => $rows,
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'sort' => $this->resolvedSort(),
                'direction' => $this->resolvedDirection(),
                'search' => $this->resolvedSearch(),
                'filters' => $this->resolvedFilters(),
                'sortable' => array_keys($this->sortNames),
                'filterable' => array_keys($this->filterable),
                'virtual' => $this->virtual,
                'exportable' => $this->exportColumns !== [] && Gate::allows('export-data'),
            ],
        ];
    }

    private static function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }

    /**
     * @param  EloquentBuilder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    private static function qualify(EloquentBuilder|QueryBuilder $query, string $column): string
    {
        $base = $query instanceof EloquentBuilder ? $query->getQuery() : $query;
        $from = $base->from;
        if (! is_string($from) || $from === '') {
            return $column;
        }
        $parts = preg_split('/\s+as\s+/i', $from);
        $table = is_array($parts) ? trim((string) end($parts)) : $from;

        return $table.'.'.$column;
    }
}
