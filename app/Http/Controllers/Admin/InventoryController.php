<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $variants = QueryBuilder::for(ProductVariant::class)
            ->with('product:id,title,slug')
            ->allowedFilters(...[
                AllowedFilter::callback('search', fn ($q, $v) => $q->where(
                    fn ($b) => $b->where('sku', 'like', "%{$v}%")
                        ->orWhereHas('product', fn ($p) => $p->where('title', 'like', "%{$v}%"))
                )),
                AllowedFilter::callback('stock', function ($q, $v) {
                    return match ($v) {
                        'out' => $q->where('stock_quantity', 0),
                        'low' => $q->lowStock()->where('stock_quantity', '>', 0),
                        'in' => $q->where('stock_quantity', '>', 0),
                        default => $q,
                    };
                }),
            ])
            ->allowedSorts(...['sku', 'stock_quantity', 'price'])
            ->defaultSort('stock_quantity')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'label' => $variant->label,
                'product_id' => $variant->product_id,
                'product_title' => $variant->product?->getTranslation('title', 'en'),
                'price' => (float) $variant->price,
                'stock_quantity' => $variant->stock_quantity,
                'low_stock_threshold' => $variant->low_stock_threshold,
                'is_low' => $variant->is_low_stock,
            ]);

        return Inertia::render('Admin/Inventory/Index', [
            'variants' => $variants,
            'filters' => $request->input('filter', []),
            'summary' => [
                'total_variants' => ProductVariant::count(),
                'out_of_stock' => ProductVariant::where('stock_quantity', 0)->count(),
                'low_stock' => ProductVariant::lowStock()->where('stock_quantity', '>', 0)->count(),
                'stock_units' => (int) ProductVariant::sum('stock_quantity'),
                'stock_value' => round((float) ProductVariant::selectRaw('SUM(stock_quantity * price) as v')->value('v'), 2),
            ],
        ]);
    }

    /**
     * Applies a stock delta and records the movement, so the running total is
     * always reconstructible from the audit trail.
     */
    public function adjust(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:set,add,subtract'],
            'quantity' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $variant) {
            $before = $variant->stock_quantity;

            $after = match ($data['mode']) {
                'set' => $data['quantity'],
                'add' => $before + $data['quantity'],
                'subtract' => max(0, $before - $data['quantity']),
            };

            $delta = $after - $before;

            $variant->update(['stock_quantity' => $after]);

            InventoryMovement::create([
                'product_variant_id' => $variant->id,
                'type' => $delta >= 0 ? 'in' : 'out',
                'quantity' => $delta,
                'stock_after' => $after,
                // `reason` is nullable, so it may be absent from the validated array.
                'reason' => ($data['reason'] ?? null) ?: 'Manual adjustment',
                'created_by' => auth()->id(),
            ]);

            $variant->product?->syncStockFromVariants();
        });

        return back()->with('success', "Stock updated for {$variant->sku}.");
    }

    public function history(ProductVariant $variant): Response
    {
        return Inertia::render('Admin/Inventory/History', [
            'variant' => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'label' => $variant->label,
                'product_title' => $variant->product?->getTranslation('title', 'en'),
                'stock_quantity' => $variant->stock_quantity,
            ],
            'movements' => $variant->inventoryMovements()
                ->with('author:id,name')
                ->latest()
                ->paginate(30)
                ->through(fn (InventoryMovement $movement) => [
                    'id' => $movement->id,
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'stock_after' => $movement->stock_after,
                    'reason' => $movement->reason,
                    'author' => $movement->author?->name,
                    'created_at' => $movement->created_at->format('d M Y H:i'),
                ]),
        ]);
    }
}
