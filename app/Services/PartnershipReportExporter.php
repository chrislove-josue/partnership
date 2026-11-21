<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Port de generatePartnershipReportXlsx() (lib/report.ts).
 * Produit un classeur Excel avec 4 feuilles : Résumé, Dons par catégorie,
 * Partenaires et (si besoin) Sans don sur la période.
 */
class PartnershipReportExporter
{
    private const FONT = 'Arial';
    private const BLUE = 'FF1F4E78';
    private const YELLOW = 'FFFFF2CC';
    private const GRID = 'FFBFBFBF';
    private const STRIPE = 'FFF7F9FC';
    private const ORANGE = 'FFBF9000';
    private const ORANGE_STRIPE = 'FFFDF6E3';
    private const CURRENCY_FORMAT = '#,##0 "FCFA"';

    public function __construct(private readonly PartnershipReportService $reports) {}

    /**
     * @param  array<string, mixed>  $report
     */
    public function toXlsx(array $report): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setCreator('Rapport partenariat — '.$report['church']['name']);

        $this->buildSummary($spreadsheet->getActiveSheet(), $report);
        $this->buildCategories($spreadsheet->createSheet(), $report);
        $this->buildPartners($spreadsheet->createSheet(), $report);

        if ($report['partnersWithoutDonation'] !== []) {
            $this->buildPartnersWithoutDonation($spreadsheet->createSheet(), $report);
        }

        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function buildSummary(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle('Résumé');
        $sheet->setShowGridlines(false);
        $sheet->getColumnDimension('A')->setWidth(34);
        $sheet->getColumnDimension('B')->setWidth(50);

        $sheet->setCellValue('A1', self::safeText('Rapport de partenariat — '.$report['church']['name']));
        $sheet->mergeCells('A1:B1');
        $sheet->getRowDimension(1)->setRowHeight(34);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['name' => self::FONT, 'bold' => true, 'size' => 16, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => $this->fill(self::BLUE),
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);

        $church = $report['church'];
        $churchLabel = $church['name'].($church['parentName'] ? ' (rattachée à '.$church['parentName'].')' : '');
        $totals = $report['totals'];

        $rows = [
            ['Église', $churchLabel],
            ['Période', $report['period']['label']],
            ['Date de génération', now()->translatedFormat('d/m/Y H:i:s')],
            ['Total des dons', $this->reports->formatAmount($totals['amount'])],
            ['Nombre de dons', (string) $totals['donationCount']],
            ['Partenaires inscrits', (string) $totals['registeredPartners']],
            ['Partenaires ayant donné', (string) $totals['partnersWithDonation']],
            ['Partenaires sans don sur la période', (string) $totals['partnersWithoutDonation']],
        ];

        foreach ($rows as $i => [$label, $value]) {
            $row = $i + 3;
            $sheet->setCellValue("A{$row}", self::safeText($label));
            $sheet->setCellValue("B{$row}", self::safeText($value));
            $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                'font' => ['name' => self::FONT],
                'borders' => $this->borders(),
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            ]);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);

            if ($label === 'Total des dons') {
                $sheet->getStyle("B{$row}")->applyFromArray([
                    'font' => ['name' => self::FONT, 'bold' => true, 'size' => 12],
                    'fill' => $this->fill(self::YELLOW),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function buildCategories(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle('Dons par catégorie');
        $sheet->setShowGridlines(false);
        $sheet->freezePane('A2');
        $sheet->getColumnDimension('A')->setWidth(26);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(20);

        $this->writeHeader($sheet, 'A1:C1', ['Catégorie', 'Nombre de dons', 'Montant total']);

        $row = 2;
        foreach ($report['categories'] as $i => $category) {
            $sheet->setCellValue("A{$row}", self::safeText($category['name']));
            $sheet->setCellValue("B{$row}", $category['count']);
            $sheet->setCellValue("C{$row}", $category['amount']);
            $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
            $this->stripe($sheet, "A{$row}:C{$row}", $i % 2 === 1 ? self::STRIPE : null);
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->setCellValue("B{$row}", $report['totals']['donationCount']);
        $sheet->setCellValue("C{$row}", $report['totals']['amount']);
        $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
        $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
            'font' => ['name' => self::FONT, 'bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => $this->fill(self::BLUE),
            'borders' => $this->borders(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function buildPartners(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle('Partenaires');
        $sheet->setShowGridlines(false);
        $sheet->freezePane('A2');
        foreach (['A' => 24, 'B' => 20, 'C' => 28, 'D' => 18, 'E' => 10, 'F' => 16] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $this->writeHeader($sheet, 'A1:F1', ['Partenaire', 'Cellule', 'Email', 'Téléphone', 'Nb dons', 'Total doné']);

        $row = 2;
        foreach ($report['partners'] as $i => $partner) {
            $sheet->setCellValue("A{$row}", self::safeText($partner['name']));
            $sheet->setCellValue("B{$row}", self::safeText($partner['cellule'] ?? '—'));
            $sheet->setCellValue("C{$row}", self::safeText($partner['email'] ?? '—'));
            $sheet->setCellValue("D{$row}", self::safeText($partner['phone'] ?? '—'));
            $sheet->setCellValue("E{$row}", $partner['count']);
            $sheet->setCellValue("F{$row}", $partner['amount']);
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
            $this->stripe($sheet, "A{$row}:F{$row}", $i % 2 === 1 ? self::STRIPE : null);
            $row++;
        }
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function buildPartnersWithoutDonation(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle('Sans don sur la période');
        $sheet->setShowGridlines(false);
        foreach (['A' => 24, 'B' => 20, 'C' => 28, 'D' => 18] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $this->writeHeader($sheet, 'A1:D1', ['Partenaire', 'Cellule', 'Email', 'Téléphone'], self::ORANGE);

        $row = 2;
        foreach ($report['partnersWithoutDonation'] as $i => $partner) {
            $sheet->setCellValue("A{$row}", self::safeText($partner['name']));
            $sheet->setCellValue("B{$row}", self::safeText($partner['cellule'] ?? '—'));
            $sheet->setCellValue("C{$row}", self::safeText($partner['email'] ?? '—'));
            $sheet->setCellValue("D{$row}", self::safeText($partner['phone'] ?? '—'));
            $this->stripe($sheet, "A{$row}:D{$row}", $i % 2 === 1 ? self::ORANGE_STRIPE : null);
            $row++;
        }
    }

    /** Neutralise les formules Excel injectées via un nom, un email ou un téléphone. */
    private static function safeText(string $value): string
    {
        return preg_match('/^[\s]*[=+\-@]/', $value) ? "'".$value : $value;
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function writeHeader(Worksheet $sheet, string $range, array $headers, string $color = self::BLUE): void
    {
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['name' => self::FONT, 'bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => $this->fill($color),
            'borders' => $this->borders(),
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);
    }

    private function stripe(Worksheet $sheet, string $range, ?string $color): void
    {
        $style = [
            'font' => ['name' => self::FONT],
            'borders' => $this->borders(),
        ];

        if ($color !== null) {
            $style['fill'] = $this->fill($color);
        }

        $sheet->getStyle($range)->applyFromArray($style);
    }

    /**
     * @return array<string, mixed>
     */
    private function fill(string $color): array
    {
        return [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['argb' => $color],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function borders(): array
    {
        return [
            'allBorders' => [
                'style' => Border::BORDER_THIN,
                'color' => ['argb' => self::GRID],
            ],
        ];
    }
}
