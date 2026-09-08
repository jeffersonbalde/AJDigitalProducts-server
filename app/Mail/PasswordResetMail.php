<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public $resetToken;
    public $userName;
    public $resetUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(string $resetToken, string $userName, string $email)
    {
        $this->resetToken = $resetToken;
        $this->userName = $userName;
        
        // Build the reset URL - use frontend URL (React app), not backend URL
        // Default to localhost:5173 for development (Vite default port)
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $this->resetUrl = rtrim($frontendUrl, '/') . '/auth/reset-password?token=' . urlencode($resetToken) . '&email=' . urlencode($email);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Password Reset Request - AJ Creative Studio',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            with: [
                'resetToken' => $this->resetToken,
                'userName' => $this->userName,
                'resetUrl' => $this->resetUrl,
            ],
        );
    }
}

