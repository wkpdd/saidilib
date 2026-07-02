<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
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
            'name'     => 'required|string|max:120',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|max:30',
            'role'     => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => 'required|string|min:6',
            'is_active'=> 'nullable|boolean',
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['is_admin'] = true; // any staff member can sign into the back-office
        $data['is_active'] = $request->boolean('is_active');

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
            'name'     => 'required|string|max:120',
            'email'    => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'    => 'nullable|string|max:30',
            'role'     => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => 'nullable|string|min:6',
            'is_active'=> 'nullable|boolean',
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone = $data['phone'] ?? null;
        $user->role = $data['role'];
        $user->is_admin = true;
        $user->is_active = $request->boolean('is_active');
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return redirect()->route('admin.users.index')->with('success', 'Membre mis à jour.');
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
