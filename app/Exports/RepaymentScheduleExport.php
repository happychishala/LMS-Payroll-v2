<?php
namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RepaymentScheduleExport implements FromCollection, WithHeadings
{
    protected $schedule;

    public function __construct(Collection $schedule)
    {
        $this->schedule = $schedule;
    }

    public function collection(): Collection
    {
        return $this->schedule;
    }

    public function headings(): array
    {
        return array_keys($this->schedule->first());
    }
}
