<?php

namespace App\Services\Import;

use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use RuntimeException;
use Throwable;

/**
 * Port de POST /api/donations/import.
 * Colonnes : churchName (ou church), donationAmount, donationDate,
 * donationCategoryName + email/phone/handleKC d'un partenaire existant,
 * ou firstName + lastName pour en créer un.
 * Un don identique (partenaire + église + catégorie + montant + date) est ignoré.
 */
class DonationImportService extends BaseImportService
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{success: int, errors: int, total: int, skipped: int, errorDetails: array<int, string>}
     */
    public function import(User $actor, array $rows): array
    {
        $results = [
            'success' => 0,
            'errors' => 0,
            'total' => count($rows),
            'skipped' => 0,
            'errorDetails' => [],
        ];

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            try {
                $churchName = $this->text($row, 'churchname') ?? $this->text($row, 'church');
                if (! $churchName) {
                    throw new RuntimeException('Champ obligatoire manquant (churchName)');
                }

                $church = $this->resolveChurch($churchName);
                $this->assertCanModify($actor, $church);

                $amount = $this->helpers->parseAmount($row['donationamount'] ?? null);
                if ($amount === null) {
                    throw new RuntimeException('Montant invalide ou manquant : '.($row['donationamount'] ?? ''));
                }

                $donationDate = $this->helpers->parseDateFlexible($row['donationdate'] ?? null);
                if (! $donationDate) {
                    throw new RuntimeException('Date de don invalide ou manquante : '.($row['donationdate'] ?? ''));
                }

                $categoryName = $this->text($row, 'donationcategoryname');
                if (! $categoryName) {
                    throw new RuntimeException('Catégorie du don manquante (donationCategoryName)');
                }
                $category = $this->resolveCategory($categoryName);

                $phone = $this->helpers->normalizePhone($row['phone'] ?? null);
                $handleKC = $this->helpers->normalizeHandleKC($this->text($row, 'handlekc'));
                $partner = $this->findPartner($row, $phone, $handleKC);

                if (! $partner) {
                    $firstName = $this->text($row, 'firstname');
                    $lastName = $this->text($row, 'lastname');

                    if (! $firstName || ! $lastName) {
                        throw new RuntimeException("Partenaire non trouvé. Renseignez firstName + lastName pour en créer un, ou email/phone/handleKC d'un partenaire existant.");
                    }

                    $partner = Partner::create([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $this->text($row, 'email'),
                        'phone' => $phone,
                        'handle_kc' => $handleKC,
                        'church_id' => $church->id,
                    ]);
                }

                $exists = Donation::query()
                    ->where('partner_id', $partner->id)
                    ->where('church_id', $church->id)
                    ->where('category_id', $category->id)
                    ->where('amount', $amount)
                    ->where('date', $donationDate)
                    ->exists();

                if ($exists) {
                    $results['skipped']++;
                    continue;
                }

                Donation::create([
                    'amount' => $amount,
                    'date' => $donationDate,
                    'partner_id' => $partner->id,
                    'church_id' => $church->id,
                    'category_id' => $category->id,
                ]);

                $results['success']++;
            } catch (Throwable $exception) {
                $results['errors']++;
                $results['errorDetails'][] = 'Ligne '.$line.': '.$exception->getMessage();
            }
        }

        return $results;
    }
}
