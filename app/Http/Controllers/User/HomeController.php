<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Additional;
use App\Models\Packet;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        // Query packet
        $query = Packet::with('product')
            ->where('is_active', 1)
            ->latest();

        // Filter product jika ada
        if ($request->has('product_id') && $request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        // Data packets
        $packets = $query->get()->groupBy('product.name');

        // Semua products
        $products = Product::latest()->get();

        // Additional
        $all_additionals = Additional::where('price', '>', 0)
            ->orderBy('name')
            ->get();

        // Return halaman utama
        return view('userPage.home', compact(
            'packets',
            'products',
            'all_additionals'
        ));
    }
    

    /**
     * Display packets by product.
     */
    public function product($id)
    {
        $product = Product::findOrFail($id);

        $packets = Packet::with('product')
            ->where('product_id', $product->id)
            ->latest()
            ->get();

        $products = Product::all();

        return view('userPage.home', compact(
            'packets',
            'products',
            'product'
        ));
    }
}