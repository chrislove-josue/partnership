<?php

namespace App\Services\Import;

use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use Throwable;

/**
 * Port de POST /api/partners/import.
 * Colonnes : firstName, lastName, email, phone, handleKC, churchName,
 * celluleName, address, birthdate, donationAmount, donationDate,
 * donationCategoryName.
 */
class PartnerImportService extends BaseImportService
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{success: int, errors: int, total: int, errorDetails: array<int, string>}
     */
    public function import(User $actor, array $rows): array
    {
        $results = [
            'success' => 0,
            'errors' => 0,
            'total' => count($rows),
            'errorDetails' => [],
        ];

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            try {
                $firstName = $this->text($row, 'firstname');
                $lastName = $this->text($row, 'lastname');
                $churchName = $this->text($row, 'churchname');

                if (! $firstName || ! $lastName || ! $churchName) {
                    throw new RuntimeException('Champs obligatoires manquants (firstName, lastName, churchName)');
                }

                $church = $this->resolveChurch($churchName);
                $this->assertCanModify($actor, $church);

                $celluleId = null;
                $celluleName = $this->text($row, 'cellulename');
                if ($celluleName) {
                    $cellule = $church->cellules()
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($celluleName)])
                        ->first();

                    $cellule ??= $church->cellules()->create(['name' => $celluleName]);
                    $celluleId = $cellule->id;
                }

                $category = null;
                $categoryName = $this->text($row, 'donationcategoryname');
                if ($categoryName) {
                    $category = $this->resolveCategory($categoryName);
                }

                $birthdate = $this->helpers->parseDateFlexible($row['birthdate'] ?? null);
                if (! empty($row['birthdate']) && ! $birthdate) {
                    throw new RuntimeException('Date de naissance invalide : '.$row['birthdate']);
                }

                $phone = $this->helpers->normalizePhone($row['phone'] ?? null);
                $handleKC = $this->helpers->normalizeHandleKC($this->text($row, 'handlekc'));

                $partner = $this->findPartner($row, $phone, $handleKC);
                $data = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $this->text($row, 'email'),
                    'phone' => $phone,
                    'address' => $this->text($row, 'address'),
                    'handle_kc' => $handleKC,
                    'church_id' => $church->id,
                    'cellule_id' => $celluleId,
                    'birthdate' => $birthdate?->toDateString(),
                ];

                if ($partner) {
                    $partner->update($data);
                } else {
                    $partner = Partner::create($data);
                }

                if (! empty($row['donationamount']) && ! empty($row['donationdate']) && $category) {
                    $amount = $this->helpers->parseAmount($row['donationamount']);
                    if ($amount === null) {
                        throw new RuntimeException('Montant invalide : '.$row['donationamount']);
                    }

                    $donationDate = $this->helpers->parseDateFlexible($row['donationdate']);
                    if (! $donationDate) {
                        throw new RuntimeException('Date de don invalide : '.$row['donationdate']);
                    }

                    Donation::create([
                        'amount' => $amount,
                        'date' => $donationDate,
                        'partner_id' => $partner->id,
                        'church_id' => $church->id,
                        'category_id' => $category->id,
                    ]);
                }

                $results['success']++;
            } catch (Throwable $exception) {
                $results['errors']++;
                $results['errorDetails'][] = 'Ligne '.$line.': '.$exception->getMessage();
            }
        }

        return $results;
    }
}
