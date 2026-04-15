<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Jobs\BackupProjectJob;
use App\Jobs\RestartProjectJob;
use App\Jobs\StartProjectJob;
use App\Jobs\StopProjectJob;
use App\Jobs\RedeployProjectJob;
use App\Services\HealthCheckService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open')
                ->label('Apri')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => "http://{$this->record->primary_domain}", shouldOpenInNewTab: true)
                ->visible(fn () => $this->record->isRunning()),

            Actions\Action::make('start')
                ->label('Avvia')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->record->isStopped() || $this->record->hasError())
                ->requiresConfirmation()
                ->action(function () {
                    StartProjectJob::dispatch($this->record);
                    Notification::make()->title('Avvio in corso...')->info()->send();
                }),

            Actions\Action::make('stop')
                ->label('Ferma')
                ->icon('heroicon-o-stop')
                ->color('warning')
                ->visible(fn () => $this->record->isRunning())
                ->requiresConfirmation()
                ->action(function () {
                    StopProjectJob::dispatch($this->record);
                    Notification::make()->title('Arresto in corso...')->warning()->send();
                }),

            Actions\Action::make('restart')
                ->label('Riavvia')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->visible(fn () => $this->record->isRunning())
                ->requiresConfirmation()
                ->action(function () {
                    RestartProjectJob::dispatch($this->record);
                    Notification::make()->title('Riavvio in corso...')->info()->send();
                }),

            Actions\Action::make('redeploy')
                ->label('Redeploy')
                ->icon('heroicon-o-rocket-launch')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Conferma Redeploy')
                ->modalDescription('Verrà eseguito un rebuild completo dei container. Il servizio sarà temporaneamente non disponibile.')
                ->action(function () {
                    RedeployProjectJob::dispatch($this->record);
                    Notification::make()->title('Redeploy avviato...')->warning()->send();
                }),

            Actions\Action::make('health_check')
                ->label('Health Check')
                ->icon('heroicon-o-heart')
                ->color('gray')
                ->action(function () {
                    $service = app(HealthCheckService::class);
                    $check = $service->check($this->record);

                    $color = match ($check->status) {
                        'ok'      => 'success',
                        'warning' => 'warning',
                        default   => 'danger',
                    };

                    Notification::make()
                        ->title("Health: {$check->status}")
                        ->body($check->error_message ?? 'Tutto OK')
                        ->{$color}()
                        ->send();
                }),

            Actions\Action::make('backup')
                ->label('Backup DB')
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->visible(fn () => $this->record->isRunning())
                ->requiresConfirmation()
                ->action(function () {
                    BackupProjectJob::dispatch($this->record);
                    Notification::make()->title('Backup avviato...')->info()->send();
                }),

            Actions\Action::make('logs')
                ->label('Log')
                ->icon('heroicon-o-document-text')
                ->url(fn () => ProjectResource::getUrl('logs', ['record' => $this->record])),

            Actions\EditAction::make(),
        ];
    }
}
