<x-filament::page>
    @include('filament.components.import-summary-modal')
    @include('filament.components.invalid-rows-modal')

    {{ $this->table }}
</x-filament::page>