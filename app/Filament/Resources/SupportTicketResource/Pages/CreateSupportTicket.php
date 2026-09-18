<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Services\SupportTicketAlertService;
use Filament\Resources\Pages\CreateRecord;

class CreateSupportTicket extends CreateRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['submitted_by_id'] = auth()->id();
        $data['status'] = 'open';

        return $data;
    }

    protected function afterCreate(): void
    {
        app(SupportTicketAlertService::class)->notifyIt($this->record->fresh(['submittedBy']));
    }
}
