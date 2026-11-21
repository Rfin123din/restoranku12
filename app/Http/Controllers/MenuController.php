<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Models\Item;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $tableNumber = $request->query('meja');

        if ($tableNumber) {
            Session::put('tableNumber', $tableNumber);
        } else {
            $tableNumber = Session::get('tableNumber');
        }

        $items = Item::where('is_active', 1)
            ->orderBy('name', 'asc')
            ->get();

        return view('customer.menu', compact('items', 'tableNumber'));
    }

    // Menampilkan keranjang
    public function cart()
    {
        $cart = Session::get('cart', []);

        return view('customer.cart', compact('cart'));
    }

    // Menambahkan menu ke keranjang
    public function addToCart(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:items,id'
        ]);

        $menuId = $request->input('id');

        $menu = Item::where('is_active', 1)
            ->find($menuId);

        if (!$menu) {
            return response()->json([
                'status' => 'error',
                'message' => 'Menu tidak tersedia'
            ], 404);
        }

        // Inisialisasi keranjang
        $cart = Session::get('cart', []);

        // Jika menu sudah ada, tambahkan quantity
        if (isset($cart[$menuId])) {

            $cart[$menuId]['qty'] += 1;

        } else {

            // Jika menu belum ada, masukkan ke keranjang
            $cart[$menuId] = [
                'id' => $menu->id,
                'name' => $menu->name,
                'price' => $menu->price,
                'image' => $menu->img,
                'qty' => 1
            ];
        }

        // Simpan keranjang ke session
        Session::put('cart', $cart);

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil ditambahkan ke keranjang',
            'cart' => $cart
        ]);
    }

    public function updateCart(Request $request)
    {
        $itemId = $request->input('id');
        $newQty = $request->input('qty');

        if ($newQty <= 0) {
            return response()->json(['success' => false]);
        }

        $cart = Session::get('cart');
        if (isset($cart[$itemId])) {
            $cart[$itemId]['qty'] = $newQty;
            Session::put('cart', $cart);
            Session::flash('success', 'Jumlah item berhasil diperbarui');

            return response()->json([ 'success' => true]);
        }

        return response()->json(['success' => false]);
    }


    public function removeFromCart(Request $request)
    {
        $request->validate([
            'id' => 'required|integer'
        ]);

        $cart = Session::get('cart', []);
        $menuId = $request->input('id');

        if (!isset($cart[$menuId])) {
            return response()->json([
                'success' => false,
                'message' => 'Menu tidak ditemukan dalam keranjang'
            ], 404);
        }

        unset($cart[$menuId]);

        Session::put('cart', $cart);

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil dihapus dari keranjang',
            'cart' => $cart
        ]);
    }

     public function clearCart()
    {
        Session::forget('cart');
        return redirect()->route('cart')->with('success', 'Keranjang berhasil dikosongkan');
    }
}
