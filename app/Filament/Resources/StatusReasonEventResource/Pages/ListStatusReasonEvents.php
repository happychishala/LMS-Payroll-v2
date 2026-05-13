<?php

namespace App\Filament\Resources\StatusReasonEventResource\Pages;

use App\Filament\Resources\StatusReasonEventResource;
use App\Services\StatusReasonAuthorization;
use App\Services\StatusReasonBatchImportService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListStatusReasonEvents extends ListRecords
{
    protected static string $resource = StatusReasonEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadStatusReasonTemplate')
                ->label('Download Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => StatusReasonAuthorization::canImport(auth()->user()))
                ->action(fn () => response()->streamDownload(function (): void {
                    $handle = fopen('php://output', 'w');

                    fputcsv($handle, [
                        'loan_id',
                        'client_id',
                        'nrc',
                        'action',
                        'status_reason_code',
                        'effective_date',
                        'mode_of_exit',
                        'affordability_drop_reason',
                        'notes',
                        'removal_reason',
                        'management_approval_confirmed',
                    ]);

                    fputcsv($handle, [
                        'L0001',
                        'CUST001',
                        '111111/11/1',
                        'ASSIGN',
                        'A001',
                        now()->toDateString(),
                        '',
                        '',
                        'Sample assign row',
                        '',
                        'no',
                    ]);

                    fputcsv($handle, [
                        'L0002',
                        'CUST002',
                        '222222/22/2',
                        'REMOVE',
                        '',
                        now()->toDateString(),
                        '',
                        '',
                        'Sample remove row',
                        'Reason for removal',
                        'no',
                    ]);

                    fclose($handle);
                }, 'status-reason-audit-template.csv', [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                ])),
            Actions\Action::make('importStatusReasonCsv')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->visible(fn (): bool => StatusReasonAuthorization::canImport(auth()->user()))
                ->form([
                    FileUpload::make('csv_file')
                        ->label('Status Reason CSV')
                        ->disk('local')
                        ->directory('imports/status-reasons')
                        ->acceptedFileTypes(['.csv', 'text/csv', 'application/vnd.ms-excel', 'text/plain'])
                        ->required(),
                ])
                ->action(function (array $data, StatusReasonBatchImportService $service): void {
                    $fileKey = $data['csv_file'] ?? null;

                    if (is_array($fileKey)) {
                        $fileKey = reset($fileKey);
                    }

                    $path = $fileKey ? Storage::disk('local')->path($fileKey) : null;

                    if (! $path || ! file_exists($path)) {
                        Notification::make()->danger()->title('CSV file not found')->send();
                        return;
                    }

                    $result = $service->import($path, auth()->user());

                    $body = "Processed: {$result['processed']} | Assigned/Changed: {$result['assigned']} | Removed: {$result['removed']} | Skipped: {$result['skipped']}";

                    if ($result['errors'] !== []) {
                        $body .= "\n" . implode("\n", array_slice($result['errors'], 0, 8));
                    }

                    Notification::make()
                        ->title('Status reason import complete')
                        ->body($body)
                        ->success()
                        ->send();
                }),
        ];
    }
}
