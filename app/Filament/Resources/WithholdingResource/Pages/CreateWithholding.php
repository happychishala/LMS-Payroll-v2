<?php

namespace App\Filament\Resources\WithholdingResource\Pages;

use App\Filament\Resources\WithholdingResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateWithholding extends CreateRecord
{
    protected static string $resource = WithholdingResource::class;
}
