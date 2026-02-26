<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPlacedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */

    public $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    public function build()
    {
        return $this->from('business.liwaas@gmail.com', 'Liwaas')
            ->subject('Your Order Has Been Placed')
            ->view('emails.order_placed')
            ->with([
                'order' => $this->order,
                // 'dark_logo' => asset('logos/' . env('STORE_DARK_LOGO')),
                // 'light_logo' => asset('logos/' . env('STORE_LIGHT_LOGO')),
            ]);
    }

}
