<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;
    public string $userType;

    /**
     * Create a new message instance.
     */
    public function __construct(string $otp, string $userType = 'user')
    {
        $this->otp = $otp;
        $this->userType = $userType;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $portal = ($this->userType === 'admin') ? 'Portal Admin' : 'Portal Pasien & Nakes';
        return new Envelope(
            subject: "Kode OTP Reset Password - {$portal}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.reset_password_otp',
            with: [
                'otp' => $this->otp,
                'portalName' => ($this->userType === 'admin') ? 'Portal Admin' : 'Portal Pasien / Nakes',
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
