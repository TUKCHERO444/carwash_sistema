<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CuentaRecuperacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nombre,
        public string $urlRecuperacion,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recupera tu acceso — Carwash El Chinito',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cuenta.recuperacion',
        );
    }
}
