<div class="space-y-6">
    {{-- Transfer Information --}}
    <div class="bg-gray-50 p-4 rounded-lg">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Transfer Details</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="font-medium text-gray-600">Transfer Number:</span>
                <span class="ml-2">{{ $transfer->transfer_number }}</span>
            </div>
            <div>
                <span class="font-medium text-gray-600">Customer:</span>
                <span class="ml-2">{{ $transfer->customer_name }}</span>
            </div>
            <div>
                <span class="font-medium text-gray-600">Status:</span>
                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    @if($transfer->status === 'checked') bg-yellow-100 text-yellow-800
                    @elseif($transfer->status === 'delivered') bg-green-100 text-green-800
                    @elseif($transfer->status === 'canceled') bg-red-100 text-red-800
                    @endif">
                    {{ ucfirst($transfer->status) }}
                </span>
            </div>
            <div>
                <span class="font-medium text-gray-600">Safe Type:</span>
                <span class="ml-2">{{ $transfer->transferType->name ?? 'N/A' }}</span>
            </div>
        </div>
    </div>

    {{-- Financial Breakdown --}}
    <div class="bg-blue-50 p-4 rounded-lg">
        <h3 class="text-lg font-semibold text-blue-900 mb-3">Financial Breakdown</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="font-medium text-blue-700">Sent Amount:</span>
                <span class="ml-2">${{ number_format($transfer->sent_amount, 2) }}</span>
            </div>
            <div>
                <span class="font-medium text-blue-700">Transfer Cost:</span>
                <span class="ml-2">${{ number_format($transfer->transfer_cost, 2) }}</span>
            </div>
            <div>
                <span class="font-medium text-blue-700">Customer Charge:</span>
                <span class="ml-2">${{ number_format($transfer->customer_price, 2) }}</span>
            </div>
            <div>
                <span class="font-medium text-blue-700">Receiver Amount:</span>
                <span class="ml-2">${{ number_format($transfer->receiver_net_amount, 2) }}</span>
            </div>
        </div>
    </div>

    {{-- Safe Balance Impact --}}
    <div class="bg-green-50 p-4 rounded-lg">
        <h3 class="text-lg font-semibold text-green-900 mb-3">Safe Balance Impact</h3>

        {{-- Current Safe Balance --}}
        <div class="mb-4">
            <span class="font-medium text-green-700">Current Safe Balance:</span>
            <span class="ml-2 text-lg font-bold">${{ number_format($safe_summary['current_balance'] ?? 0, 2) }}</span>
        </div>

        {{-- Impact by Status --}}
        <div class="space-y-3">
            <div class="flex justify-between items-center p-3 bg-white rounded border">
                <div>
                    <span class="font-medium">When Checked (Check-in only):</span>
                    <div class="text-sm text-gray-600">Sent Amount - Transfer Cost</div>
                </div>
                <span class="font-bold text-yellow-600">
                    +${{ number_format($transfer->sent_amount - $transfer->transfer_cost, 2) }}
                </span>
            </div>

            <div class="flex justify-between items-center p-3 bg-white rounded border">
                <div>
                    <span class="font-medium">When Delivered (Profit only):</span>
                    <div class="text-sm text-gray-600">Sent - Cost - Receiver Amount</div>
                </div>
                <span class="font-bold {{ $transfer->profit >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $transfer->profit >= 0 ? '+' : '' }}${{ number_format($transfer->profit, 2) }}
                </span>
            </div>

            <div class="flex justify-between items-center p-3 bg-white rounded border">
                <div>
                    <span class="font-medium">If Canceled:</span>
                    <div class="text-sm text-gray-600">Reverses all previous additions</div>
                </div>
                <span class="font-bold text-red-600">
                    -${{ number_format($transfer->safe_amount, 2) }}
                </span>
            </div>
        </div>

        {{-- Current Status Impact --}}
        <div class="mt-4 p-3 bg-white rounded border-2 border-green-300">
            <div class="flex justify-between items-center">
                <span class="font-medium text-green-800">Current Status Impact:</span>
                <span class="font-bold text-lg {{ $transfer->safe_amount >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $transfer->safe_amount >= 0 ? '+' : '' }}${{ number_format($transfer->safe_amount, 2) }}
                </span>
            </div>
        </div>
    </div>

    {{-- Balance Validation --}}
    @if(!$validation['is_valid'])
        <div class="bg-red-50 p-4 rounded-lg border-2 border-red-200">
            <h3 class="text-lg font-semibold text-red-900 mb-2">⚠️ Balance Warning</h3>
            <p class="text-red-800">{{ $validation['message'] }}</p>
            <div class="mt-3 grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="font-medium text-red-700">Current Balance:</span>
                    <span class="ml-2">${{ number_format($validation['current_balance'], 2) }}</span>
                </div>
                <div>
                    <span class="font-medium text-red-700">Balance Change:</span>
                    <span class="ml-2">{{ $validation['balance_change'] >= 0 ? '+' : '' }}${{ number_format($validation['balance_change'], 2) }}</span>
                </div>
                <div class="col-span-2">
                    <span class="font-medium text-red-700">Projected Balance:</span>
                    <span class="ml-2 font-bold">${{ number_format($validation['projected_balance'], 2) }}</span>
                </div>
            </div>
        </div>
    @else
        <div class="bg-green-50 p-4 rounded-lg border-2 border-green-200">
            <h3 class="text-lg font-semibold text-green-900 mb-2">✅ Balance Valid</h3>
            <p class="text-green-800">{{ $validation['message'] }}</p>
        </div>
    @endif

    {{-- Transfer Summary for Safe Type --}}
    @if(isset($safe_summary['transfer_summary']))
        <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Transfer Summary for This Safe Type</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="font-medium text-gray-600">Total Transfers:</span>
                    <span class="ml-2">{{ $safe_summary['transfer_summary']['total_transfers'] }}</span>
                </div>
                <div>
                    <span class="font-medium text-gray-600">Delivered:</span>
                    <span class="ml-2">{{ $safe_summary['transfer_summary']['delivered_transfers'] }}</span>
                </div>
                <div>
                    <span class="font-medium text-gray-600">Total Profit:</span>
                    <span class="ml-2">${{ number_format($safe_summary['transfer_summary']['total_profit'], 2) }}</span>
                </div>
                <div>
                    <span class="font-medium text-gray-600">Pending Amount:</span>
                    <span class="ml-2">${{ number_format($safe_summary['transfer_summary']['pending_amount'], 2) }}</span>
                </div>
            </div>
        </div>
    @endif
</div>
