<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Template Mapping</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="max-w-6xl mx-auto px-6 py-10">
        <div class="flex items-center justify-between mb-8">
            <div>
                <p class="text-sm font-semibold text-indigo-600">Template mapping</p>
                <h1 class="text-3xl font-semibold tracking-tight mt-2">Map template labels to staff fields</h1>
                <p class="text-slate-600 mt-2">Company: <span class="font-medium">{{ $company->name }}</span> (ID {{ $company->id }})</p>
            </div>
            <a href="{{ url('/company-templates?company=' . $company->id) }}"
                class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Back to tester</a>
        </div>

        @if (session('status'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <div class="mb-4">
                <p class="text-sm text-slate-600">Detected orientation: <span class="font-medium">{{ ucfirst($templateMeta['orientation']) }}</span></p>
            </div>

            @if (($company->template_type ?? 'excel') === 'pdf')
                <form method="POST" action="{{ route('companies.template.mapping.store', $company) }}">
                    @csrf
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <button type="button" id="add-mapping" class="rounded bg-indigo-600 text-white px-3 py-1 text-sm">Add mapping</button>
                        </div>

                        <div id="mappings-list" class="space-y-2">
                            @php
                                $existing = $templateMeta['mapping']['pdf'] ?? [];
                            @endphp
                            @foreach ($existing as $idx => $m)
                                <div class="p-3 border rounded flex gap-2 items-center">
                                    <input type="text" name="pdf_mappings[{{ $idx }}][label]" value="{{ $m['label'] ?? '' }}" placeholder="Label" class="border px-2 py-1 rounded" />
                                    <input type="number" step="1" name="pdf_mappings[{{ $idx }}][page]" value="{{ $m['page'] ?? 1 }}" class="border px-2 py-1 rounded w-20" />
                                    <input type="number" step="0.1" name="pdf_mappings[{{ $idx }}][x]" value="{{ $m['x'] ?? 10 }}" class="border px-2 py-1 rounded w-24" placeholder="X mm" />
                                    <input type="number" step="0.1" name="pdf_mappings[{{ $idx }}][y]" value="{{ $m['y'] ?? 10 }}" class="border px-2 py-1 rounded w-24" placeholder="Y mm" />
                                    <input type="number" step="1" name="pdf_mappings[{{ $idx }}][size]" value="{{ $m['size'] ?? 10 }}" class="border px-2 py-1 rounded w-20" placeholder="Size" />
                                    <select name="pdf_mappings[{{ $idx }}][field]" class="border px-2 py-1 rounded">
                                        <option value="">Select field</option>
                                        @foreach ($fields as $field)
                                            <option value="{{ $field }}" @selected(($m['field'] ?? '') === $field)>{{ $field }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="remove-mapping text-sm text-rose-600">Remove</button>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="rounded bg-indigo-600 text-white px-4 py-2">Save mapping</button>
                        </div>
                    </div>
                </form>

                <script>
                    document.getElementById('add-mapping').addEventListener('click', function() {
                        const list = document.getElementById('mappings-list');
                        const idx = list.children.length;
                        const div = document.createElement('div');
                        div.className = 'p-3 border rounded flex gap-2 items-center';
                        div.innerHTML = `
                            <input type="text" name="pdf_mappings[${idx}][label]" placeholder="Label" class="border px-2 py-1 rounded" />
                            <input type="number" step="1" name="pdf_mappings[${idx}][page]" value="1" class="border px-2 py-1 rounded w-20" />
                            <input type="number" step="0.1" name="pdf_mappings[${idx}][x]" value="10" class="border px-2 py-1 rounded w-24" placeholder="X mm" />
                            <input type="number" step="0.1" name="pdf_mappings[${idx}][y]" value="10" class="border px-2 py-1 rounded w-24" placeholder="Y mm" />
                            <input type="number" step="1" name="pdf_mappings[${idx}][size]" value="10" class="border px-2 py-1 rounded w-20" placeholder="Size" />
                            <select name="pdf_mappings[${idx}][field]" class="border px-2 py-1 rounded">
                                <option value="">Select field</option>
                                @foreach ($fields as $field)
                                    <option value="{{ $field }}">{{ $field }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="remove-mapping text-sm text-rose-600">Remove</button>
                        `;
                        list.appendChild(div);
                        div.querySelector('.remove-mapping').addEventListener('click', () => div.remove());
                    });
                    document.querySelectorAll('.remove-mapping').forEach(btn => btn.addEventListener('click', function(){ this.closest('div').remove(); }));
                </script>
            @else
                <form method="POST" action="{{ route('companies.template.mapping.store', $company) }}">
                    @csrf
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-slate-500">
                                    <th class="px-3 py-2">Label</th>
                                    <th class="px-3 py-2">Position</th>
                                    <th class="px-3 py-2">Mapped field</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach ($templateMeta['labels'] as $label)
                                    <tr>
                                        <td class="px-3 py-2 font-medium text-slate-800">{{ $label['value'] }}</td>
                                        <td class="px-3 py-2 text-slate-500">R{{ $label['row'] }}C{{ $label['col'] }}</td>
                                        <td class="px-3 py-2">
                                            <select name="mapping[{{ $label['key'] }}]"
                                                class="w-full rounded-lg border border-slate-300 px-2 py-2">
                                                <option value="">Skip</option>
                                                @foreach ($fields as $field)
                                                    <option value="{{ $field }}"
                                                        @selected(($templateMeta['mapping'][$label['key']] ?? $label['resolved']) === $field)>
                                                        {{ $field }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 flex items-center gap-3">
                        <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-white font-medium shadow-sm hover:bg-indigo-500">
                            Save mapping
                        </button>
                        <span class="text-sm text-slate-500">Any skipped labels will be ignored.</span>
                    </div>
                </form>
            @endif
        </div>
    </div>
</body>
</html>
