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

    public function create(): View
    {
        return view('admin.order-statuses.form', ['status' => new OrderStatus()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['slug'] = Str::slug($data['name']);
        $data['is_final'] = $request->boolean('is_final');
        $data['is_cancelled'] = $request->boolean('is_cancelled');
        $data['is_active'] = $request->boolean('is_active');
        OrderStatus::create($data);

        return redirect()->route('estados-pedido.index')->with('success', 'Estado creado correctamente.');
    }

    public function edit(OrderStatus $estados_pedido): View
    {
        return view('admin.order-statuses.form', ['status' => $estados_pedido]);
    }

    public function update(Request $request, OrderStatus $estados_pedido): RedirectResponse
    {
        $data = $this->validatedData($request, $estados_pedido);
        $data['slug'] = Str::slug($data['name']);
        $data['is_final'] = $request->boolean('is_final');
        $data['is_cancelled'] = $request->boolean('is_cancelled');
        $data['is_active'] = $request->boolean('is_active');
        $estados_pedido->update($data);

        return redirect()->route('estados-pedido.index')->with('success', 'Estado actualizado correctamente.');
    }

    public function destroy(OrderStatus $estados_pedido): RedirectResponse
    {
        $estados_pedido->update(['is_active' => ! $estados_pedido->is_active]);

        return back()->with('success', 'Estado actualizado.');
    }

    private function validatedData(Request $request, ?OrderStatus $status = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('order_statuses')->ignore($status)],
            'color' => ['required', 'string', 'max:20'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
    }
}
