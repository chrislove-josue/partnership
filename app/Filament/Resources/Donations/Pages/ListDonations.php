<?php

namespace App\Filament\Resources\Donations\Pages;

use App\Filament\Resources\Donations\DonationResource;
use App\Services\Import\DonationImportService;
use App\Services\Import\ImportTemplateGenerator;
use App\Services\Import\SpreadsheetReader;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ListDonations extends ListRecords
{
    protected static string $resource = DonationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('importDonations')
                ->label('Importer depuis Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->schema([
                    FileUpload::make('file')
                        ->label('Fichier Excel (.xlsx)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->storeFiles(false)
                        ->required()
                        ->helperText('Colonnes : churchName (ou church), donationAmount, donationDate, donationCategoryName, puis email/phone/handleKC d’un partenaire existant ou firstName + lastName pour en créer un.'),
                ])
                ->modalHeading('Importer des dons')
                ->modalSubmitActionLabel('Importer')
                ->action(function (array $data): void {
                    try {
                        $rows = app(SpreadsheetReader::class)->read($data['file']);

                        if ($rows === []) {
                            Notification::make()
                                ->title('Aucune ligne à importer')
                                ->body('Le fichier ne contient pas de données.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $result = app(DonationImportService::class)->import(Auth::user(), $rows);

                        $body = $result['success'].' don(s) importé(s), '.$result['skipped'].' déjà présent(s), '.$result['errors'].' erreur(s) sur '.$result['total'].' ligne(s).';

                        if ($result['errorDetails'] !== []) {
                            $body .= "\n".implode("\n", array_slice($result['errorDetails'], 0, 5));
                        }

                        $notification = Notification::make()
                            ->title('Import des dons terminé')
                            ->body($body);

                        $result['errors'] > 0 ? $notification->warning()->send() : $notification->success()->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('Échec de l’import des dons')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('templateDonations')
                ->label('Télécharger le modèle')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => response()->streamDownload(
                    fn () => print app(ImportTemplateGenerator::class)->toXlsx(true),
                    'modele_dons.xlsx',
                    ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                )),
        ];
    }
}
