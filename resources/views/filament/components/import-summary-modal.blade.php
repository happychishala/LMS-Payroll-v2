<x-filament::page>
    <div
        x-data="{ open: false, imported: 0, skipped: 0, invalid: 0 }"
        x-cloak
        x-show="open"
        x-on:open-import-summary-modal.window="({imported, skipped, invalid}) => (open = true, importedVal = imported, skippedVal = skipped, invalidVal = invalid, imported = imported, skipped = skipped, invalid = invalid)"
        class="fixed inset-0 z-50 flex items-center justify-center p-6"
    >
        <div class="fixed inset-0 bg-black/50" @click="open = false"></div>

        <div class="relative bg-white rounded-lg shadow-lg w-full max-w-xl p-6 z-10">
            <div class="flex justify-between items-start">
                <h3 class="text-lg font-semibold">Import summary</h3>
                <button @click="open = false" class="text-gray-600">×</button>
            </div>

            <div class="mt-4">
                <p class="text-sm">Imported: <span x-text="imported"></span></p>
                <p class="text-sm">Skipped: <span x-text="skipped"></span></p>
                <p class="text-sm">Invalid rows: <span x-text="invalid"></span></p>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <button @click="open = false" class="px-4 py-2 bg-gray-200 rounded">Close</button>

                <button
                    x-show="invalid > 0"
                    @click="() => { Livewire.emit('openInvalidRowsModal'); }"
                    class="px-4 py-2 bg-primary-600 text-white rounded"
                >
                    Review invalids
                </button>
            </div>
        </div>
    </div>

    <script>
        Livewire.on('openImportSummaryModal', payload => {
            const event = new CustomEvent('open-import-summary-modal', { detail: payload });
            // dispatch a window event Alpine picks up — Alpine handler uses direct window event name
            window.dispatchEvent(new CustomEvent('open-import-summary-modal', { detail: payload }));
            // also set simple window variables and directly open via dispatch for the inline listener
            window.dispatchEvent(new Event('open-import-summary-modal'));
            // For compatibility: call Livewire to let modal open logic handle show
            // (the Review button below calls Livewire.emit('openInvalidRowsModal'))
        });

        // expose a simple global open to allow Alpine to pick up detail
        window.addEventListener('open-import-summary-modal', (e) => {
            // store payload in a global var Livewire can access/demo only
            const payload = e.detail || {};
            // re-dispatch as a custom window event with object detail so Alpine x-on can receive it
            window.dispatchEvent(new CustomEvent('open-import-summary-modal', { detail: payload }));
        });
    </script>
</x-filament::page>