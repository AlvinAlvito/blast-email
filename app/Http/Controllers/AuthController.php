<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const USERNAME = 'posi';

    private const PASSWORD = 'vitoganteng';

    public function showLogin(): View|RedirectResponse
    {
        if (session('admin_authenticated')) {
            return redirect()->route('admin.overview');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (
            hash_equals(self::USERNAME, $credentials['username']) &&
            hash_equals(self::PASSWORD, $credentials['password'])
        ) {
            $request->session()->regenerate();
            $request->session()->put('admin_authenticated', true);
            $request->session()->put('admin_username', self::USERNAME);

            return redirect()->intended(route('admin.overview'));
        }

        return back()
            ->withInput($request->only('username'))
            ->withErrors(['login' => 'Username atau password tidak sesuai.']);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['admin_authenticated', 'admin_username']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Anda sudah keluar dari dashboard.');
    }
}
