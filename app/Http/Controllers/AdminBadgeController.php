<?php

namespace App\Http\Controllers;

use App\Models\DomusMission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminBadgeController extends Controller
{
    public function dashboard(): View
    {
        $badges = DomusMission::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin-dashboard', [
            'badges' => $badges,
        ]);
    }

    public function updateImage(Request $request, DomusMission $badge): RedirectResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ]);

        $directory = storage_path('app/badges');
        File::ensureDirectoryExists($directory);

        $image = $validated['image'];
        $extension = $image->extension() ?: 'png';
        $filename = $badge->slug.'-'.now()->format('YmdHis').'.'.$extension;
        $relativePath = 'badges/'.$filename;

        $image->move($directory, $filename);

        if ($badge->image_path) {
            $oldPath = storage_path('app/'.$badge->image_path);
            if (str_starts_with($oldPath, $directory) && File::exists($oldPath)) {
                File::delete($oldPath);
            }
        }

        $badge->forceFill([
            'image_path' => $relativePath,
        ])->save();

        return back()->with('status', 'Imagen de insignia actualizada.');
    }

    public function showImage(DomusMission $badge): BinaryFileResponse
    {
        abort_if(! $badge->image_path, 404);

        $path = storage_path('app/'.$badge->image_path);
        abort_if(! File::exists($path), 404);

        return response()->file($path, [
            'Cache-Control' => 'public, max-age=604800, immutable',
        ]);
    }
}
