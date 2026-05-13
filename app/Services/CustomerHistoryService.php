<?php

namespace App\Services;

use App\Models\Borrower;
use App\Models\BorrowerFiles;
use App\Models\Repayments;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CustomerHistoryService
{
    public function searchBorrowers(?string $search, int $limit = 10): Collection
    {
        $term = trim((string) $search);

        if ($term === '') {
            return collect();
        }

        return Borrower::query()
            ->where(function ($query) use ($term): void {
                $query->where('customer_id', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('other_names', 'like', "%{$term}%")
                    ->orWhere('full_name', 'like', "%{$term}%")
                    ->orWhere('mobile', 'like', "%{$term}%")
                    ->orWhere('identification', 'like', "%{$term}%");
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function build(int|string|null $borrowerId): ?array
    {
        if (! $borrowerId) {
            return null;
        }

        $borrower = Borrower::query()
            ->with([
                'loans.loan_type',
                'loans.statusReason',
                'loans.latestStatusReasonEvent.statusReason',
                'loans.statusReasonEvents.statusReason',
            ])
            ->find($borrowerId);

        if (! $borrower) {
            return null;
        }

        $loans = $borrower->loans
            ->sortByDesc(fn ($loan) => optional($loan->loan_release_date)->timestamp ?? 0)
            ->values();

        $loanIdentifiers = $loans
            ->flatMap(fn ($loan) => array_filter([
                $loan->loan_id ? (string) $loan->loan_id : null,
                $loan->id !== null ? (string) $loan->id : null,
            ]))
            ->unique()
            ->values();

        $repayments = $loanIdentifiers->isEmpty()
            ? collect()
            : Repayments::query()
                ->whereIn('loan_id', $loanIdentifiers->all())
                ->orderByDesc('receipt_date')
                ->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->get()
                ->values();

        $statusEvents = $loans
            ->flatMap(fn ($loan) => $loan->statusReasonEvents->map(function ($event) use ($loan) {
                $event->setRelation('loan', $loan);

                return $event;
            }))
            ->sortByDesc(fn ($event) => optional($event->effective_date)->timestamp ?? 0)
            ->values();

        $documents = $borrower->getMedia('attachments')
            ->map(fn ($media) => [
                'name' => $media->file_name,
                'url' => $this->buildDocumentUrl(
                    'public',
                    method_exists($media, 'getPathRelativeToRoot')
                        ? (string) $media->getPathRelativeToRoot()
                        : ltrim(str_replace(storage_path('app/public'), '', (string) $media->getPath()), DIRECTORY_SEPARATOR)
                ),
                'size' => $media->size,
                'uploaded_at' => $media->created_at,
            ]);

        if ($documents->isEmpty()) {
            $documents = BorrowerFiles::query()
                ->where('borrower_id', $borrower->id)
                ->get()
                ->map(fn ($file) => [
                    'name' => basename((string) $file->file_path),
                    'url' => $this->buildDocumentUrl('borrowers', (string) $file->file_path),
                    'size' => null,
                    'uploaded_at' => $file->getRawOriginal('created_at'),
                ]);
        }

        return [
            'borrower' => $borrower,
            'summary' => [
                'total_loans' => $loans->count(),
                'active_loans' => $loans->filter(fn ($loan) => $this->isActiveLoanStatus($loan->loan_status))->count(),
                'total_principal' => round((float) $loans->sum('principal_amount'), 2),
                'current_balance' => round((float) $loans->sum('balance'), 2),
                'total_repayments' => round((float) $repayments->sum(fn ($repayment) => (float) ($repayment->receipt_amount ?? $repayment->payments ?? 0)), 2),
                'repayment_count' => $repayments->count(),
                'documents_count' => $documents->count(),
            ],
            'loans' => $loans,
            'repayments' => $repayments,
            'status_events' => $statusEvents,
            'documents' => $documents->values(),
        ];
    }

    protected function buildDocumentUrl(string $disk, string $path): string
    {
        return route('customer-history.documents.show', [
            'disk' => $disk,
            'path' => base64_encode(ltrim($path, '/')),
        ]);
    }

    protected function isActiveLoanStatus(?string $status): bool
    {
        return in_array(Str::lower(trim((string) $status)), [
            'active',
            'approved',
        ], true);
    }
}
