<?php
/**
 * CSV file manager for contact form submissions.
 *
 * Maintains a header row in sync with the configured fields and guards every
 * written cell against CSV injection (formula) attacks. Deliberately free of
 * WordPress dependencies so it can be unit tested against real temp files.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact;

/**
 * Reads and writes a single CSV file, keeping its header in sync.
 */
final class Csv_Writer
{
    /**
     * Characters that, when leading a cell, can be interpreted as a formula by
     * spreadsheet software (plus TAB and CR which can be used to break out).
     */
    private const INJECTION_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    public function __construct(private string $filePath)
    {
    }

    /**
     * Guard a single cell against CSV-injection: prefix a leading formula
     * trigger character with a single quote so it is treated as plain text.
     */
    public static function neutralize(string $cell): string
    {
        if ('' === $cell) {
            return $cell;
        }

        if (in_array($cell[0], self::INJECTION_TRIGGERS, true)) {
            return "'" . $cell;
        }

        return $cell;
    }

    /**
     * Parse and return the column names from the file's first CSV record.
     *
     * @return array<int, string> Empty when the file is missing or empty.
     */
    public function headers(): array
    {
        $records = $this->records();

        return $records[0] ?? [];
    }

    /**
     * Ensure the file exists and set its first record to the given header columns.
     *
     * Existing data records (every record after the header) are preserved,
     * re-encoded byte-for-byte equivalently — including any embedded commas,
     * quotes or newlines — in their original order.
     *
     * @param array<int, string> $headers
     */
    public function syncHeader(array $headers): void
    {
        $records = $this->records();
        array_shift($records); // Drop the existing header (if any); keep data records.

        $lines = [$this->encodeRow($headers)];
        foreach ($records as $record) {
            $lines[] = $this->encodeRow($record);
        }

        file_put_contents($this->filePath, implode("\n", $lines) . "\n");
    }

    /**
     * Append one data row, aligned to the current header order.
     *
     * Values are pulled by header name (missing keys become ''); keys not in
     * the header are ignored. When no header exists yet, one is created from
     * the keys of the given associative array before the row is appended.
     *
     * @param array<string, string> $assoc
     */
    public function appendRow(array $assoc): void
    {
        $headers = $this->headers();
        if ([] === $headers) {
            $headers = array_keys($assoc);
            $this->syncHeader($headers);
        }

        $ordered = [];
        foreach ($headers as $column) {
            $ordered[] = (string) ($assoc[$column] ?? '');
        }

        $line = $this->encodeRow($ordered);
        file_put_contents($this->filePath, $line . "\n", FILE_APPEND);
    }

    /**
     * Whether the file exists and holds at least one data record beyond header.
     */
    public function hasData(): bool
    {
        return count($this->records()) > 1;
    }

    /**
     * Encode one row of cells into a single CSV-formatted line (no trailing EOL).
     *
     * Every cell is neutralized before encoding to guard against CSV injection.
     *
     * @param array<int, string> $cells
     */
    private function encodeRow(array $cells): string
    {
        $neutralized = array_map(
            static fn ($cell): string => self::neutralize((string) $cell),
            $cells
        );

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $neutralized, ',', '"', '\\', '');
        rewind($stream);
        $line = (string) stream_get_contents($stream);
        fclose($stream);

        return rtrim($line, "\r\n");
    }

    /**
     * Parse the file into CSV records using fgetcsv.
     *
     * Each record is an array of cell strings. A field that contains embedded
     * commas, quotes or newlines is correctly returned as a single cell, so a
     * multi-line quoted field counts as exactly one record. Robust to a trailing
     * newline at end of file (fgetcsv yields no spurious empty record).
     *
     * @return array<int, array<int, string>>
     */
    private function records(): array
    {
        if (!is_file($this->filePath)) {
            return [];
        }

        $handle = fopen($this->filePath, 'r');
        if (false === $handle) {
            return [];
        }

        $records = [];
        while (false !== ($record = fgetcsv($handle, 0, ',', '"', ''))) {
            // fgetcsv yields [null] for a blank line; skip such empty records.
            if ([null] === $record) {
                continue;
            }
            $records[] = array_map(static fn ($cell): string => (string) $cell, $record);
        }
        fclose($handle);

        return $records;
    }
}
