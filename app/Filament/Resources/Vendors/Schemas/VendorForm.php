<?php

namespace App\Filament\Resources\Vendors\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VendorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Vendor Details')
                    ->schema([
                        TextInput::make('name')
                            ->label('Contact Person')
                            ->required(),
                        TextInput::make('company_name')
                            ->required(),
                        TextInput::make('initial')
                            ->label('Initial'),
                        Textarea::make('address')
                            ->columnSpanFull(),
                        TextInput::make('vat_number')
                            ->label('VAT Number'),
                        Toggle::make('is_pkp')
                            ->label('Is PKP')
                            ->live(),
                        FileUpload::make('npwp_file')
                            ->label('Upload NPWP')
                            ->directory('vendor-documents')
                            ->visibility('private')
                            ->hidden(fn ($get) => !$get('is_pkp')), // Optional: hide if not PKP? User said nullable. I'll make it visible or maybe dependent. Let's make it dependent on is_pkp for better UX, or just always show. User said "is_pkp(bool), upload npwp(nullable)". Usually NPWP is related to tax/PKP. I'll leave it always visible but nullable for now, or maybe hide if not PKP is cleaner. Let's just make it visible.
                        TextInput::make('email')
                            ->email(),
                        TextInput::make('phone')
                            ->tel(),
                    ])->columns(2),
            ]);
    }
}
