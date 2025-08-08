<?php

namespace App\Models;

use App\Services\SafeBalanceService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Transfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'transfers';

    protected $fillable = [
        'transfer_number',
        'transfer_type_id',
        'customer_name',
        'phone_number',
        'sent_amount',
        'transfer_cost',
        'customer_price',
        'receiver_net_amount',
        'status',
        'delivery_safe_type_id',
        'delivery_amount',
        'delivery_notes',
        'delivered_at',
        'notes',
        'transfer_photo',
        'total_delivered_amount', // Track cumulative delivery
        'remaining_amount',       // Track what's left
    ];

    protected $casts = [
        'sent_amount' => 'decimal:2',
        'transfer_cost' => 'decimal:2',
        'customer_price' => 'decimal:2',
        'receiver_net_amount' => 'decimal:2',
        'delivery_amount' => 'decimal:2',
        'delivered_at' => 'datetime',
        'total_delivered_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    // Flag to prevent infinite loops
    public $skipBalanceEvents = false;

    public function transferType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SafeType::class, 'transfer_type_id', 'id');
    }

    public function deliverySafeType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SafeType::class, 'delivery_safe_type_id', 'id');
    }

    // Calculate profit based on status and delivery
    public function getProfitAttribute(): float
    {
        $sentAmount = $this->sent_amount ?? 0;
        $transferCost = $this->transfer_cost ?? 0;
        $deliveryAmount = $this->delivery_amount ?? $this->receiver_net_amount ?? 0;

        if ($this->status === "delivered") {
            return ($sentAmount - $transferCost) - $deliveryAmount;
        }

        return 0;
    }

    // Calculate amount that should be in the transfer type safe
    public function getTransferSafeAmountAttribute(): float
    {
        $sentAmount = $this->sent_amount ?? 0;
        $transferCost = $this->transfer_cost ?? 0;

        switch ($this->status) {
            case 'pending_verification':
            case 'checked':
                return $sentAmount - $transferCost;
            case 'delivered':
            case 'canceled':
                return 0;
            default:
                return 0;
        }
    }

    // Check if transfer can be delivered
    public function canBeDelivered(): bool
    {
        return in_array($this->status, ['pending_verification', 'checked']);
    }

    // Check if transfer was delivered
    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    // Check if transfer is pending
    public function isPending(): bool
    {
        return in_array($this->status, ['pending_verification', 'checked']);
    }

    /**
     * Deliver this transfer safely with proper transaction handling
     */
    /**
     * Deliver this transfer safely with proper transaction handling and partial delivery support
     */
    public function deliverSafely(int $deliverySafeTypeId, float $deliveryAmount, string $deliveryNotes = null): array
    {
        try {
            return DB::transaction(function () use ($deliverySafeTypeId, $deliveryAmount, $deliveryNotes) {
                Log::info("Starting delivery for transfer {$this->id}", [
                    'transfer_id' => $this->id,
                    'delivery_safe_type_id' => $deliverySafeTypeId,
                    'delivery_amount' => $deliveryAmount,
                    'current_status' => $this->status,
                    'total_delivered_so_far' => $this->total_delivered_amount ?? 0
                ]);

                // Validate transfer can be delivered
                if (!in_array($this->status, ['pending_verification', 'checked', 'partially_delivered'])) {
                    return [
                        'success' => false,
                        'message' => "Transfer cannot be delivered. Current status: {$this->status}"
                    ];
                }

                // Validate delivery amount
                if ($deliveryAmount <= 0) {
                    return [
                        'success' => false,
                        'message' => "Delivery amount must be greater than zero"
                    ];
                }

                // Calculate delivery totals
                $currentTotalDelivered = $this->total_delivered_amount ?? 0;
                $newTotalDelivered = $currentTotalDelivered + $deliveryAmount;
                $expectedTotal = $this->receiver_net_amount ?? 0;
                $remainingAmount = $expectedTotal - $newTotalDelivered;

                // Validate we don't over-deliver
                if ($newTotalDelivered > $expectedTotal + 0.01) { // Allow 1 cent tolerance
                    return [
                        'success' => false,
                        'message' => "Cannot deliver \${$deliveryAmount}. Only \${$remainingAmount} remaining to deliver."
                    ];
                }

                // Validate delivery safe has sufficient balance
                if (!SafeBalanceService::hasSufficientBalance($deliverySafeTypeId, $deliveryAmount)) {
                    $currentBalance = SafeBalanceService::getSafeBalance($deliverySafeTypeId);
                    return [
                        'success' => false,
                        'message' => "Insufficient balance. Current: $" . number_format($currentBalance, 2) . ", Required: $" . number_format($deliveryAmount, 2)
                    ];
                }

                // Get safe names for logging
                $transferSafe = SafeType::find($this->transfer_type_id);
                $deliverySafe = SafeType::find($deliverySafeTypeId);

                if (!$transferSafe || !$deliverySafe) {
                    return [
                        'success' => false,
                        'message' => "Invalid safe type selected"
                    ];
                }

                // Store original values
                $transferAmount = $this->sent_amount - $this->transfer_cost;
                $isFirstDelivery = $currentTotalDelivered == 0;

                // Set flag to skip model events during this operation
                $this->skipBalanceEvents = true;

                // Step 1: Remove balance from transfer type safe (only on first delivery)
                if ($isFirstDelivery && $transferAmount > 0) {
                    SafeBalanceService::updateSafeBalance($this->transfer_type_id, $transferAmount, 'subtract');
                    SafeBalanceService::createBalanceTransaction(
                        $this->transfer_type_id,
                        'transfer_out',
                        $transferAmount,
                        "Transfer delivery started - removed from {$transferSafe->name} for {$this->transfer_number}",
                        'App\Models\Transfer',
                        $this->id
                    );
                    Log::info("Removed {$transferAmount} from transfer type safe {$this->transfer_type_id}");
                }

                // Step 2: Deduct delivery amount from delivery safe
                SafeBalanceService::updateSafeBalance($deliverySafeTypeId, $deliveryAmount, 'subtract');
                SafeBalanceService::createBalanceTransaction(
                    $deliverySafeTypeId,
                    'transfer_out',
                    $deliveryAmount,
                    "Delivery payment for {$this->transfer_number} to {$this->customer_name} (" .
                    ($remainingAmount <= 0.01 ? 'Final' : 'Partial') . " payment)",
                    'App\Models\Transfer',
                    $this->id
                );
                Log::info("Deducted {$deliveryAmount} from delivery safe {$deliverySafeTypeId}");

                // Step 3: Determine new status
                $isCompleteDelivery = $remainingAmount <= 0.01; // 1 cent tolerance
                $newStatus = $isCompleteDelivery ? 'delivered' : 'partially_delivered';

                // Step 4: Update transfer record (without triggering events)
                $this->timestamps = false; // Temporarily disable automatic timestamps

                $updateData = [
                    'status' => $newStatus,
                    'total_delivered_amount' => $newTotalDelivered,
                    'remaining_amount' => max(0, $remainingAmount),
                    'updated_at' => now()
                ];

                // Update delivery info (for the latest delivery)
                $updateData['delivery_safe_type_id'] = $deliverySafeTypeId;
                $updateData['delivery_amount'] = $deliveryAmount;

                // Append to delivery notes (keep history)
                $existingNotes = $this->delivery_notes;
                if ($deliveryNotes) {
                    $newNote = date('Y-m-d H:i:s') . " - \${$deliveryAmount} via {$deliverySafe->name}: {$deliveryNotes}";
                    $updateData['delivery_notes'] = $existingNotes ? $existingNotes . "\n" . $newNote : $newNote;
                }

                // Set delivered_at only when fully completed
                if ($isCompleteDelivery) {
                    $updateData['delivered_at'] = now();
                }

                $this->update($updateData);
                $this->timestamps = true; // Re-enable timestamps

                // Reset flag
                $this->skipBalanceEvents = false;

                // Calculate final profit (only meaningful when fully delivered)
                $totalProfit = $isCompleteDelivery ? ($transferAmount - $newTotalDelivered) : 0;

                $resultMessage = $isCompleteDelivery
                    ? "Transfer fully delivered! Total profit: $" . number_format($totalProfit, 2)
                    : "Partial delivery completed. Remaining: $" . number_format($remainingAmount, 2) . " to deliver";

                Log::info("Transfer delivery completed", [
                    'transfer_id' => $this->id,
                    'new_status' => $newStatus,
                    'delivery_amount' => $deliveryAmount,
                    'total_delivered' => $newTotalDelivered,
                    'remaining_amount' => $remainingAmount,
                    'is_complete' => $isCompleteDelivery,
                    'total_profit' => $totalProfit
                ]);

                return [
                    'success' => true,
                    'message' => $resultMessage,
                    'status' => $newStatus,
                    'is_complete' => $isCompleteDelivery,
                    'delivery_amount' => $deliveryAmount,
                    'total_delivered' => $newTotalDelivered,
                    'remaining_amount' => $remainingAmount,
                    'profit' => $totalProfit,
                    'transfer_amount' => $transferAmount
                ];
            });
        } catch (\Exception $e) {
            Log::error("Transfer delivery failed", [
                'transfer_id' => $this->id,
                'delivery_safe_type_id' => $deliverySafeTypeId,
                'delivery_amount' => $deliveryAmount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => "Transfer delivery failed: " . $e->getMessage()
            ];
        }
    }
    protected static function boot()
    {
        parent::boot();

        // Generate unique transfer number
        static::creating(function ($transfer) {
            $transfer->transfer_number = 'TR' . date('Y') . str_pad(
                    Transfer::whereYear('created_at', date('Y'))->count() + 1,
                    6,
                    '0',
                    STR_PAD_LEFT
                );
        });

        // Handle safe balance updates on create - SIMPLIFIED
        static::created(function ($transfer) {
            if ($transfer->skipBalanceEvents) {
                return;
            }

            Log::info("Transfer created, updating safe balance", [
                'transfer_id' => $transfer->id,
                'status' => $transfer->status,
                'amount' => $transfer->sent_amount - $transfer->transfer_cost
            ]);

            if (in_array($transfer->status, ['pending_verification', 'checked'])) {
                $amount = $transfer->sent_amount - $transfer->transfer_cost;
                if ($amount > 0) {
                    SafeBalanceService::updateSafeBalance($transfer->transfer_type_id, $amount, 'add');
                    SafeBalanceService::createBalanceTransaction(
                        $transfer->transfer_type_id,
                        'transfer_in',
                        $amount,
                        "Transfer received - {$transfer->transfer_number} from {$transfer->customer_name}",
                        'App\Models\Transfer',
                        $transfer->id
                    );
                }
            }
        });

        // REMOVED the complex updating/updated events that were causing infinite loops
        // Status changes should be handled through the deliverSafely() method
    }
}
