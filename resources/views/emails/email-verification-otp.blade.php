<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #ffc107;
            margin-bottom: 10px;
        }
        .otp-box {
            background-color: #f8f9fa;
            border: 2px dashed #ffc107;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 30px 0;
        }
        .otp-code {
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 8px;
            color: #333;
            font-family: 'Courier New', monospace;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">AJ Creative Studio</div>
            <h2>Email Verification</h2>
        </div>

        <p>Hello {{ $userName }},</p>

        <p>Thank you for registering with AJ Creative Studio! To complete your registration, please verify your email address using the verification code below:</p>

        <div class="otp-box">
            <div style="margin-bottom: 10px; color: #666;">Your verification code is:</div>
            <div class="otp-code">{{ $otp }}</div>
        </div>

        <div class="warning">
            <strong>⚠️ Important:</strong> This code will expire in 15 minutes. Please do not share this code with anyone.
        </div>

        <p>If you didn't create an account with AJ Creative Studio, please ignore this email.</p>

        <p>Best regards,<br>
        <strong>AJ Creative Studio Team</strong></p>

        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; {{ date('Y') }} AJ Creative Studio. All rights reserved.</p>
        </div>
    </div>
</body>
</html>

