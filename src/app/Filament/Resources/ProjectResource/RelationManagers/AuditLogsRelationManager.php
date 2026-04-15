<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'auditLogs';
    protected static ?string $title = 'Storico Operazioni';
    protected static ?string $icon = 'heroicon-o-clipboard-document-list';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('action')
                    ->label('Azione')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'provision' => 'primary',
                        'start'     => 'success',
                        'stop'      => 'warning',
                        'restart'   => 'info',
                        'backup'    => 'gray',
                        'redeploy'  => 'primary',
                        default     => 'gray',
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Stato')
                    ->colors([
                        'success' => 'success',
                        'danger'  => 'failed',
                        'info'    => 'running',
                        'gray'    => 'pending',
                    ]),

                Tables\Columns\TextColumn::make('triggered_by')
                    ->label('Avviato da'),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Iniziato')
                    ->since(),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Durata')
                    ->suffix('s')
                    ->placeholder('N/D'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('output')
                    ->label('Output')
                    ->icon('heroicon-o-document-text')
                    ->modalContent(fn ($record) => view('filament.components.audit-output', ['log' => $record]))
                    ->modalHeading(fn ($record) => "Output: {$record->action}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Chiudi'),
            ])
            ->paginated([10, 25, 50]);
    }
}
