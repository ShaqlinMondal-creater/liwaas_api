<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewsletterWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function build()
    {
        return $this->from('business.liwaas@gmail.com', 'Liwaas')
            ->replyTo('business.liwaas@gmail.com', 'Liwaas')
            ->subject('Welcome to Liwaas')
            ->view('emails.newsletter_welcome');
    }
}
