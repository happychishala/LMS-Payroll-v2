<?php

namespace App\Filament\Pages;

use App\Models\Expense;
use App\Models\Loan;
use App\Models\Repayments;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Facades\FilamentIcon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class Dashboard extends Page
{
    //use HasPageShield;
    protected static string $routePath = '/';

    protected static ?int $navigationSort = -2;
    use HasFiltersForm;

    /**
     * @var view-string
     */
    protected static string $view = 'filament-panels::pages.dashboard';

    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel ??
            static::$title ??
            __('filament-panels::pages/dashboard.title');
    }

    public function mount(): void
    {
        if (! filled($this->filters)) {
            $this->filters = $this->getDefaultFilters();
        }
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('selectedYear')
                    ->label('Year')
                    ->options($this->getYearOptions())
                    ->default((string) now()->year)
                    ->placeholder('Custom range')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state): void {
                        if ($state === 'all') {
                            $set('startDate', null);
                            $set('endDate', null);

                            return;
                        }

                        if (filled($state)) {
                            $set('startDate', "{$state}-01-01");
                            $set('endDate', "{$state}-12-31");
                        }
                    }),
                DatePicker::make('startDate')
                    ->label('Start Date')
                    ->placeholder('Select start date')
                    ->live()
                    ->afterStateUpdated(fn (Set $set, Get $get, $state) => $this->syncSelectedYear($set, $state, $get('endDate'))),
                DatePicker::make('endDate')
                    ->label('End Date')
                    ->placeholder('Select end date')
                    ->live()
                    ->afterStateUpdated(fn (Set $set, Get $get, $state) => $this->syncSelectedYear($set, $get('startDate'), $state)),
            ]);
    }

    private function getYearOptions(): array
    {
        $currentYear = now()->year;
        $startYear = collect([
            Loan::query()->min('loan_release_date'),
            Loan::query()->min('created_at'),
            Repayments::query()->min('receipt_date'),
            Expense::query()->min('created_at'),
        ])
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->year)
            ->min() ?? $currentYear;
        $years = [];

        $years['all'] = 'All Time';

        for ($year = $currentYear; $year >= $startYear; $year--) {
            $years[(string) $year] = (string) $year;
        }

        return $years;
    }

    private function getDefaultFilters(): array
    {
        $year = (string) now()->year;

        return [
            'selectedYear' => $year,
            'startDate' => "{$year}-01-01",
            'endDate' => "{$year}-12-31",
        ];
    }

    private function syncSelectedYear(Set $set, mixed $startDate, mixed $endDate): void
    {
        if (blank($startDate) && blank($endDate)) {
            $set('selectedYear', 'all');

            return;
        }

        if (blank($startDate) || blank($endDate)) {
            $set('selectedYear', null);

            return;
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $isWholeYear = $start->isStartOfYear() && $end->isEndOfYear() && $start->year === $end->year;

        $set('selectedYear', $isWholeYear ? (string) $start->year : null);
    }
    public static function getNavigationIcon(): ?string
    {
        return static::$navigationIcon
            ?? FilamentIcon::resolve('panels::pages.dashboard.navigation-item')
            ?? (Filament::hasTopNavigation() ? 'heroicon-m-home' : 'heroicon-o-home');
    }

    public static function routes(Panel $panel): void
    {
        Route::get(static::getRoutePath(), static::class)
            ->middleware(static::getRouteMiddleware($panel))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->name(static::getSlug());
    }

    public static function getRoutePath(): string
    {
        return static::$routePath;
    }

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return Filament::getWidgets();
    }

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getVisibleWidgets(): array
    {
        return $this->filterVisibleWidgets($this->getWidgets());
    }

    /**
     * @return int | string | array<string, int | string | null>
     */
    public function getColumns(): int | string | array
    {
        return [
            'md' => 2,
            'xl' => 3,
        ];
    }

    public function getTitle(): string | Htmlable
    {
        return 'Loan Management Dashboard';
    }

    public function getSubheading(): ?string
    {
        return 'Monitor your loan portfolio performance, collections, and business metrics';
    }
}
