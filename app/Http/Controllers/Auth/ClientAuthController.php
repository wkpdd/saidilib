<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Wilaya;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class ClientAuthController extends Controller
{
    public function showRegister()
    {
        if (Auth::guard('client')->check()) {
            return redirect()->route('account.index');
        }

        return view('storefront.auth.register', ['wilayas' => Wilaya::active()->orderBy('code')->get()]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:120',
            'email'     => 'required|email|max:190|unique:clients,email',
            'phone'     => 'required|string|max:30',
            'wilaya_id' => 'nullable|exists:wilayas,id',
            'password'  => ['required', 'confirmed', Password::min(6)],
        ]);

        $client = Client::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'],
            'wilaya_id' => $data['wilaya_id'] ?? null,
            'password'  => $data['password'],
            'type'      => 'retail',
            'is_active' => true,
        ]);

        Auth::guard('client')->login($client);
        $request->session()->regenerate();

        return redirect()->route('account.index')->with('success', __('shop.welcome_account'));
    }

    public function showLogin()
    {
        if (Auth::guard('client')->check()) {
            return redirect()->route('account.index');
        }

        return view('storefront.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::guard('client')->attempt($credentials, $request->boolean('remember'))) {
            if (! Auth::guard('client')->user()->is_active) {
                Auth::guard('client')->logout();

                return back()->withErrors(['email' => 'Compte désactivé.'])->onlyInput('email');
            }

            $request->session()->regenerate();

            return redirect()->intended(route('account.index'));
        }

        return back()->withErrors(['email' => 'Identifiants invalides.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::guard('client')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
