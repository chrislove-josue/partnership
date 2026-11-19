<?php

namespace App\Services\Import;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/** Port de lib/import-helpers.ts : dates, téléphones, handle KingsChat et montants. */
class ImportHelpers
{
    /** Date au format AAAA-MM-JJ, JJ/MM/AAAA ou nombre de série Excel. */
    public function parseDateFlexible(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        // Nombre de série Excel (1900 = epoch).
        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
            } catch (Throwable) {
                return null;
            }
        }

        $text = trim((string) $value);

        // AAAA-MM-JJ strict : 2026-02-31 est rejeté (pas de roulement automatique).
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];

            return checkdate($month, $day, $year)
                ? Carbon::create($year, $month, $day, 0, 0, 0)
                : null;
        }

        // JJ/MM/AAAA ou MM/JJ/AAAA (le premier nombre > 12 est le jour).
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $text, $matches)) {
            $first = (int) $matches[1];
            $second = (int) $matches[2];
            $year = (int) $matches[3];
            $month = $first > 12 ? $second : $first;
            $day = $first > 12 ? $first : $second;

            return checkdate($month, $day, $year)
                ? Carbon::create($year, $month, $day, 0, 0, 0)
                : null;
        }

        try {
            return Carbon::parse($text);
        } catch (Throwable) {
            return null;
        }
    }

    /** Normalise un numéro béninois en format international (+229…). */
    public function normalizePhone(mixed $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $cleaned = preg_replace('/\s+/', '', trim((string) $phone));
        $cleaned = preg_replace('/[^0-9+]/', '', $cleaned);

        if (str_starts_with($cleaned, '00')) {
            $cleaned = '+'.substr($cleaned, 2);
        }
        if (str_starts_with($cleaned, '+00')) {
            $cleaned = '+'.substr($cleaned, 3);
        }
        if (str_starts_with($cleaned, '229') && ! str_starts_with($cleaned, '+')) {
            $cleaned = '+'.$cleaned;
        }
        if (! str_starts_with($cleaned, '+')) {
            $cleaned = '+229'.$cleaned;
        }

        return $cleaned;
    }

    /** Normalise un identifiant KingsChat en @handle sans espace. */
    public function normalizeHandleKC(mixed $handle): ?string
    {
        if ($handle === null || $handle === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/', '', trim((string) $handle));

        return '@'.ltrim($normalized, '@');
    }

    /** Tolère « 5 000 », « 5.000 », « 5,000 », « 50x00FCFA ». */
    public function parseAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $number = (float) $value;

            return $number > 0 ? $number : null;
        }

        $cleaned = preg_replace('/\s+/', '', (string) $value);
        $cleaned = str_replace(',', '', $cleaned);
        $cleaned = preg_replace('/[^0-9.\-]/', '', $cleaned);

        if ($cleaned === '' || ! is_numeric($cleaned)) {
            return null;
        }

        $number = (float) $cleaned;

        return $number > 0 ? $number : null;
    }

    /** Clé de colonne normalisée (firstName → firstname, donationCategoryName → donationcategoryname). */
    public function normalizeHeader(mixed $header): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $header) ?? '');
    }
}
