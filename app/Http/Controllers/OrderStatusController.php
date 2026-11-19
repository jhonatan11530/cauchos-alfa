<?php

namespace App\Http\Controllers;

use App\Models\OrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderStatusController extends Controller
{
    public function index(): View
    {
        return view('admin.order-statuses.index', ['statuses' => OrderStatus::orderBy('sort_order')->paginate(10)]);
    }

    public function create(): RedirectResponse
    {
        return redirect()
            ->route('estados-pedido.index')
            ->with('error', 'No se pueden crear estados adicionales. El sistema está limitado a los 8 estados oficiales.');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()
            ->route('estados-pedido.index')
            ->with('error', 'No se pueden crear estados adicionales. El sistema está limitado a los 8 estados oficiales.');
    }

    public function edit(OrderStatus $estados_pedido): View
    {
        return view('admin.order-statuses.form', ['status' => $estados_pedido]);
    }

    public function update(Request $request, OrderStatus $estados_pedido): RedirectResponse
    {
        $data = $this->validatedData($request, $estados_pedido);

        if ($estados_pedido->isCritical()) {
            $data['name'] = $estados_pedido->name;
            $data['slug'] = $estados_pedido->slug;
            $data['is_final'] = $estados_pedido->is_final;
            $data['is_cancelled'] = $estados_pedido->is_cancelled;
            $data['is_active'] = true;
        } else {
            $newSlug = Str::slug($data['name'] ?? $estados_pedido->name);
            if (in_array($newSlug, OrderStatus::CRITICAL_SLUGS, true)) {
                return back()->with('error', 'El nombre ingresado genera un slug reservado por el sistema.');
            }
            $data['slug'] = $newSlug;
            $data['is_final'] = $request->boolean('is_final');
            $data['is_cancelled'] = $request->boolean('is_cancelled');
            $data['is_active'] = $request->boolean('is_active');
        }

        $estados_pedido->update($data);

        return redirect()->route('estados-pedido.index')->with('success', 'Estado actualizado correctamente.');
    }

    public function destroy(OrderStatus $estados_pedido): RedirectResponse
    {
        if ($estados_pedido->isCritical()) {
            return back()->with('error', 'No se puede desactivar un estado oficial del sistema.');
        }

        $estados_pedido->update(['is_active' => ! $estados_pedido->is_active]);

        return back()->with('success', 'Estado actualizado.');
    }

    private function validatedData(Request $request, ?OrderStatus $status = null): array
    {
        return $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('order_statuses')->ignore($status)],
            'color' => ['required', 'string', 'max:20'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
    }
}
