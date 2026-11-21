<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\Partner;
use App\Services\Import\DonationImportService;
use App\Services\Import\ImportTemplateGenerator;
use App\Services\Import\PartnerImportService;
use App\Services\Import\SpreadsheetReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ImportController extends Controller
{
    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly DonationImportService $donationImporter,
        private readonly PartnerImportService $partnerImporter,
        private readonly ImportTemplateGenerator $templates,
    ) {}

    public function importDonations(Request $request): JsonResponse
    {
        return $this->import($request, $this->donationImporter, 'Import des dons terminé');
    }

    public function importPartners(Request $request): JsonResponse
    {
        return $this->import($request, $this->partnerImporter, 'Import des partenaires terminé');
    }

    public function donationTemplate(): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print $this->templates->toXlsx(true),
            'modele_dons.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function partnerTemplate(): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print $this->templates->toXlsx(false),
            'modele_partenaires.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    private function import(
        Request $request,
        DonationImportService|PartnerImportService $service,
        string $title,
    ): JsonResponse {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ], [
            'file.required' => 'Le fichier Excel est obligatoire.',
            'file.file' => 'Le fichier téléversé est invalide.',
            'file.mimes' => 'Le fichier doit être au format Excel (.xlsx).',
            'file.max' => 'Le fichier ne doit pas dépasser 10 Mo.',
        ]);

        try {
            $rows = $this->reader->read($data['file']);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Lecture du fichier impossible : '.$exception->getMessage(),
            ], 422);
        }

        if ($rows === []) {
            return response()->json([
                'message' => 'Aucune ligne à importer. Le fichier ne contient pas de données.',
                'imported' => 0,
                'skipped' => 0,
                'error_count' => 0,
                'errors' => [],
                'total' => 0,
            ], 422);
        }

        $result = $service->import($request->user(), $rows);
        $skipped = $result['skipped'] ?? 0;

        $message = $title.' : '.$result['success'].' importé(s)';
        if ($skipped > 0) {
            $message .= ', '.$skipped.' déjà présent(s)';
        }
        $message .= ', '.$result['errors'].' erreur(s) sur '.$result['total'].' ligne(s).';

        return response()->json([
            'message' => $message,
            'imported' => $result['success'],
            'skipped' => $skipped,
            'error_count' => $result['errors'],
            'errors' => array_slice($result['errorDetails'], 0, 5),
            'total' => $result['total'],
        ]);
    }
}
