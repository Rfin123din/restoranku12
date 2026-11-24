<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Models\Item;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;

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

    // Checkout
    public function checkout()
    {
        $cart = Session::get('cart');
        if(empty($cart)) {
            return redirect()->route('cart')->with('error', 'Keranjang masih kosong');
        }

        $tableNumber = Session::get('tableNumber');

        return view('customer.checkout', compact('cart','tableNumber'));
    }

    public function storeOrder(Request $request)
    {
        $cart = Session::get('cart');
        $tableNumber = Session::get('tableNumber');

        if(empty($cart)) {
            return redirect()->route('cart')->with('error', 'Keranjang masih kosong');
        }

        $validator = Validator::make($request->all(), [
            'fullname' => 'required|string|max:255',
            'phone' => 'required|string|max:15',
        ]);

        if ($validator->fails()) {
            return redirect()->route('checkout')->withErrors($validator);
        }

        $total = 0;
        foreach ($cart as $item) {
            $total += $item['price'] * $item['qty'];
        }

         $totalAmount = 0;
        foreach ($cart as $item) {
            $totalAmount += $item['qty'] * $item['price'];

            $itemDetails[] = [
                'id' => $item['id'],
                'price' => (int) ($item['price'] + ($item['price'] * 0.1)),
                'quantity' => $item['qty'],
                'name' => substr($item['name'], 0, 50),
            ];
        }

        $user = User::firstOrCreate([
            'fullname' => $request->input('fullname'),
            'phone' => $request->input('phone'),
            'role_id' => 4
        ]);

        $order = Order::create([
            'order_code' => 'ORD-'.$tableNumber.'-'. time(),
            'user_id' => $user->id,
            'subtotal' => $totalAmount,
            'tax' => 0.1 * $totalAmount,
            'grand_total' => $totalAmount + (0.1 * $totalAmount),
            'status' => 'pending',
            'table_number' => $tableNumber,
            'payment_method' => $request->payment_method,
            'note' => $request->note,
        ]);

        foreach ($cart as $itemId => $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'item_id' => $item['id'],
                'quantity' => $item['qty'],
                'price' => $item['price'] * $item['qty'],
                'tax' => 0.1 * $item['price'] * $item['qty'],
                'total_price' => ($item['price'] * $item['qty']) + (0.1 * $item['price'] * $item['qty']),
            ]);
        }

        Session::forget('cart');

        return redirect()->route('checkout.success', ['orderId' => $order->order_code])->with('success', 'Pesanan berhasil dibuat. Silakan lakukan pembayaran di kasir.');
    }

    public function checkoutSuccess($orderId)
    {
        $order = Order::where('order_code', $orderId)->first();

        if (!$order) {
            return redirect()->route('menu')->with('error', 'Pesanan tidak ditemukan');
        }

        $orderItems = OrderItem::where('order_id', $order->id)->get();

        if ($order->payment_method == 'qris') {
            $order->status  = 'settlement';
            $order->save();
        }

        return view('customer.success', compact('order', 'orderItems'));

    }
}


