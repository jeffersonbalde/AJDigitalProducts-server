<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\ProductDownload;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $excelFilePath;

    /**
     * Create a new message instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->excelFilePath = $this->generateExcelFile($order);
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $this->order->loadMissing(['customer', 'items.product']);

        $email = $this->order->customer_id 
            ? $this->order->customer->email 
            : $this->order->guest_email;
        
        $customerName = $this->order->customer_id 
            ? $this->order->customer->name 
            : $this->order->guest_name;

        $downloadLinks = [];
        
        foreach ($this->order->items as $item) {
            // Find download token for this specific order item
            $download = ProductDownload::where('order_item_id', $item->id)->first();
            if ($download && $download->download_token) {
                $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
                $apiBaseUrl = env('APP_URL', 'http://localhost:8000');
                $orderPageUrl = "{$frontendUrl}/order/{$this->order->order_number}";
                $directDownloadUrl = "{$apiBaseUrl}/api/downloads/{$download->download_token}";
                $downloadLinks[] = [
                    'product_name' => $item->product_name,
                    'order_page_url' => $orderPageUrl,
                    'direct_download_url' => $directDownloadUrl,
                    'token' => $download->download_token,
                ];
            }
        }

        $mail = $this->subject('Order Confirmation - ' . $this->order->order_number)
            ->view('emails.order-confirmation', [
                'order' => $this->order,
                'customerName' => $customerName,
                'downloadLinks' => $downloadLinks,
            ])
            ->to($email);

        // Attach Excel file if it was generated
        if ($this->excelFilePath && file_exists($this->excelFilePath)) {
            $mail->attach($this->excelFilePath, [
                'as' => 'order_' . $this->order->order_number . '.csv',
                'mime' => 'text/csv',
            ]);
        }

        return $mail;
    }

    /**
     * Generate Excel file (CSV format - compatible with Excel)
     */
    private function generateExcelFile(Order $order): ?string
    {
        try {
            $order->load('items.product');
            
            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            $filename = 'order_' . $order->order_number . '_' . time() . '.csv';
            $filePath = $tempPath . '/' . $filename;

            // Open file for writing
            $file = fopen($filePath, 'w');
            
            // Add BOM for UTF-8 (helps Excel recognize encoding)
            fwrite($file, "\xEF\xBB\xBF");

            // Header row
            fputcsv($file, ['Order Number', 'Product Name', 'Quantity', 'Unit Price', 'Subtotal']);

            // Order items
            foreach ($order->items as $item) {
                fputcsv($file, [
                    $order->order_number,
                    $item->product_name,
                    $item->quantity,
                    '₱' . number_format($item->product_price, 2),
                    '₱' . number_format($item->subtotal, 2),
                ]);
            }

            // Empty row
            fputcsv($file, []);

            // Summary rows
            fputcsv($file, ['Subtotal:', '', '', '', '₱' . number_format($order->subtotal, 2)]);
            if ($order->tax_amount > 0) {
                fputcsv($file, ['Tax:', '', '', '', '₱' . number_format($order->tax_amount, 2)]);
            }
            if ($order->discount_amount > 0) {
                fputcsv($file, ['Discount:', '', '', '', '-₱' . number_format($order->discount_amount, 2)]);
            }
            fputcsv($file, ['TOTAL:', '', '', '', '₱' . number_format($order->total_amount, 2)]);

            fclose($file);

            return $filePath;
        } catch (\Exception $e) {
            \Log::error('Error generating Excel file: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Clean up temporary file after sending
     */
    public function __destruct()
    {
        if ($this->excelFilePath && file_exists($this->excelFilePath)) {
            @unlink($this->excelFilePath);
        }
    }
}

