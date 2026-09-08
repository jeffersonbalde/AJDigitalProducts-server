# Email Configuration for .env File

## Gmail SMTP Configuration

Add these lines to your `server/.env` file:

```env
# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-16-character-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="AJ Creative Studio"
```

## Example Configuration

Replace with your actual Gmail credentials:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=ajcreativestudio@gmail.com
MAIL_PASSWORD=abcd efgh ijkl mnop
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=ajcreativestudio@gmail.com
MAIL_FROM_NAME="AJ Creative Studio"
```

## Important Notes

1. **MAIL_USERNAME**: Your full Gmail address (e.g., `yourname@gmail.com`)
2. **MAIL_PASSWORD**: The 16-character App Password from Google (remove spaces if any)
3. **MAIL_PORT**: 
   - `587` for TLS (recommended)
   - `465` for SSL (alternative)
4. **MAIL_ENCRYPTION**: 
   - `tls` for port 587
   - `ssl` for port 465

## After Configuration

1. Save the `.env` file
2. Clear Laravel config cache: `php artisan config:clear`
3. Test email using: `POST /api/test-email` with `{"email": "test@example.com"}`

## Security Reminder

⚠️ **Never commit your `.env` file to version control!**
- The `.env` file should be in `.gitignore`
- Use `.env.example` for sharing configuration structure

