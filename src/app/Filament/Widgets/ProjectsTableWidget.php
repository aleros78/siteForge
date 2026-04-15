<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ProjectsTableWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Project::query()
                    ->with(['server', 'latestHealthCheck'])
                    ->latest()
                    ->limit(10)
            )
            ->heading('Progetti Recenti')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Progetto')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('primary_domain')
                    ->label('Dominio')
                    ->url(fn ($record) => "http://{$record->primary_domain}", true)
                    ->color('primary'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Stato')
                    ->colors([
                        'success' => 'running',
                        'warning' => 'stopped',
                        'danger'  => 'error',
                        'info'    => fn ($state) => in_array($state, ['provisioning', 'rebuilding']),
                        'gray'    => 'pending',
                    ]),

                Tables\Columns\TextColumn::make('environment')
                    ->label('Ambiente')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'production' => 'danger',
                        'staging'    => 'warning',
                        default      => 'gray',
                    }),

                Tables\Columns\TextColumn::make('server.name')
                    ->label('Server'),

                Tables\Columns\TextColumn::make('last_deployed_at')
                    ->label('Ultimo Deploy')
                    ->since()
                    ->placeholder('Mai'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Apri')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record) => route('filament.admin.resources.projects.view', $record)),
            ])
            ->paginated(false);
    }
}
