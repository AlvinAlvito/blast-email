<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CampaignEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $personalizedSubject;
    public string $personalizedBody;

    public function __construct(
        public Campaign $campaign,
        public Contact $contact
    ) {
        $name = $this->contact->name ?: 'Peserta POSI';
        $this->personalizedSubject = str_replace('{{nama}}', $name, $this->campaign->subject ?? $this->campaign->name);
        $this->personalizedBody = str_replace('{{nama}}', $name, $this->campaign->body);
    }

    public function build(): self
    {
        return $this->subject($this->personalizedSubject)
            ->view('emails.campaign')
            ->with([
                'personalizedSubject' => $this->personalizedSubject,
                'personalizedBody' => $this->personalizedBody,
            ]);
    }
}
