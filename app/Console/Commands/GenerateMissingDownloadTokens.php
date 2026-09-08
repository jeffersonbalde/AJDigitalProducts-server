<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\ProductDownload;
use Illuminate\Support\Facades\Log;

class GenerateMissingDownloadTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:generate-missing-download-tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate download tokens for paid orders that are missing them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Searching for paid orders without download tokens...');

        // Find all paid orders
        $paidOrders = Order::where('payment_status', 'paid')
            ->with(['items.product', 'items.download'])
            ->get();

        $ordersProcessed = 0;
        $tokensGenerated = 0;

        foreach ($paidOrders as $order) {
            $orderHasMissingTokens = false;

            foreach ($order->items as $orderItem) {
                // Skip if product doesn't have a file
                if (!$orderItem->product || !$orderItem->product->file_path) {
                    continue;
                }

                // Check if download token already exists
                $existingDownload = ProductDownload::where('order_item_id', $orderItem->id)->first();
                
                if (!$existingDownload) {
                    $orderHasMissingTokens = true;

                    // Generate secure download token
                    $token = ProductDownload::generateToken();

                    // Set expires_at to NULL for unlimited/no expiration downloads
                    // This matches the user requirement for no download expiration

                    // Create download record
                    ProductDownload::create([
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'product_id' => $orderItem->product_id,
                        'customer_id' => $order->customer_id,
                        'guest_email' => $order->customer_id ? null : $order->guest_email,
                        'download_token' => $token,
                        'download_count' => 0,
                        'max_downloads' => 999999, // Effectively unlimited downloads
                        'expires_at' => null, // No expiration
                    ]);

                    $tokensGenerated++;
                    $this->line("  ✓ Generated token for order {$order->order_number}, item: {$orderItem->product_name}");
                }
            }

            if ($orderHasMissingTokens) {
                $ordersProcessed++;
            }
        }

        $this->info("Completed! Processed {$ordersProcessed} orders and generated {$tokensGenerated} download tokens.");
        
        return Command::SUCCESS;
    }
}
