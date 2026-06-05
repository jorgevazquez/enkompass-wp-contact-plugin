<?php
/**
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact\Tests\Unit;

use Enkompass\Contact\Csv_Writer;
use PHPUnit\Framework\TestCase;

final class CsvWriterTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir() . '/enk-csv-' . uniqid('', true) . '.csv';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
        parent::tearDown();
    }

    public function testNeutralizePrefixesFormulaTriggerCharacters(): void
    {
        $this->assertSame("'=SUM(A1)", Csv_Writer::neutralize('=SUM(A1)'));
        $this->assertSame("'+1", Csv_Writer::neutralize('+1'));
        $this->assertSame("'-1", Csv_Writer::neutralize('-1'));
        $this->assertSame("'@cmd", Csv_Writer::neutralize('@cmd'));
        $this->assertSame("'\tx", Csv_Writer::neutralize("\tx"));
        $this->assertSame("'\rx", Csv_Writer::neutralize("\rx"));
    }

    public function testNeutralizeLeavesOrdinaryTextAndNumbersUnchanged(): void
    {
        $this->assertSame('Jane Doe', Csv_Writer::neutralize('Jane Doe'));
        $this->assertSame('123 Main St', Csv_Writer::neutralize('123 Main St'));
        $this->assertSame('42', Csv_Writer::neutralize('42'));
        $this->assertSame('', Csv_Writer::neutralize(''));
    }

    public function testHeadersReturnsEmptyArrayWhenFileMissing(): void
    {
        $writer = new Csv_Writer($this->path);

        $this->assertSame([], $writer->headers());
    }

    public function testSyncHeaderCreatesFileAndHeadersReturnsColumns(): void
    {
        $writer = new Csv_Writer($this->path);

        $writer->syncHeader(['first_name', 'last_name', 'email']);

        $this->assertFileExists($this->path);
        $this->assertSame(['first_name', 'last_name', 'email'], $writer->headers());
    }

    public function testSyncHeaderRewritesHeaderWhilePreservingExistingDataRow(): void
    {
        $writer = new Csv_Writer($this->path);
        $writer->syncHeader(['first_name', 'email']);
        $writer->appendRow(['first_name' => 'Jane', 'email' => 'jane@example.com']);

        // Re-sync with an added column; the prior data row must survive.
        $writer->syncHeader(['first_name', 'email', 'company']);

        $this->assertSame(['first_name', 'email', 'company'], $writer->headers());

        $rows = $this->readRows($this->path);
        $this->assertSame(['first_name', 'email', 'company'], $rows[0]);
        $this->assertSame(['Jane', 'jane@example.com'], $rows[1]);
    }

    public function testAppendRowAlignsToHeaderFillsMissingAndIgnoresExtraKeys(): void
    {
        $writer = new Csv_Writer($this->path);
        $writer->syncHeader(['first_name', 'last_name', 'email']);

        // last_name missing -> ''; "phone" not in header -> ignored.
        $writer->appendRow([
            'email'      => 'jane@example.com',
            'first_name' => 'Jane',
            'phone'      => '555-1234',
        ]);

        $rows = $this->readRows($this->path);
        $this->assertSame(['first_name', 'last_name', 'email'], $rows[0]);
        $this->assertSame(['Jane', '', 'jane@example.com'], $rows[1]);
    }

    public function testAppendRowOnHeaderlessFileCreatesHeaderFromKeys(): void
    {
        $writer = new Csv_Writer($this->path);

        $writer->appendRow(['first_name' => 'Jane', 'email' => 'jane@example.com']);

        $this->assertSame(['first_name', 'email'], $writer->headers());

        $rows = $this->readRows($this->path);
        $this->assertSame(['first_name', 'email'], $rows[0]);
        $this->assertSame(['Jane', 'jane@example.com'], $rows[1]);
    }

    public function testAppendRowNeutralizesMaliciousCellOnWrite(): void
    {
        $writer = new Csv_Writer($this->path);
        $writer->syncHeader(['comments']);

        $writer->appendRow(['comments' => '=SUM(A1)']);

        $rows = $this->readRows($this->path);
        $this->assertSame("'=SUM(A1)", $rows[1][0]);
    }

    public function testValueContainingCommaRoundTripsCorrectly(): void
    {
        $writer = new Csv_Writer($this->path);
        $writer->syncHeader(['name', 'note']);

        $writer->appendRow(['name' => 'Doe, Jane', 'note' => 'line1, line2']);

        $rows = $this->readRows($this->path);
        $this->assertSame(['Doe, Jane', 'line1, line2'], $rows[1]);
    }

    public function testHasDataIsFalseForHeaderOnlyFile(): void
    {
        $writer = new Csv_Writer($this->path);
        $writer->syncHeader(['first_name', 'email']);

        $this->assertFalse($writer->hasData());
    }

    public function testHasDataIsFalseWhenFileMissing(): void
    {
        $writer = new Csv_Writer($this->path);

        $this->assertFalse($writer->hasData());
    }

    public function testHasDataIsTrueAfterAppendingARow(): void
    {
        $writer = new Csv_Writer($this->path);
        $writer->syncHeader(['first_name', 'email']);
        $writer->appendRow(['first_name' => 'Jane', 'email' => 'jane@example.com']);

        $this->assertTrue($writer->hasData());
    }

    public function testEmbeddedNewlineFieldRoundTripsAndIsTreatedAsOneRecord(): void
    {
        $writer = new Csv_Writer($this->path);
        $writer->syncHeader(['note']);
        $writer->appendRow(['note' => "line1\nline2"]);

        // A single multi-line field is still ONE data record.
        $this->assertTrue($writer->hasData());

        $rows = $this->readRows($this->path);
        $this->assertCount(2, $rows, 'header + one data record');
        $this->assertSame(["line1\nline2"], $rows[1]);

        // Re-syncing the header must preserve the multi-line field intact.
        $writer->syncHeader(['note', 'extra']);

        $rows = $this->readRows($this->path);
        $this->assertSame(['note', 'extra'], $rows[0]);
        $this->assertSame(["line1\nline2"], $rows[1]);
        $this->assertCount(2, $rows);
    }

    public function testHasDataIsFalseWhenHeaderCellContainsEmbeddedNewline(): void
    {
        $writer = new Csv_Writer($this->path);
        // A header whose cell contains a newline still constitutes ONE record
        // and therefore no data.
        $writer->syncHeader(["multi\nline"]);

        $this->assertFalse($writer->hasData());
    }

    /**
     * Read every CSV record from a file using fgetcsv.
     *
     * @return array<int, array<int, string>>
     */
    private function readRows(string $path): array
    {
        $rows   = [];
        $handle = fopen($path, 'r');
        while (false !== ($record = fgetcsv($handle, 0, ',', '"', ''))) {
            $rows[] = $record;
        }
        fclose($handle);

        return $rows;
    }
}
