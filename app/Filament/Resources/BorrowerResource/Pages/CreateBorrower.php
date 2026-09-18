<?php

namespace App\Filament\Resources\BorrowerResource\Pages;

use App\Filament\Resources\BorrowerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBorrower extends CreateRecord
{
    protected static string $resource = BorrowerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = BorrowerResource::mutateBorrowerData($data);
        $data['verification_status'] = 'pending';

        return $data;
    }

    protected function afterCreate(): void
    {
        BorrowerResource::syncAttachmentReferences($this->record);
    }

                                    
}
