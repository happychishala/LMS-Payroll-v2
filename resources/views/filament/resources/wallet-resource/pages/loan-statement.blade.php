<x-filament::page>
    {{-- The form --}}
    {{ $this->form }}

    {{-- The table (will automatically re-query after generateStatement()) --}}
    {{ $this->table }}
</x-filament::page>
