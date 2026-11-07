<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Models\AdvertisementPartner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdvertisementController extends Controller
{
    public function index()
    {
        return response()->json([
            'advertisements' => Advertisement::with('partner')
                ->currentlyVisible()->latest()->get(),
        ]);
    }

    public function adminIndex()
    {
        return response()->json([
            'advertisements' => Advertisement::with('partner')->latest()->get(),
        ]);
    }

    public function partners()
    {
        return response()->json([
            'partners' => AdvertisementPartner::orderBy('name')->get(),
        ]);
    }

    public function storePartner(Request $request)
    {
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:advertisement_partners,name'],
            'business_category' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_whatsapp' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:2048'],
        ])->validate();

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('advertisements', 'public');
        }

        return response()->json([
            'partner' => AdvertisementPartner::create($data),
        ], 201);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('advertisements', 'public');
        }

        return response()->json([
            'advertisement' => Advertisement::create($data)->load('partner'),
        ], 201);
    }

    public function update(Request $request, Advertisement $advertisement)
    {
        $data = $this->validateData($request);
        if ($request->hasFile('image')) {
            if ($advertisement->image) Storage::disk('public')->delete($advertisement->image);
            $data['image'] = $request->file('image')->store('advertisements', 'public');
        }

        $advertisement->update($data);

        return response()->json([
            'advertisement' => $advertisement->fresh()->load('partner'),
        ]);
    }

    public function destroy(Advertisement $advertisement)
    {
        if ($advertisement->image) Storage::disk('public')->delete($advertisement->image);
        $advertisement->delete();

        return response()->json(['message' => 'Publicité supprimée.']);
    }

    private function validateData(Request $request): array
    {
        return Validator::make($request->all(), [
            'partner_id' => ['required', 'integer', 'exists:advertisement_partners,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:4096'],
            'is_sponsored' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ])->validate();
    }
}
