<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Every mutation returns the whole cart with totals, so the client never has
 * to recompute money or re-fetch after a change.
 *
 * Guests identify themselves with the `X-Cart-Token` header. If they arrive
 * without one, the server mints a token and returns it on the response — the
 * client stores it and sends it back from then on.
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function show(Request $request): JsonResponse
    {
        return $this->respond($request);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $token = $this->token($request);

        $this->cart->add($this->user($request), $token, $data['variant_id'], $data['quantity']);

        return $this->respond($request, $token, 201);
    }

    public function update(Request $request, int $item): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $token = $this->token($request);

        $this->cart->update($this->user($request), $token, $item, $data['quantity']);

        return $this->respond($request, $token);
    }

    public function destroy(Request $request, int $item): JsonResponse
    {
        $token = $this->token($request);

        $this->cart->remove($this->user($request), $token, $item);

        return $this->respond($request, $token);
    }

    public function clear(Request $request): JsonResponse
    {
        $token = $this->token($request);

        $this->cart->clear($this->user($request), $token);

        return $this->respond($request, $token);
    }

    /**
     * Folds the guest cart carried by `X-Cart-Token` into the signed-in
     * user's cart. Called by the client straight after login.
     */
    public function merge(Request $request): JsonResponse
    {
        $token = $request->header('X-Cart-Token');

        $this->cart->merge($this->user($request), $token);

        return $this->respond($request);
    }

    /**
     * The cart is reachable signed-in or not, so no auth middleware guards
     * it. Resolve the Sanctum user when a bearer token is present, and
     * treat everyone else as a guest.
     */
    private function user(Request $request): ?User
    {
        // The guard resolves a token when one is present and returns null
        // otherwise — checking for a bearer token first would miss any other
        // way the guard gets its user.
        return auth('sanctum')->user();
    }

    /**
     * The caller's existing token, or a fresh one for a guest who has none.
     * Authenticated carts key off the user, so no token is involved.
     */
    private function token(Request $request): ?string
    {
        if ($this->user($request)) {
            return null;
        }

        return $request->header('X-Cart-Token') ?: $this->cart->newToken();
    }

    private function respond(Request $request, ?string $token = null, int $status = 200): JsonResponse
    {
        $token ??= $this->user($request) ? null : $request->header('X-Cart-Token');

        $response = response()->json([
            'data' => $this->cart->summary($this->user($request), $token),
            'cart_token' => $token,
        ], $status);

        return $token
            ? $response->header('X-Cart-Token', $token)
            : $response;
    }
}
