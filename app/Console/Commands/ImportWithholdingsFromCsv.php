<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Loan;
use App\Models\Borrower;
use App\Models\Withholding;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ImportWithholdingsFromCsv extends Command
{
    protected $signature = 'import:withholdings {path=/Users/zedfin-it/Payroll_LMS/withholding.csv}';
    protected $description = 'Import withholdings data from a CSV file';

    public function handle(): void
    {
        $path = $this->argument('path');

        if (!file_exists($path)) {
            $this->error("File not found: $path");
            return;
        }

        $rows = array_map('str_getcsv', file($path));
        $header = array_map(fn($h) => strtolower(trim($h)), array_shift($rows));

// Remove BOM from first header column if present
if (str_starts_with($header[0], "\u{FEFF}")) {
    $header[0] = str_replace("\u{FEFF}", '', $header[0]);
}
dump($header); // or print_r($header);

        foreach ($rows as $index => $row) {
            $data = array_combine($header, $row);

            // Normalize values
            $loanNumber = trim($data['loan_id']);
            $clientRef  = trim($data['client_id']);
            $amount     = floatval($data['amount']);
            $statusText = strtolower(trim($data['status']));
            $excelDate  = intval($data['loan_issue_date']);


            // Parse the issue date from CSV (expects format like '20240731' or '31/07/2024')
            $rawDate = $data['loan_issue_date'] ?? null;
            if ($rawDate) {
                // Try to parse as Y-m-d, d/m/Y, or Excel serial
                if (is_numeric($rawDate) && strlen($rawDate) > 4) {
                    // Excel serial date (if needed)
                    $issueDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rawDate)->format('Y-m-d');
                } else {
                    try {
                        $issueDate = Carbon::parse($rawDate)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $issueDate = null;
                    }
                }
            } else {
                $issueDate = null;
            }


            // Map status
            $status = match (true) {
                str_contains($statusText, 'refunded') => 'Refunded',
                str_contains($statusText, 'receipted') => 'Refund',
                default => 'Pending',
            };

            // Get internal borrower & loan IDs
            $loan = Loan::where('loan_id', $loanNumber)->first();
            $borrower = Borrower::where('customer_id', $clientRef)->first();

            if (!$loan || !$borrower) {
                $this->warn("Skipped row $index → Loan or Borrower not found (Loan: $loanNumber, Client: $clientRef)");
                continue;
            }

            // Avoid duplicates
            $existing = Withholding::where('loan_id', $loan->id)->first();
            if ($existing) {
                $this->info("Skipped row $index → Withholding already exists for loan {$loan->id}");
                continue;
            }

            // Insert withholding record
            Withholding::create([
                'loan_id'             => $loan->loan_id,        // use string loan_id
                'borrower_id'         => $borrower->customer_id, // use string customer_id
                'installment_due_date'=> $issueDate,
                'installment_amount'  => $amount,
                'withholding_status'  => $status,
                'remarks'             => "Imported from CSV row $index",
            ]);

            $this->info("Imported row $index → Loan {$loan->id}, Borrower {$borrower->id}, Status: $status");
        }

        $this->info('✅ Withholding import complete.');
    }
}
