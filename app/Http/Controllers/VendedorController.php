<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VendedorController extends Controller
{
    public function index(): View
    {
        $role = Role::where('slug', 'vendedor')->first();

        $sellers = User::with('role')
            ->when($role, fn($q) => $q->where('role_id', $role->id))
            ->when(request()->filled('q'), function ($query) {
                $term = '%' . request()->string('q') . '%';
                $query->where(fn($q) => $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('seller_code', 'like', $term));
            })
            ->latest();

        return view('admin.sellers.index', ['sellers' => $sellers->paginate(10)->withQueryString()]);
    }

    public function create(): View
    {
        return view('admin.sellers.form', [
            'seller' => new User(['is_active' => true]),
            'suggestedCode' => $this->generateCode(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['role_id'] = Role::where('slug', 'vendedor')->value('id');
        $data['password'] = Hash::make($data['password']);
        $data['seller_code'] = strtoupper(trim($data['seller_code']));
        $data['is_active'] = $request->boolean('is_active', true);
        User::create($data);

        return redirect()->route('vendedores.index')->with('success', 'Vendedor creado correctamente. Su código de acceso es ' . $data['seller_code'] . '.');
    }

    public function edit(User $vendedor): View
    {
        return view('admin.sellers.form', [
            'seller' => $vendedor,
            'suggestedCode' => $vendedor->seller_code,
        ]);
    }

    public function update(Request $request, User $vendedor): RedirectResponse
    {
        $data = $this->validatedData($request, $vendedor);
        $data['seller_code'] = strtoupper(trim($data['seller_code']));
        $data['is_active'] = $request->boolean('is_active');

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $vendedor->update($data);

        return redirect()->route('vendedores.index')->with('success', 'Vendedor actualizado correctamente.');
    }

    public function destroy(User $vendedor): RedirectResponse
    {
        $vendedor->update(['is_active' => !$vendedor->is_active]);

        return back()->with('success', 'Estado del vendedor actualizado.');
    }

    private function validatedData(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:50'],
            'seller_code' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
        ]);
    }

    private function generateCode(): string
    {
        do {
            $code = 'V-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (User::where('seller_code', $code)->exists());

        return $code;
    }
}
