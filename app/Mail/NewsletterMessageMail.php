<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewsletterMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $body,
    ) {
    }

    public function build()
    {
        return $this->from('business.liwaas@gmail.com', 'Liwaas')
            ->replyTo('business.liwaas@gmail.com', 'Liwaas')
            ->subject($this->mailSubject)
            ->view('emails.newsletter_message')
            ->with([
                'body' => $this->body,
            ]);
    }
}
