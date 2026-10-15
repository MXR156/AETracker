<?php

namespace App\Http\Controllers;

use App\Models\Holding;
use Illuminate\Http\Request;

class HoldingController extends Controller
{
    /**
     * Update my current parcel counts. Each day keeps one entry (saving again the same day just
     * updates it), so the parcel history chart still shows how the count changed over time.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'common' => ['required', 'integer', 'min:0'],
            'rare' => ['required', 'integer', 'min:0'],
            'epic' => ['required', 'integer', 'min:0'],
            'legendary' => ['required', 'integer', 'min:0'],
        ]);

        Holding::updateOrCreate(['user_id' => $request->user()->id, 'recorded_on' => now()->toDateString()], $data);

        return redirect()->to(route('dashboard').'#update')->with('status', 'Parcel counts saved.');
    }

    public function destroy(Request $request, Holding $holding)
    {
        abort_unless($holding->user_id === $request->user()->id, 404); // only your own
        $holding->delete();

        return redirect()->to(route('dashboard').'#update')->with('status', 'Entry deleted.');
    }
}
