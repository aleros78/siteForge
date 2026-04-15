<div class="space-y-3">
    @if($log->output)
        <div>
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Output</h4>
            <pre class="bg-gray-900 text-green-400 rounded-lg p-3 text-xs font-mono overflow-auto max-h-64 whitespace-pre-wrap">{{ $log->output }}</pre>
        </div>
    @endif

    @if($log->error_output)
        <div>
            <h4 class="text-sm font-medium text-red-600 mb-1">Errori</h4>
            <pre class="bg-gray-900 text-red-400 rounded-lg p-3 text-xs font-mono overflow-auto max-h-64 whitespace-pre-wrap">{{ $log->error_output }}</pre>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-2 text-xs text-gray-500">
        <div>Exit Code: <span class="font-mono">{{ $log->exit_code ?? 'N/D' }}</span></div>
        <div>Durata: <span class="font-mono">{{ $log->duration ?? 'N/D' }}s</span></div>
        <div>Avviato da: <span class="font-mono">{{ $log->triggered_by ?? 'system' }}</span></div>
        <div>Data: <span class="font-mono">{{ $log->created_at->format('d/m/Y H:i:s') }}</span></div>
    </div>
</div>
