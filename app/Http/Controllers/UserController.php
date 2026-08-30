<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::with('role')
            ->when(request()->filled('q'), function ($query) {
                $term = '%' . request()->string('q') . '%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('seller_code', 'like', $term));
            })
            ->latest();

        return view('admin.users.index', ['users' => $users->paginate(10)->withQueryString()]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User(), 'roles' => Role::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $request->boolean('is_active');
        User::create($data);

        return redirect()->route('usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $usuario): View
    {
        return view('admin.users.form', ['user' => $usuario, 'roles' => Role::orderBy('name')->get()]);
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $data = $this->validatedData($request, $usuario);
        $data['is_active'] = $request->boolean('is_active');

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $usuario->update($data);

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario): RedirectResponse
    {
        $usuario->update(['is_active' => ! $usuario->is_active]);

        return back()->with('success', 'Estado del usuario actualizado.');
    }

    private function validatedData(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'role_id' => ['nullable', 'exists:roles,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:50'],
            'seller_code' => ['nullable', 'string', 'max:50', Rule::unique('users')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
        ]);
    }
}
