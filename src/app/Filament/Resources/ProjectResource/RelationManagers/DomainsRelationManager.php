<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';
    protected static ?string $title = 'Domini';
    protected static ?string $icon = 'heroicon-o-globe-alt';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('domain')
                ->label('Dominio')
                ->required()
                ->placeholder('alias.example.com'),

            Forms\Components\Toggle::make('https_enabled')
                ->label('HTTPS'),

            Forms\Components\Toggle::make('is_primary')
                ->label('Dominio Primario'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->label('Dominio')
                    ->url(fn ($record) => $record->url, true),

                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Primario')
                    ->boolean(),

                Tables\Columns\IconColumn::make('https_enabled')
                    ->label('HTTPS')
                    ->boolean(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Stato')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'pending',
                        'danger'  => 'error',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Aggiungi Dominio'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn ($record) => $record->is_primary),
            ]);
    }
}
