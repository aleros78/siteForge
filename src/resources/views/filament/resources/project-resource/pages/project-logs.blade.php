<x-filament-panels::page>
    <div class="space-y-4">
        {{-- Filtri log --}}
        <div class="flex gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Servizio
                </label>
                <input
                    type="text"
                    wire:model="service"
                    placeholder="Tutti i servizi"
                    class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
                />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Righe
                </label>
                <select
                    wire:model="lines"
                    class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
                >
                    <option value="50">50</option>
                    <option value="100" selected>100</option>
                    <option value="200">200</option>
                    <option value="500">500</option>
                </select>
            </div>
            <button
                wire:click="fetchLogs"
                wire:loading.attr="disabled"
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
            >
                <span wire:loading.remove>Carica Log</span>
                <span wire:loading>Caricamento...</span>
            </button>
        </div>

        {{-- Output log --}}
        <div class="bg-gray-900 rounded-xl p-4 min-h-64 max-h-screen overflow-auto">
            @if($isLoading)
                <p class="text-gray-400 text-sm">Caricamento log in corso...</p>
            @elseif($logs)
                <pre class="text-green-400 text-xs font-mono whitespace-pre-wrap">{{ $logs }}</pre>
            @else
                <p class="text-gray-500 text-sm">
                    Clicca "Carica Log" per visualizzare i log del progetto <strong class="text-white">{{ $record->name }}</strong>.
                </p>
            @endif
        </div>

        {{-- Info progetto --}}
        <div class="text-xs text-gray-500">
            Progetto: <span class="font-mono">{{ $record->getComposeProjectName() }}</span>
            &bull;
            Path: <span class="font-mono">{{ $record->base_path }}</span>
        </div>
    </div>
</x-filament-panels::page>
