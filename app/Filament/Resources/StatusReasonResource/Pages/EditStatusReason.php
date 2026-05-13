<?php

namespace App\Filament\Resources\StatusReasonResource\Pages;

use App\Filament\Resources\StatusReasonResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStatusReason extends EditRecord
{
    protected static string $resource = StatusReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
