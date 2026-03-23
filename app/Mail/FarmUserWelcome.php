<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FarmUserWelcome extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $newUser,
        public readonly string $farmRole,
        public readonly string $resetUrl,
        public readonly string $temporaryPassword = 'FarmConsul@1',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to FarmConsul – Your Account is Ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.farm-user-welcome',
            with: [
                'newUser' => $this->newUser,
                'farmRole' => $this->farmRole,
                'resetUrl' => $this->resetUrl,
                'temporaryPassword' => $this->temporaryPassword,
            ],
        );
    }
}

