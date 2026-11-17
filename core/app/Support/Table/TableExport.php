<?php

namespace App\Support\Table;

use App\Support\Dashboard\Text;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a table's filtered rows as CSV (UTF-8 with BOM for Excel), in batches, with formula cells defused.
 */
final class TableExport
{
    private const BATCH = 500;

    /**
     * @param  list<array{key: string, label: string, value: (callable(array<string, mixed>): string)|null}>  $columns
     * @param  iterable<int, array<string, mixed>>  $rows  the mapped rows, already in order
     */
    public static function respond(string $name, int $total, array $columns, iterable $rows): StreamedResponse
    {
        $filename = self::filename($name, $total);

        return new StreamedResponse(function () use ($columns, $rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, array_map(static fn (array $column): string => $column['label'], $columns));

            $written = 0;
            foreach ($rows as $row) {
                $line = [];
                foreach ($columns as $column) {
                    $value = $column['value'] !== null ? ($column['value'])($row) : self::scalar($row[$column['key']] ?? null);
                    $line[] = self::defuse($value);
                }
                fputcsv($out, $line);

                if (++$written % self::BATCH === 0) {
                    flush();
                }
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * @param  array<string, string|array{0: string, 1: callable(array<string, mixed>): string}>  $columns
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    public static function wanted(Request $request, string $name, array $columns, iterable $rows): ?StreamedResponse
    {
        if ($request->query('export') !== 'csv') {
            return null;
        }

        TableQuery::assertMayExport();

        $declared = [];
        foreach ($columns as $key => $column) {
            $declared[] = is_array($column)
                ? ['key' => $key, 'label' => $column[0], 'value' => $column[1]]
                : ['key' => $key, 'label' => $column, 'value' => null];
        }

        $all = is_array($rows) ? array_values($rows) : iterator_to_array($rows, false);

        return self::respond($name, count($all), $declared, $all);
    }

    public static function filename(string $name, int $total): string
    {
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', strtolower($name)) ?? 'export';

        return trim($slug, '-').'-'.now()->format('Y-m-d-Hi').'-'.$total.'-rows.csv';
    }

    private static function scalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? Text::get('common.yes') : Text::get('common.no');
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            if (array_key_exists('ar', $value)) {
                return self::text($value['ar']);
            }
            if (array_is_list($value) && $value !== [] && ! is_array($value[0])) {
                return implode(' | ', array_map(static fn (mixed $item): string => self::text($item), $value));
            }
        }

        return self::text(json_encode($value, JSON_UNESCAPED_UNICODE));
    }

    /** A trimmed string for any scalar, else an empty string. */
    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function defuse(string $value): string
    {
        return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'".$value : $value;
    }
}
