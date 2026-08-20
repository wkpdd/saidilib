<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TempPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderByDesc('is_admin')->orderBy('name')->get();
        $stats = [
            'total'   => $users->count(),
            'admins'  => $users->where('role', 'admin')->count(),
            'staff'   => $users->whereIn('role', ['manager', 'staff'])->count(),
            'active'  => $users->where('is_active', true)->count(),
        ];

        return view('admin.users.index', compact('users', 'stats'));
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(['is_active' => true, 'role' => 'staff'])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:120',
            'email'         => 'required|email|unique:users,email',
            'phone'         => 'nullable|string|max:30',
            'role'          => ['required', Rule::in(array_keys(User::ROLES))],
            'password'      => 'required|string|min:6',
            'permissions'   => 'nullable|array',
            'permissions.*' => ['string', Rule::in(array_keys(User::PERMISSIONS))],
            'is_active'     => 'nullable|boolean',
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['is_admin'] = true; // any staff member can sign into the back-office
        $data['is_active'] = $request->boolean('is_active');
        $data['permissions'] = $this->resolvePermissions($request);

        User::create($data);

        return redirect()->route('admin.users.index')->with('success', 'Membre ajouté à l\'équipe.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:120',
            'email'         => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'         => 'nullable|string|max:30',
            'role'          => ['required', Rule::in(array_keys(User::ROLES))],
            'password'      => 'nullable|string|min:6',
            'permissions'   => 'nullable|array',
            'permissions.*' => ['string', Rule::in(array_keys(User::PERMISSIONS))],
            'is_active'     => 'nullable|boolean',
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone = $data['phone'] ?? null;
        $user->role = $data['role'];
        $user->is_admin = true;
        $user->is_active = $request->boolean('is_active');
        $user->permissions = $this->resolvePermissions($request);
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return redirect()->route('admin.users.index')->with('success', 'Membre mis à jour.');
    }

    /**
     * Admin role → null (implicitly all permissions). Otherwise the explicit
     * checkbox selection (validated against the known permission keys).
     */
    private function resolvePermissions(Request $request): ?array
    {
        if ($request->input('role') === 'admin') {
            return null;
        }

        return array_values(array_intersect(
            (array) $request->input('permissions', []),
            array_keys(User::PERMISSIONS)
        ));
    }

    /**
     * Give a staff member a new password on the spot (generated unless one is
     * typed). Restricted to full administrators — the `users` permission alone
     * shouldn't let a manager take over an admin account.
     */
    public function resetPassword(Request $request, User $user)
    {
        if (! $request->user()->isFullAdmin()) {
            return back()->with('error', 'Seul un administrateur peut réinitialiser un mot de passe.');
        }

        $data = $request->validate(['password' => 'nullable|string|min:6|max:190']);
        $password = ($data['password'] ?? null) ?: TempPassword::make();

        $user->forceFill(['password' => Hash::make($password)])->save();

        return back()
            ->with('success', "Mot de passe de {$user->name} réinitialisé.")
            ->with('new_password', $password)
            ->with('new_password_user', $user->id);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        $user->delete();

        return back()->with('success', 'Membre supprimé.');
    }
}
