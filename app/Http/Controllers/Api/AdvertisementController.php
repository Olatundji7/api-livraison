<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdvertisementController extends Controller
{
    public function index()
    {
        return response()->json([
            'advertisements' => Advertisement::currentlyVisible()
                ->latest()
                ->get(),
        ]);
    }

    public function adminIndex()
    {
        return response()->json([
            'advertisements' => Advertisement::latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('advertisements', 'public');
        }

        return response()->json([
            'advertisement' => Advertisement::create($data),
        ], 201);
    }

    public function update(Request $request, Advertisement $advertisement)
    {
        $data = $this->validateData($request);

        if ($request->hasFile('image')) {
            if ($advertisement->image) {
                Storage::disk('public')->delete($advertisement->image);
            }
            $data['image'] = $request->file('image')->store('advertisements', 'public');
        }

        $advertisement->update($data);

        return response()->json([
            'advertisement' => $advertisement->fresh(),
        ]);
    }

    public function destroy(Advertisement $advertisement)
    {
        if ($advertisement->image) {
            Storage::disk('public')->delete($advertisement->image);
        }

        $advertisement->delete();

        return response()->json([
            'message' => 'Publicité supprimée.',
        ]);
    }

    private function validateData(Request $request): array
    {
        return Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:4096'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ])->validate();
    }
}
