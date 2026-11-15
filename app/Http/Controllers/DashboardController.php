<?php

namespace App\Http\Controllers;

use App\Models\Catalog;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $pendingInvoices = Order::with(['client', 'status', 'creator', 'paymentResponsible'])
            ->where('payment_term', 'credito')
            ->whereNull('paid_at')
            ->whereNotNull('payment_due_date')
            ->orderBy('payment_due_date', 'asc')
            ->get();

        return view('admin.dashboard.index', [
            'clientsCount' => Client::count(),
            'productsCount' => Product::count(),
            'catalogsCount' => Catalog::count(),
            'ordersCount' => Order::count(),
            'recentOrders' => Order::with(['client', 'status'])->latest()->take(5)->get(),
            'pendingInvoices' => $pendingInvoices,
            'pendingCollections' => $pendingInvoices,
            'invoicesToCollect' => $pendingInvoices,
            'pendingInvoicesCount' => $pendingInvoices->count(),
        ]);
    }
}
