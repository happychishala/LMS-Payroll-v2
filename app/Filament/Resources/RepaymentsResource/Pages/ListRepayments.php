<?php

namespace App\Filament\Resources\RepaymentsResource\Pages;

use App\Filament\Resources\RepaymentsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListRepayments extends ListRecords
{
    protected static string $resource = RepaymentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    // pass invalid rows from session into the page view
    public function render(): View
    {
        return parent::render()->with([
            'invalidRows' => session('invalid_rows', []),
        ]);
    }
}
