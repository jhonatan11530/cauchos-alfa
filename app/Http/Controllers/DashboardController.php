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
        return view('admin.dashboard.index', [
            'clientsCount' => Client::count(),
            'productsCount' => Product::count(),
            'catalogsCount' => Catalog::count(),
            'ordersCount' => Order::count(),
            'recentOrders' => Order::with(['client', 'status'])->latest()->take(5)->get(),
        ]);
    }
}
