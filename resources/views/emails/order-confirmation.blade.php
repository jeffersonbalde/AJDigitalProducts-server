<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #ffc107;
        }
        .header h1 {
            color: #000;
            margin: 0;
            font-size: 24px;
        }
        .success-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .order-info {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .order-info h2 {
            margin-top: 0;
            color: #000;
            font-size: 18px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #666;
        }
        .info-value {
            color: #000;
        }
        .products-section {
            margin: 30px 0;
        }
        .products-section h2 {
            color: #000;
            font-size: 18px;
            margin-bottom: 15px;
        }
        .product-item {
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        .product-name {
            font-weight: 600;
            color: #000;
            margin-bottom: 5px;
        }
        .download-button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #000;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin-top: 10px;
            text-align: center;
        }
        .download-button:hover {
            background-color: #333;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        .total-section {
            background-color: #000;
            color: #fff;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
            text-align: right;
        }
        .total-label {
            font-size: 14px;
            margin-bottom: 5px;
        }
        .total-amount {
            font-size: 24px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="success-icon">✅</div>
            <h1>Order Confirmation</h1>
            <p style="color: #666; margin: 10px 0 0 0;">Thank you for your purchase!</p>
        </div>

        <div class="order-info">
            <h2>Order Details</h2>
            <div class="info-row">
                <span class="info-label">Order Number:</span>
                <span class="info-value"><strong>{{ $order->order_number }}</strong></span>
            </div>
            <div class="info-row">
                <span class="info-label">Order Date:</span>
                <span class="info-value">{{ $order->created_at->format('F d, Y h:i A') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Payment Status:</span>
                <span class="info-value" style="color: #10b981; font-weight: 600;">Paid</span>
            </div>
            <div class="info-row">
                <span class="info-label">Payment Method:</span>
                <span class="info-value">{{ ucfirst($order->payment_method ?? 'PayMaya') }}</span>
            </div>
        </div>

        <div class="products-section">
            <h2>Your Products</h2>
            @foreach($order->items as $item)
            <div class="product-item">
                <div class="product-name">{{ $item->product_name }}</div>
                <div style="color: #666; font-size: 14px;">
                    Quantity: {{ $item->quantity }} × ₱{{ number_format($item->product_price, 2) }} = ₱{{ number_format($item->subtotal, 2) }}
                </div>
                @if(isset($downloadLinks) && count($downloadLinks) > 0)
                    @php
                        $downloadLink = collect($downloadLinks)->firstWhere('product_name', $item->product_name);
                    @endphp
                    @if($downloadLink)
                        <a href="{{ $downloadLink['direct_download_url'] }}" class="download-button" style="display: inline-block; padding: 12px 24px; background-color: #000; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; margin-top: 10px; text-align: center;">Download Product</a>
                    @endif
                @endif
            </div>
            @endforeach
        </div>

        <div class="total-section">
            <div class="total-label">Total Amount</div>
            <div class="total-amount">₱{{ number_format($order->total_amount, 2) }}</div>
        </div>

        <div style="margin-top: 30px; padding: 20px; background-color: #fff3cd; border-radius: 6px; border-left: 4px solid #ffc107;">
            <p style="margin: 0; color: #856404;">
                <strong>📎 Excel File Attached:</strong> A detailed order summary has been attached to this email. You can also download your products using the buttons above or visit your order page.
            </p>
        </div>

        <div class="footer">
            <p>If you have any questions, please contact our support team.</p>
            <p style="margin-top: 10px;">
                <a href="{{ env('FRONTEND_URL', 'http://localhost:5173') }}/order/{{ $order->order_number }}" style="color: #000; text-decoration: underline;">View Order Details</a>
            </p>
        </div>
    </div>
</body>
</html>

