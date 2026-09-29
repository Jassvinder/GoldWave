<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Settings\SetMetalRate;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SetMetalRateRequest;
use App\Models\MetalRate;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md S07 — Gold & Silver rates together, with effective-date history. */
class MetalRateController extends Controller
{
    public function index(Request $request): Response
    {
        $rates = MetalRate::orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MetalRate $rate): array => [
                'id' => $rate->id,
                'metal' => $rate->metal,
                'rate_per_gram' => $rate->rate_per_gram,
                'making_charge_percent' => $rate->making_charge_percent,
                'effective_from' => Dates::date($rate->effective_from),
            ]);

        return Inertia::render('super-admin/metal-rates', [
            'rates' => $rates,
        ]);
    }

    public function store(SetMetalRateRequest $request, SetMetalRate $action): RedirectResponse
    {
        $action(
            $request->string('metal')->toString(),
            (float) $request->input('rate_per_10_grams'),
            (float) $request->input('making_charge_percent'),
            $request->string('effective_from')->toString(),
            $request->user(),
        );

        return redirect()->route('super-admin.metal-rates.index')->with('status', 'Metal rate recorded.');
    }
}
