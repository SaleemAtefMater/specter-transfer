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

{{-- resources/views/filament/transfers/balance-impact-detailed.blade.php --}}
<div class="space-y-6">
    {{-- Transfer Information --}}
    <div class="bg-gray-50 p-4 rounded-lg">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Transfer Details</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div><span class="font-medium text-gray-600">Transfer Number:</span> {{ $transfer->transfer_number }}</div>
            <div><span class="font-medium text-gray-600">Customer:</span> {{ $transfer->customer_name }}</div>
            <div><span class="font-medium text-gray-600">Phone:</span> {{ $transfer->phone_number }}</div>
            <div><span class="font-medium text-gray-600">Safe Type:</span> {{ $transfer->transferType->name ?? 'N/A' }}</div>
            <div><span class="font-medium text-gray-600">Status:</span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                    {{ ucfirst($transfer->status) }}
                </span>
            </div>
            <div><span class="font-medium text-gray-600">Created:</span> {{ $transfer->created_at->format('M j, Y g:i A') }}</div>
        </div>
    </div>

    {{-- Financial Breakdown --}}
    <div class="bg-blue-50 p-4 rounded-lg">
        <h3 class="text-lg font-semibold text-blue-900 mb-3">Financial Breakdown</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div><span class="font-medium text-blue-700">Sent Amount:</span> ${{ number_format($transfer->sent_amount, 2) }}</div>
            <div><span class="font-medium text-blue-700">Transfer Cost:</span> ${{ number_format($transfer->transfer_cost, 2) }}</div>
            <div><span class="font-medium text-blue-700">Customer Charge:</span> ${{ number_format($transfer->customer_price, 2) }}</div>
            <div><span class="font-medium text-blue-700">Receiver Amount:</span> ${{ number_format($transfer->receiver_net_amount, 2) }}</div>
        </div>

        <div class="mt-4 p-3 bg-white rounded border">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div><span class="font-medium text-blue-700">Expected Profit:</span>
                    <span class="font-bold {{ $transfer->profit >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        ${{ number_format($transfer->profit, 2) }}
                    </span>
                </div>
                <div><span class="font-medium text-blue-700">Profit Margin:</span>
                    <span class="font-bold">{{ number_format(($transfer->profit / $transfer->sent_amount) * 100, 2) }}%</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Safe Balance Impact --}}
    <div class="bg-green-50 p-4 rounded-lg">
        <h3 class="text-lg font-semibold text-green-900 mb-3">Safe Balance Impact</h3>

        <div class="mb-4">
            <span class="font-medium text-green-700">Current Safe Balance:</span>
            <span class="ml-2 text-xl font-bold">${{ number_format($safe_summary['current_balance'] ?? 0, 2) }}</span>
        </div>

        <div class="space-y-3">
            <div class="flex justify-between items-center p-3 bg-white rounded border">
                <div>
                    <span class="font-medium">Currently Added (Checked Status):</span>
                    <div class="text-sm text-gray-600">Sent Amount - Transfer Cost</div>
                </div>
                <span class="font-bold text-yellow-600">
                    +${{ number_format($transfer->sent_amount - $transfer->transfer_cost, 2) }}
                </span>
            </div>

            <div class="flex justify-between items-center p-3 bg-white rounded border">
                <div>
                    <span class="font-medium">After Delivery (Profit Only):</span>
                    <div class="text-sm text-gray-600">Final amount kept in safe</div>
                </div>
                <span class="font-bold {{ $transfer->profit >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $transfer->profit >= 0 ? '+' : '' }}${{ number_format($transfer->profit, 2) }}
                </span>
            </div>

            <div class="flex justify-between items-center p-3 bg-gray-100 rounded border-2 border-gray-300">
                <div>
                    <span class="font-medium">Net Balance Change on Delivery:</span>
                    <div class="text-sm text-gray-600">Difference between current and final</div>
                </div>
                <span class="font-bold text-lg {{ ($transfer->profit - ($transfer->sent_amount - $transfer->transfer_cost)) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ ($transfer->profit - ($transfer->sent_amount - $transfer->transfer_cost)) >= 0 ? '+' : '' }}${{ number_format($transfer->profit - ($transfer->sent_amount - $transfer->transfer_cost), 2) }}
                </span>
            </div>
        </div>
    </div>

    {{-- Transfer Summary for Safe Type --}}
    @if(isset($transfer_summary))
        <div class="bg-purple-50 p-4 rounded-lg">
            <h3 class="text-lg font-semibold text-purple-900 mb-3">{{ $transfer->transferType->name ?? 'Safe Type' }} Summary</h3>
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600">{{ $transfer_summary['total_transfers'] ?? 0 }}</div>
                    <div class="text-purple-700">Total Transfers</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">${{ number_format($transfer_summary['total_profit'] ?? 0, 2) }}</div>
                    <div class="text-purple-700">Total Profit</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-600">${{ number_format($transfer_summary['pending_amount'] ?? 0, 2) }}</div>
                    <div class="text-purple-700">Pending Amount</div>
                </div>
            </div>
        </div>
    @endif

    {{-- Balance Validation --}}
    @if(!$validation['is_valid'])
        <div class="bg-red-50 p-4 rounded-lg border-2 border-red-200">
            <h3 class="text-lg font-semibold text-red-900 mb-2">⚠️ Balance Warning</h3>
            <p class="text-red-800 mb-3">{{ $validation['message'] }}</p>
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div class="text-center">
                    <div class="text-lg font-bold text-red-600">${{ number_format($validation['current_balance'], 2) }}</div>
                    <div class="text-red-700">Current Balance</div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-bold text-red-600">{{ $validation['balance_change'] >= 0 ? '+' : '' }}${{ number_format($validation['balance_change'], 2) }}</div>
                    <div class="text-red-700">Balance Change</div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-bold text-red-600">${{ number_format($validation['projected_balance'], 2) }}</div>
                    <div class="text-red-700">Projected Balance</div>
                </div>
            </div>
        </div>
    @else
        <div class="bg-green-50 p-4 rounded-lg border-2 border-green-200">
            <h3 class="text-lg font-semibold text-green-900 mb-2">✅ Balance Validation Passed</h3>
            <p class="text-green-800">{{ $validation['message'] }}</p>
        </div>
    @endif
</div>

{{-- resources/views/filament/transfers/widgets/balance-impact-widget.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Balance Impact Analysis
        </x-slot>

        @if(isset($transfer))
            <div class="space-y-4">
                {{-- Current Status --}}
                <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                    <h4 class="font-semibold text-yellow-800 mb-2">Current Status: Checked</h4>
                    <div class="flex justify-between items-center">
                        <span class="text-yellow-700">Amount currently in safe:</span>
                        <span class="font-bold text-yellow-800 text-lg">
                        +${{ number_format($current_amount_in_safe ?? 0, 2) }}
                    </span>
                    </div>
                    <div class="text-sm text-yellow-600 mt-1">
                        (Sent Amount: ${{ number_format($transfer->sent_amount, 2) }} - Cost: ${{ number_format($transfer->transfer_cost, 2) }})
                    </div>
                </div>

                {{-- After Delivery --}}
                <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                    <h4 class="font-semibold text-green-800 mb-2">After Delivery: Final Amount</h4>
                    <div class="flex justify-between items-center">
                        <span class="text-green-700">Profit remaining in safe:</span>
                        <span class="font-bold text-green-800 text-lg">
                        {{ ($profit_after_delivery ?? 0) >= 0 ? '+' : '' }}${{ number_format($profit_after_delivery ?? 0, 2) }}
                    </span>
                    </div>
                    <div class="text-sm text-green-600 mt-1">
                        (Sent - Cost - Receiver: ${{ number_format($transfer->sent_amount, 2) }} - ${{ number_format($transfer->transfer_cost, 2) }} - ${{ number_format($transfer->receiver_net_amount, 2) }})
                    </div>
                </div>

                {{-- Net Change --}}
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <h4 class="font-semibold text-blue-800 mb-2">Net Change on Delivery</h4>
                    <div class="flex justify-between items-center">
                        <span class="text-blue-700">Balance will change by:</span>
                        <span class="font-bold text-blue-800 text-xl">
                        {{ ($balance_change_on_delivery ?? 0) >= 0 ? '+' : '' }}${{ number_format($balance_change_on_delivery ?? 0, 2) }}
                    </span>
                    </div>
                    <div class="text-sm text-blue-600 mt-1">
                        {{ ($balance_change_on_delivery ?? 0) < 0 ? 'Money will be paid out to receiver' : 'Additional profit will be added' }}
                    </div>
                </div>

                {{-- Safe Information --}}
                @if(isset($safe_summary))
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <h4 class="font-semibold text-gray-800 mb-2">{{ $safe_summary['safe_type']->name ?? 'Safe Type' }} - Current Balance</h4>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700">Current balance:</span>
                            <span class="font-bold text-gray-800 text-lg">
                        ${{ number_format($safe_summary['current_balance'] ?? 0, 2) }}
                    </span>
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-gray-700">Balance after delivery:</span>
                            <span class="font-bold {{ (($safe_summary['current_balance'] ?? 0) + ($balance_change_on_delivery ?? 0)) >= 0 ? 'text-green-600' : 'text-red-600' }} text-lg">
                        ${{ number_format(($safe_summary['current_balance'] ?? 0) + ($balance_change_on_delivery ?? 0), 2) }}
                    </span>
                        </div>
                    </div>
                @endif

                {{-- Validation Message --}}
                @if(isset($validation) && !$validation['is_valid'])
                    <div class="bg-red-50 p-4 rounded-lg border-2 border-red-300">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <span class="font-semibold text-red-800">Warning: {{ $validation['message'] }}</span>
                        </div>
                    </div>
                @endif
            </div>
        @else
            <div class="text-center py-8 text-gray-500">
                No transfer data available
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
