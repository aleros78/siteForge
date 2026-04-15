<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Jobs\BackupProjectJob;
use App\Jobs\RestartProjectJob;
use App\Jobs\StartProjectJob;
use App\Jobs\StopProjectJob;
use App\Models\Project;
use App\Models\Server;
use App\Models\Template;
use App\Services\LicenseService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Progetti';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informazioni Progetto')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome Progetto')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Forms\Set $set, $record) {
                            if (!$record) {
                                $set('slug', Str::slug($state));
                            }
                        }),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(63)
                        ->helperText('Usato come nome cartella e nei container Docker'),

                    Forms\Components\Textarea::make('description')
                        ->label('Descrizione')
                        ->rows(2)
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Configurazione')
                ->schema([
                    Forms\Components\Select::make('server_id')
                        ->label('Server')
                        ->relationship('server', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if ($state) {
                                $server = Server::find($state);
                                $set('base_path', null); // reset, sarà calcolato al salvataggio
                            }
                        }),

                    Forms\Components\Select::make('template_id')
                        ->label('Template')
                        ->relationship('template', 'name', fn ($query) => $query->where('is_active', true))
                        ->required()
                        ->searchable()
                        ->preload(),

                    Forms\Components\TextInput::make('primary_domain')
                        ->label('Dominio Principale')
                        ->required()
                        ->placeholder('myapp.example.com')
                        ->helperText('Senza http:// - es: myapp.example.com'),

                    Forms\Components\Select::make('environment')
                        ->label('Ambiente')
                        ->options([
                            'production'  => 'Production',
                            'staging'     => 'Staging',
                            'development' => 'Development',
                        ])
                        ->required()
                        ->default('production'),
                ])->columns(2),

            Forms\Components\Section::make('Variabili di Ambiente')
                ->description('Variabili custom che sovrascrivono i placeholder del template.')
                ->schema([
                    Forms\Components\KeyValue::make('env_vars')
                        ->label('ENV Variables')
                        ->keyLabel('Chiave')
                        ->valueLabel('Valore')
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Progetto')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->primary_domain),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Stato')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'running'      => 'In Esecuzione',
                        'stopped'      => 'Fermato',
                        'error'        => 'Errore',
                        'provisioning' => 'Provisioning',
                        'rebuilding'   => 'Rebuild',
                        'pending'      => 'In Attesa',
                        'cloning'      => 'Clonazione',
                        default        => $state,
                    })
                    ->colors([
                        'success' => 'running',
                        'warning' => 'stopped',
                        'danger'  => 'error',
                        'info'    => fn ($state) => in_array($state, ['provisioning', 'rebuilding', 'cloning']),
                        'gray'    => 'pending',
                    ]),

                Tables\Columns\BadgeColumn::make('environment')
                    ->label('Ambiente')
                    ->colors([
                        'danger'  => 'production',
                        'warning' => 'staging',
                        'success' => 'development',
                    ]),

                Tables\Columns\TextColumn::make('server.name')
                    ->label('Server'),

                Tables\Columns\TextColumn::make('template.name')
                    ->label('Template'),

                Tables\Columns\TextColumn::make('last_deployed_at')
                    ->label('Ultimo Deploy')
                    ->since()
                    ->placeholder('Mai'),

                Tables\Columns\TextColumn::make('latestHealthCheck.status')
                    ->label('Health')
                    ->badge()
                    ->colors([
                        'success' => 'ok',
                        'warning' => 'warning',
                        'danger'  => 'error',
                        'gray'    => 'unknown',
                    ])
                    ->placeholder('N/D'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'running'      => 'In Esecuzione',
                        'stopped'      => 'Fermato',
                        'error'        => 'Errore',
                        'provisioning' => 'Provisioning',
                        'pending'      => 'In Attesa',
                    ]),

                Tables\Filters\SelectFilter::make('environment')
                    ->label('Ambiente')
                    ->options([
                        'production'  => 'Production',
                        'staging'     => 'Staging',
                        'development' => 'Development',
                    ]),

                Tables\Filters\SelectFilter::make('server_id')
                    ->label('Server')
                    ->relationship('server', 'name'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    // Start
                    Tables\Actions\Action::make('start')
                        ->label('Avvia')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->visible(fn ($record) => $record->isStopped() || $record->hasError())
                        ->requiresConfirmation()
                        ->action(function (Project $record) {
                            StartProjectJob::dispatch($record);
                            Notification::make()->title('Avvio in corso...')->info()->send();
                        }),

                    // Stop
                    Tables\Actions\Action::make('stop')
                        ->label('Ferma')
                        ->icon('heroicon-o-stop')
                        ->color('warning')
                        ->visible(fn ($record) => $record->isRunning())
                        ->requiresConfirmation()
                        ->action(function (Project $record) {
                            StopProjectJob::dispatch($record);
                            Notification::make()->title('Arresto in corso...')->warning()->send();
                        }),

                    // Restart
                    Tables\Actions\Action::make('restart')
                        ->label('Riavvia')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->visible(fn ($record) => $record->isRunning())
                        ->requiresConfirmation()
                        ->action(function (Project $record) {
                            RestartProjectJob::dispatch($record);
                            Notification::make()->title('Riavvio in corso...')->info()->send();
                        }),

                    Tables\Actions\Action::make('view_logs')
                        ->label('Log')
                        ->icon('heroicon-o-document-text')
                        ->url(fn ($record) => static::getUrl('logs', ['record' => $record])),

                    Tables\Actions\Action::make('backup')
                        ->label('Backup DB')
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->visible(fn ($record) => $record->isRunning())
                        ->requiresConfirmation()
                        ->action(function (Project $record) {
                            BackupProjectJob::dispatch($record);
                            Notification::make()->title('Backup avviato...')->info()->send();
                        }),

                    Tables\Actions\Action::make('open')
                        ->label('Apri nel browser')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn ($record) => "http://{$record->primary_domain}", shouldOpenInNewTab: true)
                        ->visible(fn ($record) => $record->isRunning()),
                ]),

                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_restart')
                        ->label('Riavvia selezionati')
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(fn ($r) => RestartProjectJob::dispatch($r));
                            Notification::make()->title('Riavvio in corso per i progetti selezionati')->info()->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            ProjectResource\RelationManagers\AuditLogsRelationManager::class,
            ProjectResource\RelationManagers\BackupsRelationManager::class,
            ProjectResource\RelationManagers\DomainsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'view'   => Pages\ViewProject::route('/{record}'),
            'edit'   => Pages\EditProject::route('/{record}/edit'),
            'logs'   => Pages\ProjectLogs::route('/{record}/logs'),
        ];
    }
}
