<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $invoiceData;

    /**
     * Create a new message instance.
     *
     * @param  array  $invoiceData  Data invoice yang telah disiapkan oleh EmailNotificationService
     */
    public function __construct(array $invoiceData)
    {
        $this->invoiceData = $invoiceData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $bookingCode = $this->invoiceData['kode_booking'] ?? ('#' . ($this->invoiceData['id_booking'] ?? ''));
        return new Envelope(
            subject: "Invoice & Bukti Pelayanan {$bookingCode} - " . config('app.name', 'HomeCare'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: [
                'invoice' => $this->invoiceData,
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
