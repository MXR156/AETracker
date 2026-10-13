<?php

namespace App\Http\Controllers;

use App\Models\Holding;
use App\Services\EarningsCalculator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HoldingController extends Controller
{
    /** Record my full parcel counts on a date. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'recorded_on' => ['required', 'date'],
            'common' => ['required', 'integer', 'min:0'],
            'rare' => ['required', 'integer', 'min:0'],
            'epic' => ['required', 'integer', 'min:0'],
            'legendary' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        Holding::updateOrCreate(['recorded_on' => $data['recorded_on']], $data);

        return redirect()->route('portfolio.index')->with('status', 'Parcel counts saved.');
    }

    /** Add (or remove) a number of parcels of one rarity to my current counts. */
    public function add(Request $request)
    {
        $data = $request->validate([
            'rarity' => ['required', Rule::in(EarningsCalculator::RARITIES)],
            'quantity' => ['required', 'integer', 'not_in:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $current = Holding::current();
        $counts = $current ? $current->counts() : array_fill_keys(EarningsCalculator::RARITIES, 0);
        $counts[$data['rarity']] = max(0, $counts[$data['rarity']] + $data['quantity']);

        $today = now()->toDateString();
        $holding = $current && $current->recorded_on->toDateString() === $today ? $current : new Holding(['recorded_on' => $today]);
        $holding->fill($counts);
        $note = ($data['quantity'] > 0 ? '+' : '').$data['quantity'].' '.$data['rarity'].(! empty($data['notes']) ? " ({$data['notes']})" : '');
        $holding->notes = trim(($holding->exists ? $holding->notes.'; ' : '').$note, '; ');
        $holding->save();

        return redirect()->route('portfolio.index')->with('status', "Recorded {$note}.");
    }

    public function destroy(Holding $holding)
    {
        $holding->delete();

        return redirect()->route('portfolio.index')->with('status', 'Entry deleted.');
    }
}
