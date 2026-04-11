<?php

namespace App\Filament\Resources\Quotations\Tables;

use App\Jobs\GenerateQuotationPdfJob;
use App\Models\Quotation;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class QuotationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('quotation_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.company_name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'sent' => 'info',
                        'accepted' => 'success',
                        'declined' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('pdf_status')
                    ->label('PDF')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'queued' => 'Queued',
                        'generating' => 'Generating',
                        'generated' => 'Ready',
                        'failed' => 'Failed',
                        default => 'Not Generated',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'queued', 'generating' => 'warning',
                        'generated' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('grand_total')
                    ->money('IDR', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('Created By'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('generatePdf')
                    ->label('Generate PDF')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('warning')
                    ->disabled(fn (Quotation $record): bool => in_array($record->pdf_status, ['queued', 'generating'], true))
                    ->action(function (Quotation $record): void {
                        if (filled($record->pdf_path) && Storage::disk('local')->exists($record->pdf_path)) {
                            Storage::disk('local')->delete($record->pdf_path);
                        }

                        $record->forceFill([
                            'pdf_status' => 'queued',
                            'pdf_path' => null,
                            'pdf_requested_at' => now(),
                            'pdf_generated_at' => null,
                            'pdf_failed_at' => null,
                            'pdf_error' => null,
                        ])->save();

                        GenerateQuotationPdfJob::dispatch($record->getKey())
                            ->onQueue('pdf')
                            ->afterCommit();

                        Notification::make()
                            ->title('PDF sedang digenerate di worker')
                            ->success()
                            ->send();
                    }),
                Action::make('downloadPdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->disabled(fn (Quotation $record): bool => $record->pdf_status !== 'generated' || blank($record->pdf_path))
                    ->url(fn (Quotation $record): ?string => $record->pdf_status === 'generated' && filled($record->pdf_path)
                        ? URL::temporarySignedRoute(
                            'quotations.pdf.download',
                            now()->addMinutes(5),
                            ['quotation' => $record],
                        )
                        : null)
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
