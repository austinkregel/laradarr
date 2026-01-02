<?php

namespace App\Http\Controllers;

use App\Models\Credential;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CredentialController extends Controller
{
    public function index()
    {
        return Inertia::render('Credentials', [
            'credentials' => Credential::query()
                ->select(['id', 'service', 'key', 'is_enabled', 'expires_at', 'last_used_at', 'updated_at'])
                ->orderBy('service')
                ->orderBy('key')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'service' => ['required', 'string', 'max:100'],
            'key' => ['required', 'string', 'max:100'],
            'value' => ['nullable', 'string'],
            'is_enabled' => ['boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        Credential::query()->updateOrCreate(
            ['service' => $data['service'], 'key' => $data['key']],
            [
                'value' => $data['value'] ?? null,
                'is_enabled' => (bool) ($data['is_enabled'] ?? true),
                'expires_at' => $data['expires_at'] ?? null,
            ]
        );

        return back();
    }

    public function update(Request $request, Credential $credential)
    {
        // value is write-only: we never return it to the client.
        $data = $request->validate([
            'value' => ['nullable', 'string'],
            'is_enabled' => ['boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        if (array_key_exists('value', $data)) {
            $credential->value = $data['value'];
        }

        if (array_key_exists('is_enabled', $data)) {
            $credential->is_enabled = (bool) $data['is_enabled'];
        }

        if (array_key_exists('expires_at', $data)) {
            $credential->expires_at = $data['expires_at'];
        }

        if ($credential->isDirty()) {
            $credential->save();
        }

        return back();
    }

    public function destroy(Credential $credential)
    {
        $credential->delete();
        return back();
    }
}





