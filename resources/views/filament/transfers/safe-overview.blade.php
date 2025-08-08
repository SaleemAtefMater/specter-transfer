{{-- resources/views/filament/transfers/safe-overview.blade.php --}}
<div class="space-y-6">
    <div class="bg-gradient-to-r from-blue-50 to-blue-100 p-6 rounded-lg border border-blue-200">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-2xl font-bold text-blue-900">Total Safe Balance</h3>
                <p class="text-blue-700">Across all safe types</p>
            </div>
            <div class="text-right">
                <div class="text-4xl font-bold {{ $total_balance >= 0 ? 'text-green-700' : 'text-red-700' }}">
                    ${{ number_format($total_balance, 2) }}
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @forelse($safe_types as $safeType)
            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="font-semibold text-gray-900">{{ $safeType['name'] }}</h4>
                    <span class="text-sm text-gray-500">{{ ucfirst($safeType['type'] ?? 'N/A') }}</span>
                </div>

                <div class="text-2xl font-bold {{ $safeType['current_balance'] >= 0 ? 'text-green-600' : 'text-red-600' }} mb-1">
                    {{ $safeType['balance_formatted'] }}
                </div>

                @if($safeType['account_number'])
                    <div class="text-sm text-gray-600">
                        Account: {{ $safeType['account_number'] }}
                    </div>
                @endif
            </div>
        @empty
            <div class="col-span-full text-center py-8 text-gray-500">
                No active safe types found
            </div>
        @endforelse
    </div>
</div>
