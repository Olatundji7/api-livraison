<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()->active();
        if ($request->filled('category')) $query->where('category', $request->input('category'));
        return response()->json(['products' => $query->latest()->get()]);
    }

    public function adminIndex(Request $request)
    {
        return response()->json(['products' => Product::latest()->get()]);
    }

    public function show(Product $product)
    {
        if (! $product->is_active) return response()->json(['message' => 'Produit indisponible.'], 404);
        return response()->json(['product' => $product]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['image'] = $request->file('image')?->store('products', 'public');
        return response()->json(['product' => Product::create($data)], 201);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validateData($request);
        if ($request->hasFile('image')) {
            if ($product->image) Storage::disk('public')->delete($product->image);
            $data['image'] = $request->file('image')->store('products', 'public');
        }
        $product->update($data);
        return response()->json(['product' => $product->fresh()]);
    }

    public function destroy(Product $product)
    {
        if ($product->image) Storage::disk('public')->delete($product->image);
        $product->delete();
        return response()->json(['message' => 'Produit supprimé.']);
    }

    private function validateData(Request $request): array
    {
        return Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
        ])->validate();
    }
}
