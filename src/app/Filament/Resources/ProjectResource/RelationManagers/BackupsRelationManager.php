<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Jobs\BackupProjectJob;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BackupsRelationManager extends RelationManager
{
    protected static string $relationship = 'backups';
    protected static ?string $title = 'Backup';
    protected static ?string $icon = 'heroicon-o-archive-box';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
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

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->since(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Scade')
                    ->since()
                    ->placeholder('Mai'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('new_backup')
                    ->label('Nuovo Backup')
                    ->icon('heroicon-o-plus')
                    ->action(function () {
                        BackupProjectJob::dispatch($this->getOwnerRecord());
                        Notification::make()->title('Backup avviato...')->info()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        // Elimina il file fisico
                        if ($record->file_path && file_exists($record->file_path)) {
                            unlink($record->file_path);
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
