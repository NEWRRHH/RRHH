<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Monthly attendance summary for the employee, with the PDF attached
 * (read from FILES_DISK when the queue sends it).
 */
class AttendanceSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $employeeName,
        public string $companyName,
        public string $monthLabel,
        public string $disk,
        public string $path,
        public string $fileName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Tu resumen de asistencia de {$this->monthLabel}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.attendance-summary');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk($this->disk, $this->path)->as($this->fileName)->withMime('application/pdf'),
        ];
    }
}
