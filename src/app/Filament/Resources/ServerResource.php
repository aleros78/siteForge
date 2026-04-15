<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServerResource\Pages;
use App\Models\Server;
use App\Services\DockerService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServerResource extends Resource
{
    protected static ?string $model = Server::class;
    protected static ?string $navigationIcon = 'heroicon-o-server';
    protected static ?string $navigationGroup = 'Infrastruttura';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informazioni Server')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('host')
                        ->label('Host / IP')
                        ->required()
                        ->default('localhost'),

                    Forms\Components\TextInput::make('base_path')
                        ->label('Path Base Progetti')
                        ->required()
                        ->default('/opt/siteforge/projects')
                        ->helperText('Cartella dove vengono creati i progetti su questo server'),

                    Forms\Components\Toggle::make('is_local')
                        ->label('Server Locale')
                        ->default(true)
                        ->reactive(),
                ])->columns(2),

            Forms\Components\Section::make('Connessione SSH')
                ->schema([
                    Forms\Components\TextInput::make('ssh_user')
                        ->label('Utente SSH'),

                    Forms\Components\TextInput::make('ssh_port')
                        ->label('Porta SSH')
                        ->numeric()
                        ->default(22),

                    Forms\Components\Textarea::make('ssh_private_key')
                        ->label('Chiave Privata SSH')
                        ->rows(6)
                        ->password()
                        ->helperText('Incolla la chiave privata SSH (es: contenuto di ~/.ssh/id_rsa)'),
                ])
                ->columns(2)
                ->hidden(fn (Forms\Get $get) => $get('is_local')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('host')
                    ->label('Host'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Stato')
                    ->colors([
                        'success' => 'online',
                        'danger'  => 'offline',
                        'warning' => 'unknown',
                    ]),

                Tables\Columns\TextColumn::make('docker_version')
                    ->label('Docker')
                    ->placeholder('N/D'),

                Tables\Columns\TextColumn::make('projects_count')
                    ->label('Progetti')
                    ->counts('projects'),

                Tables\Columns\IconColumn::make('is_local')
                    ->label('Locale')
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_checked_at')
                    ->label('Ultima Verifica')
                    ->since()
                    ->placeholder('Mai'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'online'  => 'Online',
                        'offline' => 'Offline',
                        'unknown' => 'Sconosciuto',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('ping')
                    ->label('Verifica')
                    ->icon('heroicon-o-signal')
                    ->color('info')
                    ->action(function (Server $record) {
                        $docker = app(DockerService::class);
                        $result = $docker->version();
                        $version = trim($result['output']);

                        $record->update([
                            'status'          => $result['success'] ? 'online' : 'offline',
                            'docker_version'  => $result['success'] ? $version : null,
                            'last_checked_at' => now(),
                        ]);

                        if ($result['success']) {
                            Notification::make()
                                ->title("Docker {$version} - Server online")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Server non raggiungibile')
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListServers::route('/'),
            'create' => Pages\CreateServer::route('/create'),
            'edit'   => Pages\EditServer::route('/{record}/edit'),
        ];
    }
}
