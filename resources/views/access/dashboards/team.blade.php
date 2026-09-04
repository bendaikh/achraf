@extends('layouts.with-sidebar')

@section('title', 'Performance équipe commerciale')
@section('sidebar_page_title', 'Gestion ventes')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-start">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Équipe commerciale</h1>
                <p class="text-gray-500 mt-1">Vue responsable / admin — salariés et freelances.</p>
            </div>
            <form method="GET" class="flex flex-wrap gap-2">
                <input type="date" name="date_from" value="{{ $from }}" class="px-3 py-2 border rounded-lg text-sm">
                <input type="date" name="date_to" value="{{ $to }}" class="px-3 py-2 border rounded-lg text-sm">
                <button class="px-4 py-2 bg-[#fdb819] text-white rounded-lg font-semibold text-sm">Filtrer</button>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Commercial</th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Type</th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Commandes</th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">CA</th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Livré</th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Encaissé</th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Commission</th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-gray-500"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($rows as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium">{{ $row['collaborator']->fullName() }}</td>
                                <td class="px-4 py-3 text-sm">{{ $row['type'] }}</td>
                                <td class="px-4 py-3 text-sm">{{ $row['orders'] }}</td>
                                <td class="px-4 py-3 text-sm">{{ number_format((float)$row['ca'], 2, ',', ' ') }}</td>
                                <td class="px-4 py-3 text-sm">{{ number_format((float)$row['delivered'], 2, ',', ' ') }}</td>
                                <td class="px-4 py-3 text-sm">{{ number_format((float)$row['collected'], 2, ',', ' ') }}</td>
                                <td class="px-4 py-3 text-sm font-medium">{{ number_format((float)$row['commission'], 2, ',', ' ') }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <a href="{{ route('access.collaborators.show', $row['collaborator']) }}" class="text-blue-600 hover:text-blue-900">Fiche</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">Aucun commercial.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
@endsection
