<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    // Left: Avatar (Placeholder) & Identity
                    Stack::make([
                        TextColumn::make('initial')
                            ->default(fn ($record) => substr($record->company_name, 0, 2))
                            ->badge()
                            ->color('gray')
                            ->extraAttributes(['class' => 'w-10 h-10 rounded-full flex items-center justify-center bg-gray-200 text-lg font-bold']),
                        
                        TextColumn::make('company_name')
                            ->weight('bold')
                            ->searchable()
                            ->sortable(),
                            
                        TextColumn::make('name')
                            ->label('Buyer')
                            ->icon('heroicon-m-user')
                            ->color('gray')
                            ->searchable(),
                    ])->space(1),

                    // Center: Contact Info
                    Stack::make([
                        TextColumn::make('phone')
                            ->icon('heroicon-m-phone')
                            ->searchable()
                            ->color('gray')
                            ->alignLeft(),
                        TextColumn::make('email')
                            ->icon('heroicon-m-envelope')
                            ->searchable()
                            ->color('gray')
                            ->alignLeft(),
                    ])->space(1),
                    
                    // Right: Meta
                    Stack::make([
                         TextColumn::make('creator.name')
                            ->label('Created By')
                            ->icon('heroicon-m-pencil-square')
                            ->color('gray')
                            ->alignRight(),
                    ])->space(1),
                ])->from('md'),
            ])
            ->contentGrid([
                'md' => 1,
                'xl' => 1,
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
