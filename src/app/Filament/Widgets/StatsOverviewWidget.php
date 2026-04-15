<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use App\Models\Server;
use App\Models\Backup;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $runningProjects = Project::where('status', 'running')->count();
        $totalProjects   = Project::count();
        $errorProjects   = Project::where('status', 'error')->count();
        $onlineServers   = Server::where('status', 'online')->count();
        $totalServers    = Server::count();
        $recentBackups   = Backup::where('status', 'completed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return [
            Stat::make('Progetti Attivi', "{$runningProjects} / {$totalProjects}")
                ->description($errorProjects > 0 ? "{$errorProjects} in errore" : 'Tutti OK')
                ->descriptionIcon($errorProjects > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($errorProjects > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-cube'),

            Stat::make('Server Online', "{$onlineServers} / {$totalServers}")
                ->description('Server disponibili')
                ->descriptionIcon('heroicon-m-server')
                ->color($onlineServers === $totalServers ? 'success' : 'warning')
                ->icon('heroicon-o-server'),

            Stat::make('Backup (24h)', $recentBackups)
                ->description('Backup completati oggi')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('info')
                ->icon('heroicon-o-archive-box'),

            Stat::make('Progetti in Errore', $errorProjects)
                ->description($errorProjects > 0 ? 'Richiedono attenzione' : 'Nessun errore')
                ->descriptionIcon($errorProjects > 0 ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                ->color($errorProjects > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-circle'),
        ];
    }
}
