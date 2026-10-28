<?php

namespace App\Services\Concerns;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

trait ImportsSpreadsheets
{
    public const MAX_IMPORT_ROWS = 5000;

    /**
     * Read the first sheet of an xlsx file.
     *
     * @return array{headers: list<string>, rows: list<array{number: int, values: list<mixed>}>}
     */
    public function readSpreadsheet(string $path): array
    {
        $reader = new Reader;
        $headers = [];
        $rows = [];

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $number => $row) {
                    $values = array_map(fn ($value) => $this->normalizeCell($value), $row->toArray());

                    if ($number === 1) {
                        $headers = array_map(fn ($value) => (string) ($value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value), $values);

                        continue;
                    }

                    if (collect($values)->every(fn ($value) => $value === '')) {
                        continue;
                    }

                    if (count($rows) >= self::MAX_IMPORT_ROWS) {
                        throw ValidationException::withMessages([
                            'file' => 'Maksimal '.number_format(self::MAX_IMPORT_ROWS, 0, ',', '.').' baris data per berkas.',
                        ]);
                    }

                    $rows[] = ['number' => $number, 'values' => $values];
                }

                break;
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'Berkas tidak dapat dibaca. Pastikan formatnya .xlsx yang valid.']);
        } finally {
            try {
                $reader->close();
            } catch (Throwable) {
                // The reader was never opened.
            }
        }

        if ($headers === []) {
            throw ValidationException::withMessages(['file' => 'Berkas kosong atau baris judul tidak ditemukan.']);
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param  array<string, string>  $fields  field name => column label
     * @param  array<string, string>  $sample  field name => example value
     */
    protected function writeTemplateFile(string $path, array $fields, array $sample): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_values($fields)));
        $writer->addRow(Row::fromValues(array_map(fn ($field) => $sample[$field] ?? '', array_keys($fields))));
        $writer->close();
    }

    /**
     * @param  list<mixed>  $values
     * @param  array<int, string>  $mapping
     * @param  array<string, string>  $fields
     * @return array<string, mixed>
     */
    protected function mapRow(array $values, array $mapping, array $fields): array
    {
        $record = [];

        foreach ($mapping as $index => $field) {
            if (! isset($fields[$field]) || isset($record[$field])) {
                continue;
            }

            $record[$field] = $this->normalizeField($field, $values[$index] ?? '');
        }

        return $record;
    }

    protected function normalizeField(string $field, mixed $value): string
    {
        $text = $value instanceof DateTimeInterface ? $value->format('Y-m-d') : trim((string) $value);

        return match ($field) {
            'nik' => preg_replace("/[\s']/", '', $text),
            'birth_date' => $this->normalizeDate($value),
            'gender' => $this->normalizeGender($text),
            default => $text,
        };
    }

    protected function normalizeDate(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $text = trim((string) $value);

        foreach (['!d/m/Y', '!d-m-Y', '!Y-m-d', '!d/m/y'] as $format) {
            if (Carbon::canBeCreatedFromFormat($text, $format)) {
                return Carbon::createFromFormat($format, $text)->format('Y-m-d');
            }
        }

        return $text;
    }

    protected function normalizeGender(string $text): string
    {
        return match (mb_strtolower($text)) {
            'l', 'laki-laki', 'laki laki', 'pria', 'male' => 'Laki-laki',
            'p', 'perempuan', 'wanita', 'female' => 'Perempuan',
            default => $text,
        };
    }

    protected function normalizeCell(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
        }

        return trim((string) $value);
    }

    protected function normalizeHeader(string $header): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($header));
    }

    protected function nullIfBlank(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
