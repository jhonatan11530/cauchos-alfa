<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VendedorController extends Controller
{
    /**
     * Dominio usado para generar el correo automatico del vendedor
     * a partir de su nombre.
     */
    private const EMAIL_DOMAIN = 'cauchosalfa.com';

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
        $data['seller_code'] = strtoupper(trim($data['seller_code']));
        $data['email'] = $this->generateEmail($data['name']);
        $data['password'] = Hash::make($this->generatePassword($data['seller_code']));
        $data['is_active'] = $request->boolean('is_active', true);
        User::create($data);

        return redirect()->route('vendedores.index')->with('success',
            'Vendedor creado correctamente. Código de acceso: '.$data['seller_code'].
            ' | Correo: '.$data['email']);
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
            'phone' => ['nullable', 'string', 'max:50'],
            'seller_code' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user)],
        ]);
    }

    /**
     * Correo automatico: nombre normalizado + dominio de la app.
     * Ej: "Juan Pérez" -> "juan.perez@cauchosalfa.com"
     */
    private function generateEmail(string $name): string
    {
        $base = Str::slug($name, '.');

        do {
            $email = $base.'@'.self::EMAIL_DOMAIN;
            if (! User::where('email', $email)->exists()) {
                return $email;
            }
            $base = $base.'-'.Str::lower(Str::random(4));
        } while (true);
    }

    /**
     * Contraseña automatica: código del vendedor + fecha actual (Ymd).
     * Ej: V-1234 + 2026-05-30 -> "V-1234-20260530"
     */
    private function generatePassword(string $sellerCode): string
    {
        return $sellerCode.'-'.now()->format('Ymd');
    }

    private function generateCode(): string
    {
        do {
            $code = 'V-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (User::where('seller_code', $code)->exists());

        return $code;
    }
}
