<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DispatchSheetController extends Controller
{
    /**
     * Vista de gestión de despachos del día.
     */
    public function index(Request $request): View
    {
        $selectedDate = $request->input('date', date('Y-m-d'));
        $dispatchedStatus = OrderStatus::where('slug', OrderStatus::SLUG_DESPACHADO)->first();

        $orders = Order::with(['client', 'creator', 'status', 'items.product'])
            ->when($dispatchedStatus, fn ($q) => $q->where('order_status_id', $dispatchedStatus->id), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($request->filled('date'), function ($q) use ($selectedDate) {
                $q->whereDate('ordered_at', $selectedDate);
            })
            ->latest('id')
            ->get();

        return view('admin.dispatch.index', [
            'orders' => $orders,
            'selectedDate' => $selectedDate,
            'totalOrders' => $orders->count(),
        ]);
    }

    /**
     * Vista de impresión tipo manifiesto / hoja de ruta física.
     */
    public function print(Request $request): View
    {
        $selectedDate = $request->input('date', date('Y-m-d'));
        $dispatchedStatus = OrderStatus::where('slug', OrderStatus::SLUG_DESPACHADO)->first();

        $orders = Order::with(['client', 'creator', 'status', 'items.product'])
            ->when($dispatchedStatus, fn ($q) => $q->where('order_status_id', $dispatchedStatus->id), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($request->filled('date'), function ($q) use ($selectedDate) {
                $q->whereDate('ordered_at', $selectedDate);
            })
            ->latest('id')
            ->get();

        return view('admin.dispatch.print', [
            'orders' => $orders,
            'selectedDate' => $selectedDate,
            'totalOrders' => $orders->count(),
        ]);
    }
}
