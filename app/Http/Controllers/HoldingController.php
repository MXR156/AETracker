<?php

namespace App\Http\Controllers;

use App\Models\Holding;
use Illuminate\Http\Request;

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

        return redirect()->to(route('dashboard').'#update')->with('status', 'Parcel counts saved.');
    }

    public function destroy(Holding $holding)
    {
        $holding->delete();

        return redirect()->to(route('dashboard').'#update')->with('status', 'Entry deleted.');
    }
}
