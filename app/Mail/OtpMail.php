<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use SerializesModels;

    public $otp;
    public $user;

    public function __construct($otp, $user)
    {
        $this->otp = $otp;
        $this->user = $user;
    }

    public function build()
    {
        return $this->subject('Password Reset OTP - Liwaas')
                    ->view('emails.otp')
                    ->with([
                        'otp' => $this->otp,
                        'user' => $this->user,
                    ]);
    }
}
