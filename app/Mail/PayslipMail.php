<?php

namespace App\Mail;

use App\Models\Payslip;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayslipMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payslip $payslip, protected string $pdf, protected string $filename) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Slip Gaji '.$this->payslip->register->period->name.' — '.config('payroll.company_name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.payslip');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, $this->filename)->withMime('application/pdf')];
    }
}
