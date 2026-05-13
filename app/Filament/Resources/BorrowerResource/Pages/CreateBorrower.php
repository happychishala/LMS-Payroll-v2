<?php

namespace App\Filament\Resources\BorrowerResource\Pages;

use App\Filament\Resources\BorrowerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBorrower extends CreateRecord
{
    protected static string $resource = BorrowerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
       return BorrowerResource::mutateBorrowerData($data);
    }

    protected function afterCreate(): void
    {
        BorrowerResource::syncAttachmentReferences($this->record);
    }

                                    
}
