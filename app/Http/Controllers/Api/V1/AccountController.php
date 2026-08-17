<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * The signed-in customer's own data. Every query is scoped to the
 * authenticated user — there is no path here that reaches another account.
 */
class AccountController extends Controller
{
    /** GET /api/v1/account/orders */
    public function orders(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->withCount('items')
            ->latest('placed_at')
            ->paginate(min($request->integer('per_page', 15), 50));

        return response()->json([
            'data' => $orders->through(fn (Order $order) => [
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_status' => $order->payment_status->value,
                'items_count' => $order->items_count,
                'grand_total' => (float) $order->grand_total,
                'currency' => $order->currency,
                'placed_at' => $order->placed_at?->toIso8601String(),
            ])->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /** PUT /api/v1/account/profile */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        // Changing the address invalidates verification.
        if ($data['email'] !== $user->email) {
            $user->email_verified_at = null;
        }

        $user->fill($data)->save();

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ]]);
    }

    /** PUT /api/v1/account/password */
    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('That password is not correct.'),
            ]);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        // Changing a password signs every other device out.
        $current = $user->currentAccessToken();
        $user->tokens()->where('id', '!=', $current?->id)->delete();

        return response()->json(['message' => __('Password updated.')]);
    }

    // Wishlist

    public function wishlist(Request $request): AnonymousResourceCollection
    {
        $products = Product::published()
            ->whereIn('id', Wishlist::where('user_id', $request->user()->id)->select('product_id'))
            ->with(['brand', 'primaryImage'])
            ->get();

        return ProductResource::collection($products);
    }

    public function addToWishlist(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        // Unique on (user_id, product_id) — adding twice is a no-op, not an error.
        Wishlist::firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $data['product_id'],
        ]);

        return response()->json(['message' => __('Added to your wishlist.')], 201);
    }

    public function removeFromWishlist(Request $request, int $product): JsonResponse
    {
        Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $product)
            ->delete();

        return response()->json(['message' => __('Removed from your wishlist.')]);
    }
}
