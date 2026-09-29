<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DynamicNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $emailSubject;
    public string $emailBody;
    public ?string $actionUrl;
    public ?string $actionText;
    public ?string $recipientName;

    /**
     * Create a new message instance.
     */
    public function __construct(
        string $emailSubject,
        string $emailBody,
        ?string $actionUrl = null,
        ?string $actionText = null,
        ?string $recipientName = null
    ) {
        $this->emailSubject  = $emailSubject;
        $this->emailBody     = $emailBody;
        $this->actionUrl     = $actionUrl;
        $this->actionText    = $actionText ?: 'Lihat Detail';
        $this->recipientName = $recipientName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.dynamic_notification',
            with: [
                'subject'       => $this->emailSubject,
                'bodyContent'   => $this->emailBody,
                'actionUrl'     => $this->actionUrl,
                'actionText'    => $this->actionText,
                'recipientName' => $this->recipientName,
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
