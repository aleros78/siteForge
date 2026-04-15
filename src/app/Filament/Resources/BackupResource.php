<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BackupResource\Pages;
use App\Jobs\BackupProjectJob;
use App\Models\Backup;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BackupResource extends Resource
{
    protected static ?string $model = Backup::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?int $navigationSort = 80;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('project.name')
                    ->label('Progetto')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tipo')
                    ->colors([
                        'primary' => 'database',
                        'info'    => 'files',
                        'warning' => 'full',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Stato')
                    ->colors([
                        'success' => 'completed',
                        'danger'  => 'failed',
                        'info'    => 'running',
                        'gray'    => 'pending',
                    ]),

                Tables\Columns\TextColumn::make('file_size_human')
                    ->label('Dimensione'),

                Tables\Columns\TextColumn::make('disk')
                    ->label('Disk'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Scadenza')
                    ->date()
                    ->placeholder('Mai'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'database' => 'Database',
                        'files'    => 'Files',
                        'full'     => 'Full',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completed' => 'Completato',
                        'failed'    => 'Fallito',
                        'running'   => 'In Corso',
                        'pending'   => 'In Attesa',
                    ]),

                Tables\Filters\SelectFilter::make('project_id')
                    ->label('Progetto')
                    ->relationship('project', 'name'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('backup_all')
                    ->label('Backup Tutti i Progetti')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->requiresConfirmation()
                    ->action(function () {
                        $projects = \App\Models\Project::where('status', 'running')->get();
                        $count = 0;
                        foreach ($projects as $project) {
                            BackupProjectJob::dispatch($project);
                            $count++;
                        }
                        Notification::make()
                            ->title("{$count} backup avviati")
                            ->info()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        if ($record->file_path && file_exists($record->file_path)) {
                            unlink($record->file_path);
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBackups::route('/'),
        ];
    }
}
