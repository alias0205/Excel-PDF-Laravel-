<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Companies</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="max-w-5xl mx-auto px-6 py-10">
        <div class="mb-8">
            <p class="text-sm font-semibold text-indigo-600">Companies</p>
            <h1 class="text-3xl font-semibold tracking-tight mt-2">Create and select companies</h1>
            <p class="text-slate-600 mt-2">Create a company, then jump to the template tester.</p>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-lg font-semibold">Create company</h2>
                <form method="POST" action="{{ route('companies.store') }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label for="companyName" class="block text-sm font-medium text-slate-700">Company name</label>
                        <input id="companyName" name="name" type="text" required
                            class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        @error('name')
                            <p class="text-sm text-rose-600 mt-2">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-white font-medium shadow-sm hover:bg-indigo-500">
                        Create company
                    </button>
                </form>

                @if (session('status'))
                    <p class="mt-4 text-sm text-emerald-600">{{ session('status') }}</p>
                @endif
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-lg font-semibold">Existing companies</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($companies as $company)
                        <div class="flex flex-col gap-2 rounded-lg border border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-medium">{{ $company->name }}</p>
                                <p class="text-xs text-slate-500">ID: {{ $company->id }}</p>
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                                <form method="POST" action="{{ route('companies.generate-staff', $company) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium rounded bg-emerald-100 text-emerald-700 px-3 py-1 hover:bg-emerald-200">Generate staff</button>
                                </form>
                                <a href="{{ route('companies.template.mapping', $company) }}"
                                    class="text-sm font-medium text-slate-600 hover:text-slate-500">
                                    Map template
                                </a>
                                <a href="{{ url('/company-templates?company=' . $company->id) }}"
                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                                    Test template
                                </a>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-600">No companies yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</body>
</html>
