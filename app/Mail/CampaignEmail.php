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
        $replacements = [
            '{{nama}}' => $this->contact->name ?: 'Peserta POSI',
            '{{sekolah}}' => $this->contact->school ?: '-',
            '{{bidang}}' => $this->contact->field ?: '-',
            '{{peserta}}' => $this->contact->participant_no ?: '-',
            '{{link}}' => $this->contact->participant_card_link ?: '-',
        ];

        $this->personalizedSubject = strtr($this->campaign->subject ?? $this->campaign->name, $replacements);
        $this->personalizedBody = strtr($this->campaign->body, $replacements);
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
