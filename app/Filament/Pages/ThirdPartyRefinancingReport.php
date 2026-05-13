<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Loan;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ThirdPartyRefinancingExport;
use App\Services\ThirdPartyRefinancingQueryBuilder;

class ThirdPartyRefinancingReport extends Page
{
    use WithPagination;

    protected static ?string $navigationGroup = 'Loans';
    protected static ?string $navigationIcon  = 'heroicon-o-arrow-path-rounded-square';
    protected static ?int $navigationSort     = 40;
    protected static string $view             = 'filament.pages.third-party-refinancing-report';
    protected static ?string $title           = 'Third-Party Refinancing';

    public ?string $startDate = '';
    public ?string $endDate   = '';
    public ?string $employer  = '';
    public ?string $thirdPartyName = '';
    public ?string $recency   = '';

    /** Dropdown data sources */
    public function getEmployersProperty()
    {
        return Loan::whereNotNull('employer')->distinct()->orderBy('employer')->pluck('employer');
    }

    public function getThirdPartyNamesProperty()
    {
        // union names across the three columns
        return collect()
            ->merge(Loan::whereNotNull('third_party_name')->distinct()->pluck('third_party_name'))
            ->merge(Loan::whereNotNull('third_party_name_2')->distinct()->pluck('third_party_name_2'))
            ->merge(Loan::whereNotNull('third_party_name_3')->distinct()->pluck('third_party_name_3'))
            ->filter()->unique()->sort()->values();
    }

    /** Preview: first 20 rows of what will be exported */
    public function getPreviewRowsProperty()
    {
        return ThirdPartyRefinancingQueryBuilder::build(
            $this->startDate ?: null,
            $this->endDate ?: null,
            $this->employer ?: null,
            $this->thirdPartyName ?: null,
            $this->recency ?: null,
        )->take(20);
    }

    /** Snapshot for the performance cards */
    public function getSnapshotProperty(): array
    {
        $rows = ThirdPartyRefinancingQueryBuilder::build(
            $this->startDate ?: null,
            $this->endDate ?: null,
            $this->employer ?: null,
            $this->thirdPartyName ?: null,
            $this->recency ?: null,
        );

        return ThirdPartyRefinancingQueryBuilder::summarize($rows);
    }

    public function updated($prop)
    {
        if (in_array($prop, ['startDate','endDate','employer','thirdPartyName','recency'])) {
            $this->resetPage(); // keeps pagination sane if you paginate later
        }
    }

    public function export()
    {
        // Validate using the DIA-based labels
        $this->validate([
            'startDate'     => 'nullable|date',
            'endDate'       => 'nullable|date|after_or_equal:startDate',
            'employer'      => 'nullable|string',
            'thirdPartyName'=> 'nullable|string',
            'recency'       => 'nullable|in:Pass (0 days),Pass (1 to 59),Special Mention (60 to 89),Substandard (1) (90 to 119),Substandard (2) (120 to 179),Doubtful (1) (180 to 269),Doubtful (2) (270 to 364),Loss (365+)',
        ]);

        return Excel::download(
            new ThirdPartyRefinancingExport(
                $this->startDate ?: null,
                $this->endDate ?: null,
                $this->employer ?: null,
                $this->thirdPartyName ?: null,
                $this->recency ?: null
            ),
            'third_party_refinancing_' . now()->format('Ymd_His') . '.csv'
        );
    }
}
