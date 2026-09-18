<x-filament::page>
    <form wire:submit.prevent="submit" x-on:keydown.enter.prevent>
        {{ $this->form }}
        <x-filament::button type="submit" class="mt-4">Create Top-Up Loan</x-filament::button>
    </form>

    @if (!empty($this->selectedLoanBalances))
        <div class="mt-4 p-4 bg-gray-100 dark:bg-gray-800 rounded">
            <div class="font-semibold mb-2">Selected Loan Balances:</div>
            <ul class="list-disc pl-5">
                @foreach ($this->selectedLoanBalances as $balance)
                    <li>{{ $balance }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (!empty($this->selectedLoanBalances) && (float) ($this->topup_amount ?? 0) <= 0)
        <div class="mt-4 p-4 rounded border border-warning-200 dark:border-warning-800 bg-warning-50 dark:bg-warning-950/30 text-sm">
            Enter `Additional Top-Up Amount` to calculate the new loan table.
        </div>
    @endif

    @if (!empty($this->topUpPreview))
        <div class="mt-4 p-4 rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <div class="font-semibold mb-3">Top-Up Preview</div>

            @if (!empty($this->topUpPreview['error']))
                <div class="text-sm text-danger-600 dark:text-danger-400">
                    {{ $this->topUpPreview['error'] }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Calculation Item</th>
                                <th class="px-3 py-2 text-left font-semibold">Amount</th>
                                <th class="px-3 py-2 text-left font-semibold">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="px-3 py-2">Existing loan balances</td>
                                <td class="px-3 py-2">{{ number_format($this->topUpPreview['total_outstanding'], 2) }}</td>
                                <td class="px-3 py-2">
                                    {{ $this->topUpPreview['template_loan']->loan_type->loan_name ?? $this->topUpPreview['template_loan']->loan_category }}
                                </td>
                            </tr>
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="px-3 py-2">New loan type</td>
                                <td class="px-3 py-2">{{ $this->topUpPreview['loan_type']->loan_name ?? 'N/A' }}</td>
                                <td class="px-3 py-2">Applied to the new top-up loan and its pricing.</td>
                            </tr>
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="px-3 py-2">Additional Top-Up Amount</td>
                                <td class="px-3 py-2">{{ number_format($this->topUpPreview['topup_amount'], 2) }}</td>
                                <td class="px-3 py-2">This is the new cash amount entered above.</td>
                            </tr>
                            <tr class="border-t border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/40">
                                <td class="px-3 py-2 font-semibold">New Loan Principal</td>
                                <td class="px-3 py-2 font-semibold">{{ number_format($this->topUpPreview['new_principal'], 2) }}</td>
                                <td class="px-3 py-2">Existing balances + Additional Top-Up Amount.</td>
                            </tr>
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="px-3 py-2">Estimated fees</td>
                                <td class="px-3 py-2">{{ number_format($this->topUpPreview['fees_total'], 2) }}</td>
                                <td class="px-3 py-2">Applied against the additional top-up amount.</td>
                            </tr>
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="px-3 py-2">Accrued loan deductions</td>
                                <td class="px-3 py-2">{{ number_format($this->topUpPreview['accrued_deductions_total'], 2) }}</td>
                                <td class="px-3 py-2">Unpaid accrued interest and insurance from the selected loan(s).</td>
                            </tr>
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="px-3 py-2">Total settlement amount</td>
                                <td class="px-3 py-2">{{ number_format($this->topUpPreview['total_settled_amount'], 2) }}</td>
                                <td class="px-3 py-2">Existing loan balances + accrued loan deductions.</td>
                            </tr>
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="px-3 py-2">Total deductions</td>
                                <td class="px-3 py-2">{{ number_format($this->topUpPreview['total_deductions'], 2) }}</td>
                                <td class="px-3 py-2">Estimated fees + accrued loan deductions.</td>
                            </tr>
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="px-3 py-2">Net cash disbursement</td>
                                <td class="px-3 py-2">{{ number_format($this->topUpPreview['net_disbursement'], 2) }}</td>
                                <td class="px-3 py-2">Additional Top-Up Amount - total deductions.</td>
                            </tr>
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="px-3 py-2">Monthly repayment</td>
                                <td class="px-3 py-2">{{ number_format($this->topUpPreview['financials']['total_monthly_repayment'], 2) }}</td>
                                <td class="px-3 py-2">Calculated from the new loan principal.</td>
                            </tr>
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="px-3 py-2">Release date</td>
                                <td class="px-3 py-2">{{ $this->topUpPreview['release_date'] }}</td>
                                <td class="px-3 py-2">New top-up loan release date.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @if (($this->topUpPreview['can_create'] ?? true) !== true)
                    <div class="mt-3 text-sm text-danger-600 dark:text-danger-400">
                        @if (($this->topUpPreview['additional_amount_shortfall'] ?? 0) > 0)
                            Additional Top-Up Amount is too low. Increase it by at least {{ number_format($this->topUpPreview['additional_amount_shortfall'], 2) }} to cover estimated fees and accrued loan deductions.
                        @else
                            Enter an Additional Top-Up Amount greater than zero.
                        @endif
                    </div>
                @endif

                @if (($this->topUpPreview['withholding_amount'] ?? 0) > 0)
                    <div class="mt-3 text-sm text-warning-700 dark:text-warning-400">
                        Upfront withholding will be created for {{ number_format($this->topUpPreview['withholding_amount'], 2) }}.
                    </div>
                @endif
            @endif
        </div>
    @endif
</x-filament::page>
