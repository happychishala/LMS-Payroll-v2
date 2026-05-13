<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Filament\Resources\LoanResource;
use App\Models\StatusReason;
use App\Services\StatusReasonAuthorization;
use App\Services\StatusReasonService;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms\Get;

class ViewLoan extends ViewRecord
{
    protected static string $resource = LoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('assignStatusReason')
                ->label($this->record->statusReason ? 'Change Status Reason' : 'Assign Status Reason')
                ->icon('heroicon-o-tag')
                ->color('warning')
                ->visible(fn (): bool => StatusReasonAuthorization::canAssign(auth()->user()))
                ->form([
                    Select::make('status_reason_id')
                        ->label('Status Reason')
                        ->options(fn () => StatusReason::query()->where('is_active', true)->orderBy('code')->get()->mapWithKeys(
                            fn (StatusReason $statusReason) => [$statusReason->id => "{$statusReason->code} - {$statusReason->label}"]
                        )->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(),
                    Placeholder::make('status_reason_flags')
                        ->label('Rule Summary')
                        ->content(function (Get $get): string {
                            $statusReason = StatusReason::find($get('status_reason_id'));

                            if (! $statusReason) {
                                return 'Select a code to review its flags.';
                            }

                            return implode(' | ', array_filter([
                                $statusReason->suspend_submissions ? 'Suspend Submissions' : null,
                                $statusReason->client_may_replace ? 'Client May Replace' : null,
                                $statusReason->suspend_interest ? 'Suspend Interest' : null,
                                $statusReason->triggers_insurance_claim ? 'Triggers Insurance' : null,
                                $statusReason->blocks_new_loan ? 'Blocks New Loans' : null,
                            ])) ?: 'No system flags.';
                        }),
                    DatePicker::make('effective_date')
                        ->default(now()->toDateString())
                        ->required(),
                    Select::make('mode_of_exit')
                        ->options(StatusReason::modeOfExitOptions())
                        ->visible(function (Get $get): bool {
                            return (bool) optional(StatusReason::find($get('status_reason_id')))->requires_mode_of_exit;
                        }),
                    Select::make('affordability_reason')
                        ->options(StatusReason::affordabilityReasonOptions())
                        ->visible(function (Get $get): bool {
                            return (bool) optional(StatusReason::find($get('status_reason_id')))->requires_affordability_reason;
                        })
                        ->live(),
                    Toggle::make('management_approval_confirmed')
                        ->label('Management Approval Confirmed')
                        ->visible(function (Get $get): bool {
                            return (bool) optional(StatusReason::find($get('status_reason_id')))->requires_management_approval;
                        }),
                    Textarea::make('notes')
                        ->rows(3)
                        ->helperText('Required when affordability reason is Other.')
                        ->required(fn (Get $get): bool => $get('affordability_reason') === StatusReasonService::OTHER_AFFORDABILITY_REASON),
                ])
                ->action(function (array $data, StatusReasonService $service): void {
                    $statusReason = StatusReason::findOrFail($data['status_reason_id']);

                    $service->assign($this->record->loadMissing('borrower', 'statusReason'), $statusReason, $data, auth()->user());

                    Notification::make()
                        ->success()
                        ->title('Status reason saved')
                        ->send();

                    $this->redirect(LoanResource::getUrl('view', ['record' => $this->record]));
                }),
            Actions\Action::make('removeStatusReason')
                ->label('Remove Status Reason')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => StatusReasonAuthorization::canRemove(auth()->user()) && filled($this->record->status_reason_id))
                ->form([
                    DatePicker::make('effective_date')
                        ->default(now()->toDateString())
                        ->required(),
                    Textarea::make('removal_reason')
                        ->required()
                        ->rows(3),
                    Textarea::make('notes')
                        ->rows(3),
                ])
                ->action(function (array $data, StatusReasonService $service): void {
                    $service->remove($this->record->loadMissing('borrower', 'statusReason'), $data, auth()->user());

                    Notification::make()
                        ->success()
                        ->title('Status reason removed')
                        ->send();

                    $this->redirect(LoanResource::getUrl('view', ['record' => $this->record]));
                }),
            Actions\EditAction::make(),
        ];
    }
}
