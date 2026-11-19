<?php

namespace App\Services\Import;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/** Lit la première feuille d'un classeur Excel en lignes associatives. */
class SpreadsheetReader
{
    public function __construct(private readonly ImportHelpers $helpers) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function read(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Le fichier téléversé est invalide.');
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getSheet(0)->toArray(null, true, true, false);

        if (count($rows) < 2) {
            return [];
        }

        $headers = array_map(
            fn (mixed $header): string => $this->helpers->normalizeHeader($header),
            array_shift($rows),
        );

        $records = [];
        foreach ($rows as $row) {
            $record = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $record[$header] = $row[$index] ?? null;
                }
            }

            $hasValue = false;
            foreach ($record as $value) {
                if ($value !== null && $value !== '') {
                    $hasValue = true;
                    break;
                }
            }

            if ($hasValue) {
                $records[] = $record;
            }
        }

        return $records;
    }
}
