<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Jobs\ProvisionProjectJob;
use App\Models\Server;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Calcola base_path dal server selezionato
        $server = Server::findOrFail($data['server_id']);
        $data['base_path'] = $server->getProjectPath($data['slug']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Dispatch provisioning job dopo la creazione del record
        ProvisionProjectJob::dispatch($this->record);

        Notification::make()
            ->title('Provisioning avviato per: ' . $this->record->name)
            ->body('Il progetto sarà disponibile a breve. Monitora lo stato nella lista.')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
