<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;

class TagsController extends Controller
{
    private function ownerId(): int
    {
        $user = auth()->user();

        return $user->role === 'employee' ? (int) $user->shop_owner_id : (int) $user->id;
    }

    public function index()
    {
        $user = auth()->user();
        if ($user->role === 'employee' && ! $user->hasPermission('view_tags')) {
            abort(403);
        }

        $tags = Tag::where('user_id', $this->ownerId())->orderBy('name')->get();

        return view('tags.index', compact('tags'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && ! $user->hasPermission('create_tags')) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);

        Tag::create([
            'name' => $request->name,
            'price' => $request->price,
            'user_id' => $this->ownerId(),
        ]);

        return redirect()->route('tags.index')->with('success', __('ui.saved'));
    }

    public function destroy(Tag $tag)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && ! $user->hasPermission('delete_tags')) {
            abort(403);
        }

        if ($tag->user_id !== $this->ownerId()) {
            abort(404);
        }

        $tag->delete();

        return redirect()->route('tags.index')->with('success', __('ui.saved'));
    }
}
