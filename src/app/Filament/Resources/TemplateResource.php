<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TemplateResource\Pages;
use App\Models\Template;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TemplateResource extends Resource
{
    protected static ?string $model = Template::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Infrastruttura';
    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informazioni Template')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, Forms\Set $set) =>
                            $set('slug', Str::slug($state))
                        ),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->unique(ignoreRecord: true),

                    Forms\Components\Select::make('type')
                        ->label('Tipo')
                        ->options([
                            'laravel' => 'Laravel',
                            'php'     => 'PHP Puro',
                            'nodejs'  => 'Node.js',
                            'static'  => 'Sito Statico',
                            'custom'  => 'Custom',
                        ])
                        ->required()
                        ->default('laravel'),

                    Forms\Components\TextInput::make('version')
                        ->label('Versione')
                        ->default('1.0.0'),

                    Forms\Components\Textarea::make('description')
                        ->label('Descrizione')
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Attivo')
                        ->default(true),
                ])->columns(2),

            Forms\Components\Section::make('File Template')
                ->description('I file generati per ogni progetto. Usa {{placeholder}} per i valori dinamici.')
                ->schema([
                    Forms\Components\Repeater::make('files')
                        ->label('File')
                        ->relationship()
                        ->schema([
                            Forms\Components\TextInput::make('filename')
                                ->label('Nome file stub')
                                ->required()
                                ->placeholder('docker-compose.stub'),

                            Forms\Components\TextInput::make('target_path')
                                ->label('Path destinazione')
                                ->required()
                                ->placeholder('docker-compose.yml'),

                            Forms\Components\Select::make('type')
                                ->label('Tipo')
                                ->options([
                                    'docker-compose' => 'Docker Compose',
                                    'nginx'          => 'Nginx Config',
                                    'env'            => '.env',
                                    'custom'         => 'Custom',
                                ])
                                ->required()
                                ->default('custom'),

                            Forms\Components\Toggle::make('is_required')
                                ->label('Obbligatorio')
                                ->default(true),

                            Forms\Components\Textarea::make('content')
                                ->label('Contenuto')
                                ->required()
                                ->rows(20)
                                ->fontFamily('mono')
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->itemLabel(fn (array $state): ?string => $state['filename'] ?? null)
                        ->addActionLabel('Aggiungi file')
                        ->collapsible()
                        ->columnSpanFull(),
                ]),
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

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tipo')
                    ->colors([
                        'primary' => 'laravel',
                        'warning' => 'php',
                        'success' => 'nodejs',
                        'info'    => 'static',
                        'gray'    => 'custom',
                    ]),

                Tables\Columns\TextColumn::make('version')
                    ->label('Versione'),

                Tables\Columns\TextColumn::make('files_count')
                    ->label('File')
                    ->counts('files'),

                Tables\Columns\TextColumn::make('projects_count')
                    ->label('Progetti')
                    ->counts('projects'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_builtin')
                    ->label('Built-in')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'laravel' => 'Laravel',
                        'php'     => 'PHP',
                        'nodejs'  => 'Node.js',
                        'static'  => 'Static',
                        'custom'  => 'Custom',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')->label('Attivo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->hidden(fn ($record) => $record->is_builtin),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTemplates::route('/'),
            'create' => Pages\CreateTemplate::route('/create'),
            'edit'   => Pages\EditTemplate::route('/{record}/edit'),
        ];
    }
}
