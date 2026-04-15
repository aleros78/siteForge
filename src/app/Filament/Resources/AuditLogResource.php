<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Form;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?int $navigationSort = 90;
    protected static ?string $label = 'Audit Log';
    protected static ?string $pluralLabel = 'Audit Log';

    public static function canCreate(): bool
    {
        return false; // Log solo in lettura
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]); // Non usato
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('project.name')
                    ->label('Progetto')
                    ->placeholder('Sistema')
                    ->searchable(),

                Tables\Columns\TextColumn::make('action')
                    ->label('Azione')
                    ->badge()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Stato')
                    ->colors([
                        'success' => 'success',
                        'danger'  => 'failed',
                        'info'    => 'running',
                        'gray'    => 'pending',
                    ]),

                Tables\Columns\TextColumn::make('triggered_by')
                    ->label('Utente')
                    ->placeholder('system'),

                Tables\Columns\TextColumn::make('exit_code')
                    ->label('Exit')
                    ->badge()
                    ->color(fn ($state) => $state === 0 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Data')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Durata')
                    ->suffix('s')
                    ->placeholder('N/D'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        'provision' => 'Provision',
                        'start'     => 'Start',
                        'stop'      => 'Stop',
                        'restart'   => 'Restart',
                        'backup'    => 'Backup',
                        'redeploy'  => 'Redeploy',
                        'clone'     => 'Clone',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'success' => 'Success',
                        'failed'  => 'Failed',
                        'running' => 'Running',
                        'pending' => 'Pending',
                    ]),

                Tables\Filters\SelectFilter::make('project_id')
                    ->label('Progetto')
                    ->relationship('project', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('output')
                    ->label('Output')
                    ->icon('heroicon-o-eye')
                    ->modalContent(fn ($record) => view('filament.components.audit-output', ['log' => $record]))
                    ->modalHeading(fn ($record) => "Output: {$record->action}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Chiudi'),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('15s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
