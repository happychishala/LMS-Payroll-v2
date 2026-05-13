<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employer CSV Report</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
    <main class="mx-auto max-w-3xl p-6">
        <div class="rounded-xl bg-white p-6 shadow">
            <h1 class="text-2xl font-semibold">Employer CSV Report</h1>
            <p class="mt-1 text-sm text-gray-600">Choose a loan name and instalment month, then download the CSV.</p>

            <form method="POST" action="{{ route('reports.employer_csv.export') }}" class="mt-6 space-y-6">
                @csrf

                <div class="space-y-2">
                    <label for="loan_name" class="block text-sm font-medium">Loan Name</label>
                    <select id="loan_name" name="loan_name" required class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Select Loan Name</option>
                        @foreach ($loanNames as $name)
                            <option value="{{ $name }}" @selected(old('loan_name') === $name)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('loan_name')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label for="month" class="block text-sm font-medium">Instalment Month</label>
                    <input type="month" id="month" name="month" value="{{ old('month', now()->format('Y-m')) }}" required class="w-full rounded-md border-gray-300 shadow-sm" />
                    @error('month')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <label for="start_date" class="block text-sm font-medium">Disbursement Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="{{ old('start_date') }}" class="w-full rounded-md border-gray-300 shadow-sm" />
                        @error('start_date')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label for="end_date" class="block text-sm font-medium">Disbursement End Date</label>
                        <input type="date" id="end_date" name="end_date" value="{{ old('end_date') }}" class="w-full rounded-md border-gray-300 shadow-sm" />
                        @error('end_date')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <button type="submit" class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">
                    Download CSV
                </button>
            </form>
        </div>
    </main>
</body>
</html>
