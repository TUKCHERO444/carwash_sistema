<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CuentaGoogleMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nombre,
        public string $titulo,
        public string $cuerpo,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->titulo.' — Carwash El Chinito',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cuenta.google',
        );
    }
}
