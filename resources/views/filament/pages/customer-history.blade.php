<x-filament::page>
    @php
        $history = $this->customerHistory;
        $results = $this->searchResults;
        $borrower = $history['borrower'] ?? null;
        $summary = $history['summary'] ?? [];
        $loans = $history['loans'] ?? collect();
        $repayments = $history['repayments'] ?? collect();
        $statusEvents = $history['status_events'] ?? collect();
        $documents = $history['documents'] ?? collect();
    @endphp

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto]">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Search customer</label>
                    <input
                        type="text"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Customer ID, name, NRC, or phone"
                        class="mt-1 w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
                    />
                    <p class="mt-2 text-xs text-gray-500">Select a borrower to view profile, loans, repayments, documents, and status changes.</p>
                </div>

                @if ($borrower)
                    <div class="flex items-end">
                        <x-filament::button color="gray" wire:click="clearSelection">
                            Clear
                        </x-filament::button>
                    </div>
                @endif
            </div>

            @if (filled($search) && ! $borrower)
                <div class="mt-4 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                    @forelse ($results as $result)
                        <button
                            type="button"
                            wire:click="selectBorrower({{ $result->id }})"
                            class="flex w-full items-center justify-between border-b border-gray-200 px-4 py-3 text-left last:border-b-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                        >
                            <span>
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ trim(($result->first_name ?? '') . ' ' . ($result->other_names ?? '') . ' ' . ($result->last_name ?? '')) ?: 'Unnamed borrower' }}
                                </span>
                                <span class="block text-xs text-gray-500">
                                    ID: {{ $result->customer_id ?: '-' }} | NRC: {{ $result->identification ?: '-' }} | Phone: {{ $result->mobile ?: '-' }}
                                </span>
                            </span>
                            <span class="text-xs text-gray-500">{{ $result->employer ?: 'No employer' }}</span>
                        </button>
                    @empty
                        <div class="px-4 py-3 text-sm text-gray-500">No customers matched that search.</div>
                    @endforelse
                </div>
            @endif
        </div>

        @if ($borrower)
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Customer ID</div>
                    <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $borrower->customer_id ?: '-' }}</div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Loans</div>
                    <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $summary['total_loans'] ?? 0 }}</div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Active Loans</div>
                    <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $summary['active_loans'] ?? 0 }}</div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Total Principal</div>
                    <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">ZMW {{ number_format((float) ($summary['total_principal'] ?? 0), 2) }}</div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Current Balance</div>
                    <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">ZMW {{ number_format((float) ($summary['current_balance'] ?? 0), 2) }}</div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Repayments</div>
                    <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $summary['repayment_count'] ?? 0 }}</div>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-[1.05fr_1.95fr]">
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Customer Profile</h2>
                    <div class="mt-4 space-y-3 text-sm text-gray-700 dark:text-gray-200">
                        <div><span class="font-medium text-gray-500">Name:</span> {{ trim(($borrower->first_name ?? '') . ' ' . ($borrower->other_names ?? '') . ' ' . ($borrower->last_name ?? '')) ?: '-' }}</div>
                        <div><span class="font-medium text-gray-500">NRC:</span> {{ $borrower->identification ?: '-' }}</div>
                        <div><span class="font-medium text-gray-500">Phone:</span> {{ $borrower->mobile ?: '-' }}</div>
                        <div><span class="font-medium text-gray-500">Email:</span> {{ $borrower->email ?: '-' }}</div>
                        <div><span class="font-medium text-gray-500">Employer:</span> {{ $borrower->employer ?: '-' }}</div>
                        <div><span class="font-medium text-gray-500">Occupation:</span> {{ $borrower->occupation ?: '-' }}</div>
                        <div><span class="font-medium text-gray-500">Address:</span> {{ $borrower->address ?: '-' }}</div>
                    </div>

                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Documents</h3>
                        <div class="mt-3 space-y-2">
                            @forelse ($documents as $document)
                                <a
                                    href="{{ $document['url'] }}"
                                    target="_blank"
                                    class="flex items-center justify-between rounded-xl border border-gray-200 px-3 py-2 text-sm hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                                >
                                    <span class="truncate pr-4 text-gray-700 dark:text-gray-200">{{ $document['name'] }}</span>
                                    <span class="shrink-0 text-xs text-gray-500">
                                        {{ filled($document['uploaded_at']) ? \Illuminate\Support\Carbon::parse($document['uploaded_at'])->format('d M Y') : 'File' }}
                                    </span>
                                </a>
                            @empty
                                <p class="text-sm text-gray-500">No customer documents found.</p>
                            @endforelse
                        </div>
                    </div>
                </section>

                <section class="space-y-6">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Loan History</h2>
                            <div class="text-sm text-gray-500">Total repaid: ZMW {{ number_format((float) ($summary['total_repayments'] ?? 0), 2) }}</div>
                        </div>

                        <div class="mt-4 overflow-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="text-xs uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="pb-3 pr-4">Loan ID</th>
                                        <th class="pb-3 pr-4">Type</th>
                                        <th class="pb-3 pr-4">Released</th>
                                        <th class="pb-3 pr-4">Principal</th>
                                        <th class="pb-3 pr-4">Balance</th>
                                        <th class="pb-3 pr-4">Status</th>
                                        <th class="pb-3">Latest Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($loans as $loan)
                                        <tr class="border-t border-gray-200 dark:border-gray-700">
                                            <td class="py-3 pr-4 font-medium text-gray-900 dark:text-white">{{ $loan->loan_id ?: $loan->id }}</td>
                                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">{{ $loan->loan_type?->loan_name ?? $loan->loan_category ?? '-' }}</td>
                                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">{{ optional($loan->loan_release_date)->format('d M Y') ?? '-' }}</td>
                                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">ZMW {{ number_format((float) ($loan->principal_amount ?? 0), 2) }}</td>
                                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">ZMW {{ number_format((float) ($loan->balance ?? 0), 2) }}</td>
                                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">{{ $loan->loan_status ?: '-' }}</td>
                                            <td class="py-3 text-gray-700 dark:text-gray-200">{{ $loan->latestStatusReasonEvent?->statusReason?->name ?? $loan->statusReason?->name ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="py-4 text-sm text-gray-500">No loans found for this customer.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Repayment History</h2>
                        <div class="mt-4 overflow-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="text-xs uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="pb-3 pr-4">Date</th>
                                        <th class="pb-3 pr-4">Loan ID</th>
                                        <th class="pb-3 pr-4">Amount</th>
                                        <th class="pb-3 pr-4">Principal</th>
                                        <th class="pb-3 pr-4">Interest</th>
                                        <th class="pb-3">Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($repayments as $repayment)
                                        <tr class="border-t border-gray-200 dark:border-gray-700">
                                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">{{ optional($repayment->receipt_date ?? $repayment->payment_date)->format('d M Y') ?? '-' }}</td>
                                            <td class="py-3 pr-4 font-medium text-gray-900 dark:text-white">{{ $repayment->loan_id ?: '-' }}</td>
                                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">ZMW {{ number_format((float) ($repayment->receipt_amount ?? $repayment->payments ?? 0), 2) }}</td>
                                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">ZMW {{ number_format((float) ($repayment->paid_principal ?? 0), 2) }}</td>
                                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">ZMW {{ number_format((float) ($repayment->paid_interest ?? 0), 2) }}</td>
                                            <td class="py-3 text-gray-700 dark:text-gray-200">ZMW {{ number_format((float) ($repayment->balance ?? 0), 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="py-4 text-sm text-gray-500">No repayments found for this customer.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Status Timeline</h2>
                        <div class="mt-4 space-y-3">
                            @forelse ($statusEvents as $event)
                                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ $event->loan?->loan_id ?: $event->loan_id ?: 'Loan' }} | {{ $event->statusReason?->name ?? ucfirst((string) $event->action) }}
                                        </div>
                                        <div class="text-xs text-gray-500">{{ optional($event->effective_date)->format('d M Y') ?? '-' }}</div>
                                    </div>
                                    <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                        {{ $event->notes ?: ($event->performed_by_name ? 'Updated by ' . $event->performed_by_name : 'No notes recorded.') }}
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No status history recorded for this customer.</p>
                            @endforelse
                        </div>
                    </div>
                </section>
            </div>
        @elseif (blank($search))
            <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900">
                Search for a customer to open their full history.
            </div>
        @endif
    </div>
</x-filament::page>
