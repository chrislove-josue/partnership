<?php

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Resources\Partners\PartnerResource;
use App\Services\Import\ImportTemplateGenerator;
use App\Services\Import\PartnerImportService;
use App\Services\Import\SpreadsheetReader;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ListPartners extends ListRecords
{
    protected static string $resource = PartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('importPartners')
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
                        ->helperText('Colonnes : firstName, lastName, email, phone, handleKC, churchName, celluleName, address, birthdate, donationAmount, donationDate, donationCategoryName.'),
                ])
                ->modalHeading('Importer des partenaires')
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

                        $result = app(PartnerImportService::class)->import(Auth::user(), $rows);

                        $body = $result['success'].' partenaire(s) importé(s), '.$result['errors'].' erreur(s) sur '.$result['total'].' ligne(s).';

                        if ($result['errorDetails'] !== []) {
                            $body .= "\n".implode("\n", array_slice($result['errorDetails'], 0, 5));
                        }

                        $notification = Notification::make()
                            ->title('Import des partenaires terminé')
                            ->body($body);

                        $result['errors'] > 0 ? $notification->warning()->send() : $notification->success()->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('Échec de l’import des partenaires')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('templatePartners')
                ->label('Télécharger le modèle')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => response()->streamDownload(
                    fn () => print app(ImportTemplateGenerator::class)->toXlsx(false),
                    'modele_partenaires.xlsx',
                    ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                )),
        ];
    }
}
