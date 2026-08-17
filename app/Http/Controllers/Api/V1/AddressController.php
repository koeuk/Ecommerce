<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The customer's address book. Every query is scoped to the authenticated
 * user, so one customer can never reach another's addresses.
 */
class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->scope($request)
                ->orderByDesc('is_default_shipping')
                ->get()
                ->map(fn (UserAddress $a) => $this->serialise($a)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $address = DB::transaction(function () use ($request, $data) {
            $address = $request->user()->addresses()->create($data);

            $this->applyDefaults($request, $address, $data);

            return $address;
        });

        return response()->json(['data' => $this->serialise($address->fresh())], 201);
    }

    public function update(Request $request, int $address): JsonResponse
    {
        $model = $this->scope($request)->findOrFail($address);
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $model, $data) {
            $model->update($data);

            $this->applyDefaults($request, $model, $data);
        });

        return response()->json(['data' => $this->serialise($model->fresh())]);
    }

    public function destroy(Request $request, int $address): JsonResponse
    {
        $this->scope($request)->findOrFail($address)->delete();

        return response()->json(['message' => __('Address removed.')]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['nullable', 'string', 'max:60'],
            'receiver_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'is_default_shipping' => ['boolean'],
            'is_default_billing' => ['boolean'],
        ]);
    }

    /**
     * Only one address can be the default of each kind, and the first one
     * added becomes the default automatically.
     */
    private function applyDefaults(Request $request, UserAddress $address, array $data): void
    {
        $isFirst = $this->scope($request)->count() === 1;

        foreach (['is_default_shipping', 'is_default_billing'] as $flag) {
            $wants = ($data[$flag] ?? false) || $isFirst;

            if (! $wants) {
                continue;
            }

            $this->scope($request)->whereKeyNot($address->id)->update([$flag => false]);

            $address->update([$flag => true]);
        }
    }

    private function scope(Request $request)
    {
        return UserAddress::where('user_id', $request->user()->id);
    }

    private function serialise(UserAddress $address): array
    {
        return [
            'id' => $address->id,
            'label' => $address->label,
            'receiver_name' => $address->receiver_name,
            'phone' => $address->phone,
            'address_line1' => $address->address_line1,
            'address_line2' => $address->address_line2,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'country_code' => $address->country_code,
            'full_address' => $address->full_address,
            'is_default_shipping' => $address->is_default_shipping,
            'is_default_billing' => $address->is_default_billing,
        ];
    }
}
