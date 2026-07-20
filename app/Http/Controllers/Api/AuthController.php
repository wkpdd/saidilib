<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /** Staff login → bearer token + profile + granted permissions. */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
            'device'   => 'nullable|string|max:120',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Email ou mot de passe incorrect.'], 422);
        }
        if (! $user->is_admin || ! $user->is_active) {
            return response()->json(['message' => "Ce compte n'a pas accès à l'application équipe."], 403);
        }

        return response()->json([
            'token' => ApiToken::issue($user, $data['device'] ?? null),
            'user'  => $this->profile($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->profile($request->user())]);
    }

    /** Revoke THIS device's token. */
    public function logout(Request $request)
    {
        $request->attributes->get('api_token')?->delete();

        return response()->json(['ok' => true]);
    }

    /** Register/refresh the FCM push token for this device (optional). */
    public function fcmToken(Request $request)
    {
        $data = $request->validate(['fcm_token' => 'required|string|max:255']);
        $request->attributes->get('api_token')?->update(['fcm_token' => $data['fcm_token']]);

        return response()->json(['ok' => true]);
    }

    private function profile(User $user): array
    {
        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'role'        => $user->role,
            'role_label'  => $user->role_label,
            'permissions' => $user->grantedPermissions(),
        ];
    }
}
