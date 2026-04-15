<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Jobs\ProvisionProjectJob;
use App\Models\Project;
use App\Models\Server;
use App\Services\LicenseService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuovo Progetto')
                ->before(function () {
                    $license = app(LicenseService::class);
                    if (!$license->canCreateProject()) {
                        Notification::make()
                            ->title('Limite progetti raggiunto')
                            ->danger()
                            ->send();
                        $this->halt();
                    }
                }),
        ];
    }
}
