<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;

class TagsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('view_tags')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $tags = Tag::where('user_id', $ownerId)->orderBy('name')->get();

        return view('tags.index', compact('tags'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('create_tags')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);

        Tag::create([
            'name' => $request->name,
            'price' => $request->price,
            'user_id' => $ownerId,
        ]);

        return redirect()->route('tags.index')->with('success', 'Tag created successfully!');
    }

    public function destroy(Tag $tag)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('delete_tags')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        if ($tag->user_id !== $ownerId) {
            abort(403, 'Unauthorized');
        }

        $tag->delete();

        return redirect()->route('tags.index')->with('success', 'Tag deleted successfully!');
    }
}
