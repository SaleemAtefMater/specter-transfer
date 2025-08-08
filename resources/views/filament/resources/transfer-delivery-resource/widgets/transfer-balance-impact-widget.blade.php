{{-- resources/views/filament/resources/transfer-delivery-resource/widgets/transfer-balance-impact-widget.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Balance Impact Analysis
        </x-slot>

        @if(isset($transfer) && $transfer)
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
                @elseif(isset($validation))
                    <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="font-semibold text-green-800">✅ {{ $validation['message'] }}</span>
                        </div>
                    </div>
                @endif
            </div>
        @else
            <div class="text-center py-8 text-gray-500">
                @if(isset($error))
                    <p class="text-red-500">{{ $error }}</p>
                @else
                    <p>No transfer data available</p>
                @endif
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
