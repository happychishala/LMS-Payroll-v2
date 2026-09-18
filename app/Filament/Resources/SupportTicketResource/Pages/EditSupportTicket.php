<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSupportTicket extends EditRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn (): bool => SupportTicketResource::canManageTickets()),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (isset($data['status']) && in_array($data['status'], ['resolved', 'closed'], true)) {
            $data['closed_at'] = now();
        }

        if (isset($data['status']) && ! in_array($data['status'], ['resolved', 'closed'], true)) {
            $data['closed_at'] = null;
        }

        $record->update($data);

        return $record;
    }
}
