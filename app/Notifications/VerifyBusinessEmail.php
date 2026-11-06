<?php

namespace App\Notifications;

use App\Models\VenueManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyBusinessEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public VenueManager $claim) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute('publican.claim.verify', now()->addDays(3), ['claim' => $this->claim->id]);

        return (new MailMessage)
            ->subject('Confirm your Beercheck venue claim')
            ->line("Someone (hopefully you) asked to manage {$this->claim->venue->name} on Beercheck on behalf of {$this->claim->business_name}.")
            ->action('Confirm business email', $url)
            ->line('After confirming, our team will review the claim. If this wasn\'t you, ignore this email.');
    }
}
