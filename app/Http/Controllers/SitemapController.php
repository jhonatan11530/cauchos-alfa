<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $categories = Category::where('is_active', true)->get();

        $content = view('site.sitemap', compact('categories'))->render();

        return response($content)->header('Content-Type', 'text/xml; charset=UTF-8');
    }

    public function feed(): Response
    {
        $products = Product::where('is_active', true)->with(['category', 'images'])->get();
        $content = view('site.feed', compact('products'))->render();

        return response($content)->header('Content-Type', 'text/xml; charset=UTF-8');
    }
}
