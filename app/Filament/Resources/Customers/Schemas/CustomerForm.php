<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer Details')
                    ->schema([
                        TextInput::make('name')
                            ->label('Contact Person')
                            ->required(),
                        TextInput::make('company_name')
                            ->required(),
                        TextInput::make('initial')
                            ->label('Initial')
                            ->placeholder('e.g., ABC')
                            ->maxLength(10),
                        TextInput::make('email')
                            ->email()
                            ->required(),
                        TextInput::make('phone')
                            ->tel(),
                        TextInput::make('vat_number'),
                        Textarea::make('address')
                            ->columnSpanFull(),
                    ])->columns(2),
                Section::make('Meta')
                    ->schema([
                        Placeholder::make('created_at')
                            ->label('Created at')
                            ->content(fn ($record) => $record?->created_at?->toDayDateTimeString() ?? '-'),
                        Placeholder::make('updated_at')
                            ->label('Last modified at')
                            ->content(fn ($record) => $record?->updated_at?->toDayDateTimeString() ?? '-'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
