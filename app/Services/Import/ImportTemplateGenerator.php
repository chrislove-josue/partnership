<?php

namespace App\Services\Import;

use App\Support\DonationCategoryNames;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Modèle Excel téléchargeable pour les imports (port des pages /partners/import et /donations/import). */
class ImportTemplateGenerator
{
    private const COLUMNS = [
        ['firstName', 18, 'Prénom du partenaire (requis si nouveau partenaire)'],
        ['lastName', 18, 'Nom du partenaire (requis si nouveau partenaire)'],
        ['email', 28, 'Email du partenaire EXISTANT (clé de reconnaissance)'],
        ['phone', 20, 'Téléphone au format international, ex : +22961000000'],
        ['handleKC', 16, 'Identifiant KingsChat (ex : @nestor229)'],
        ['churchName', 20, "Nom exact de l'église (créée si absente)"],
        ['donationAmount', 16, 'Nombre uniquement (sans devise ni espace)'],
        ['donationDate', 14, 'Format AAAA-MM-JJ, ex : 2026-11-01'],
        ['donationCategoryName', 22, 'Ex : Offrandes, Dîmes (créée si absente)'],
    ];

    public function toXlsx(bool $withDonation = true): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($withDonation ? 'Dons' : 'Partenaires');

        $headers = array_column(self::COLUMNS, 0);
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach (self::COLUMNS as $index => [$name, $width]) {
            $sheet->getColumnDimensionByColumn($index + 1)->setWidth($width);
        }

        $examples = $withDonation ? [
            ['Nestor', 'ATCHOUGO', 'nestor@example.test', '+22961981233', '@nestor229', 'CE SIKECODJI', 5000, '2026-11-01', DonationCategoryNames::CANONICAL[1]],
            ['Marie', 'Dossou', 'marie@example.test', '+22961000002', '@marie2', 'CE SIKECODJI', 3000, '2024-02-02', DonationCategoryNames::CANONICAL[0]],
        ] : [
            ['Nestor', 'ATCHOUGO', 'nestor@example.test', '+22961981233', '@nestor229', 'CE SIKECODJI', null, null, null],
            ['Marie', 'Dossou', 'marie@example.test', '+22961000002', '@marie2', 'CE SIKECODJI', null, null, null],
        ];

        $row = 2;
        foreach ($examples as $example) {
            $sheet->fromArray($example, null, "A{$row}");
            $row++;
        }

        // Feuille d'aide sur les colonnes.
        $help = $spreadsheet->createSheet();
        $help->setTitle('Aide');
        $help->fromArray(['Colonne', 'Description'], null, 'A1');
        foreach (self::COLUMNS as $index => [$name, , $description]) {
            $help->setCellValue('A'.($index + 2), $name);
            $help->setCellValue('B'.($index + 2), $description);
        }
        $help->getColumnDimension('A')->setWidth(24);
        $help->getColumnDimension('B')->setWidth(60);
        $help->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F4E78']],
            'borders' => ['allBorders' => ['style' => Border::BORDER_THIN, 'color' => ['argb' => 'FFBFBFBF']]],
        ]);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }
}
