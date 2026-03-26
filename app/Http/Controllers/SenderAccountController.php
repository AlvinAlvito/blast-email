<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\SenderAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class SenderAccountController extends Controller
{
    public function edit(SenderAccount $senderAccount): View
    {
        return view('admin.senders-edit', [
            'sender' => $senderAccount,
            'stats' => [
                'contacts' => Contact::count(),
                'emailable_contacts' => Contact::whereNotNull('email')->where('email_opt_out', false)->count(),
                'senders' => SenderAccount::count(),
                'campaigns' => Campaign::count(),
                'queued_recipients' => CampaignRecipient::where('status', 'queued')->count(),
                'sent_recipients' => CampaignRecipient::where('status', 'sent')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request, true);

        SenderAccount::create($data);

        return redirect()->route('admin.senders')->with('status', 'Sender account ditambahkan.');
    }

    public function update(Request $request, SenderAccount $senderAccount): RedirectResponse
    {
        $data = $this->validatePayload($request, false);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $senderAccount->update($data);

        return redirect()->route('admin.senders')->with('status', 'Sender account diperbarui.');
    }

    public function destroy(SenderAccount $senderAccount): RedirectResponse
    {
        $senderAccount->delete();

        return redirect()->route('admin.senders')->with('status', 'Sender account dihapus.');
    }

    public function toggle(SenderAccount $senderAccount): RedirectResponse
    {
        $senderAccount->update([
            'is_active' => ! $senderAccount->is_active,
        ]);

        return redirect()->route('admin.senders')->with('status', 'Status sender account diperbarui.');
    }

    public function test(SenderAccount $senderAccount): RedirectResponse
    {
        try {
            $transport = new EsmtpTransport(
                $senderAccount->host,
                $senderAccount->port,
                $senderAccount->encryption === 'ssl'
            );

            if ($senderAccount->encryption === 'tls') {
                $transport->setTls(true);
            }

            $transport->setUsername($senderAccount->username);
            $transport->setPassword($senderAccount->password);
            $transport->start();
            $transport->stop();

            return redirect()->route('admin.senders')->with('status', 'Koneksi SMTP berhasil.');
        } catch (\Throwable $exception) {
            return redirect()->route('admin.senders')->withErrors([
                'sender_test' => 'Koneksi SMTP gagal: '.$exception->getMessage(),
            ]);
        }
    }

    protected function validatePayload(Request $request, bool $passwordRequired): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer'],
            'encryption' => ['nullable', 'string', 'max:10'],
            'username' => ['required', 'string', 'max:255'],
            'password' => [$passwordRequired ? 'required' : 'nullable', 'string'],
            'from_address' => ['required', 'email'],
            'from_name' => ['required', 'string', 'max:255'],
            'reply_to_address' => ['nullable', 'email'],
            'daily_limit' => ['required', 'integer', 'min:1'],
            'hourly_limit' => ['required', 'integer', 'min:1'],
        ]);
    }
}
