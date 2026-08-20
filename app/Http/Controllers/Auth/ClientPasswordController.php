<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * "Mot de passe oublié" for storefront customers, on the `clients` broker.
 * Requires a working mailer — with MAIL_MAILER=log the link only lands in
 * storage/logs. Staff can always reset a client from the back-office instead.
 */
class ClientPasswordController extends Controller
{
    public function showRequest()
    {
        if (Auth::guard('client')->check()) {
            return redirect()->route('account.index');
        }

        return view('storefront.auth.forgot');
    }

    public function sendLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::broker('clients')->sendResetLink($request->only('email'));

        // Never reveal whether the address exists.
        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors(['email' => __($status)])->onlyInput('email');
        }

        return back()->with('success', __('shop.password_link_sent'));
    }

    public function showReset(Request $request, string $token)
    {
        return view('storefront.auth.reset', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(6)],
        ]);

        $status = Password::broker('clients')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($client, string $password) {
                $client->forceFill([
                    'password'       => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($client));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)])->onlyInput('email');
        }

        return redirect()->route('account.login')->with('success', __('shop.password_changed'));
    }
}
