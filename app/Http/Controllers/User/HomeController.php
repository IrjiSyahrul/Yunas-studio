<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Additional;
use App\Models\Packet;
use App\Models\Transaksi;   

class HomeController extends Controller
{
      public function index()
    {
        $packets = Packet::with('product')->where('is_active', 1)->get()->groupBy('product.name');
        $all_additionals = Additional::where('price', '>', 0)->orderBy('name')->get();
        return view('userPage.home',compact('packets', 'all_additionals'));
    }
     
}

