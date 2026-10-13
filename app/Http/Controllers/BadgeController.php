<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'boost_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'owned' => ['nullable', 'boolean'],
            'acquired_on' => ['nullable', 'date'],
        ]);
        $data['owned'] = $request->boolean('owned');
        $data['boost_percent'] ??= 0;

        return $data;
    }

    public function store(Request $request)
    {
        Badge::create($this->validated($request));

        return redirect()->to(route('portfolio.index').'#badges')->with('status', 'Badge added.');
    }

    public function update(Request $request, Badge $badge)
    {
        $badge->update($this->validated($request));

        return redirect()->to(route('portfolio.index').'#badges')->with('status', 'Badge updated.');
    }

    public function toggle(Badge $badge)
    {
        $badge->update([
            'owned' => ! $badge->owned,
            'acquired_on' => $badge->owned ? null : ($badge->acquired_on ?? now()),
        ]);

        return redirect()->to(route('portfolio.index').'#badges');
    }

    public function destroy(Badge $badge)
    {
        $badge->delete();

        return redirect()->to(route('portfolio.index').'#badges')->with('status', 'Badge deleted.');
    }
}
