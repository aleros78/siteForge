<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Services\DockerService;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Livewire\Attributes\Computed;

class ProjectLogs extends Page
{
    protected static string $resource = ProjectResource::class;
    protected static string $view = 'filament.resources.project-resource.pages.project-logs';

    public $record;
    public string $service = '';
    public int $lines = 100;
    public string $logs = '';
    public bool $isLoading = false;

    public function mount(int|string $record): void
    {
        $this->record = \App\Models\Project::findOrFail($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Torna al Progetto')
                ->url(ProjectResource::getUrl('view', ['record' => $this->record]))
                ->color('gray')
                ->icon('heroicon-o-arrow-left'),

            Action::make('refresh')
                ->label('Aggiorna')
                ->icon('heroicon-o-arrow-path')
                ->action('fetchLogs'),
        ];
    }

    public function fetchLogs(): void
    {
        $this->isLoading = true;
        $docker = app(DockerService::class);

        $result = $docker->logs(
            $this->record,
            $this->lines,
            $this->service ?: null
        );

        $this->logs = $result['success']
            ? ($result['output'] ?: 'Nessun log disponibile.')
            : 'Errore nel recupero dei log: ' . $result['error'];

        $this->isLoading = false;
    }

    public function getTitle(): string
    {
        return "Log: {$this->record->name}";
    }
}
