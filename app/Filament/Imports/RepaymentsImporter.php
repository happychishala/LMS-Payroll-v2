<?php
// File: app/Filament/Imports/RepaymentsImporter.php

namespace App\Filament\Imports;

use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\Import as ImportModel;
use App\Models\Repayments;

class RepaymentsImporter extends Importer
{
    public static function getModel(): string
    {
        return Repayments::class;
    }

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('loan_id')->relationship(),
            ImportColumn::make('employee_no'),
            ImportColumn::make('client_id'),
            ImportColumn::make('batch_no'),
            ImportColumn::make('receipt_date'),
            ImportColumn::make('receipt_amount')->numeric(),
            ImportColumn::make('paid_principal')->numeric(),
            ImportColumn::make('paid_interest')->numeric(),
            ImportColumn::make('monthly_installment_balance')->numeric(),
            ImportColumn::make('opening_balance')->numeric(),
            ImportColumn::make('closing_balance')->numeric(),
            ImportColumn::make('repayment_number')->integer(),
            ImportColumn::make('monthly_repayment_balance')->numeric(),
            ImportColumn::make('payment_status'),
            ImportColumn::make('extra_payments')->numeric(),
            ImportColumn::make('refund')->numeric(),
            ImportColumn::make('insurance_charge')->numeric(),
            ImportColumn::make('zed_fin')->numeric(),
            ImportColumn::make('sanlam')->numeric(),
            ImportColumn::make('interest_rate')->numeric(),
            ImportColumn::make('loan_amount')->numeric(),
            ImportColumn::make('term')->integer(),
            ImportColumn::make('loan_issue_date'),
            ImportColumn::make('employer'),
            ImportColumn::make('status'),
            // legacy fields if still needed
            ImportColumn::make('payments')->numeric(),
            ImportColumn::make('payments_method'),
            ImportColumn::make('reference_number'),
        ];
    }

    /**
     * Match the base class signature exactly:
     *    public static function getCompletedNotificationBody(Import $import): string
     */
    public static function getCompletedNotificationBody(ImportModel $import): string
    {
        return 'Imported ' . $import->processed_count . ' repayment records successfully.';
    }
}
