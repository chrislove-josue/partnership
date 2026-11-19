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

        // Les fichiers temporaires Livewire portent des métadonnées dans leur nom, ce qui casse
        // le flux zip:// de PhpSpreadsheet. On copie vers un chemin simple avant lecture.
        $cleanPath = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        copy($file->getRealPath(), $cleanPath);

        try {
            $spreadsheet = IOFactory::createReader('Xlsx')->load($cleanPath);
            $rows = $spreadsheet->getSheet(0)->toArray(null, true, true, false);
        } finally {
            @unlink($cleanPath);
        }

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
