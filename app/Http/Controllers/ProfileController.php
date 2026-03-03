<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Update the user's profile image.
     */
    public function updateImage(Request $request): RedirectResponse
    {
        $request->validate([
            'profile_image' => ['required', 'image', 'max:5120'],
        ]);

        $user = $request->user();

        // Delete old image if exists
        if ($user->profile_image) {
            Storage::disk('private')->delete($user->profile_image);
        }

        // Resize to 200x200 square (center crop) using GD
        $file = $request->file('profile_image');
        $src = imagecreatefromstring(file_get_contents($file->getRealPath()));
        $srcW = imagesx($src);
        $srcH = imagesy($src);

        // Center-crop to square
        $cropSize = min($srcW, $srcH);
        $srcX = intdiv($srcW - $cropSize, 2);
        $srcY = intdiv($srcH - $cropSize, 2);

        $dst = imagecreatetruecolor(200, 200);
        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, 200, 200, $cropSize, $cropSize);
        imagedestroy($src);

        ob_start();
        imagejpeg($dst, null, 90);
        $imageData = ob_get_clean();
        imagedestroy($dst);

        $path = 'profile_images/' . uniqid() . '.jpg';
        Storage::disk('private')->put($path, $imageData);
        $user->update(['profile_image' => $path]);

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Remove the user's profile image.
     */
    public function destroyImage(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->profile_image) {
            Storage::disk('private')->delete($user->profile_image);
            $user->update(['profile_image' => null]);
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Serve a user's profile image.
     */
    public function showImage(User $user)
    {
        if (!$user->profile_image || !Storage::disk('private')->exists($user->profile_image)) {
            abort(404);
        }

        return response(Storage::disk('private')->get($user->profile_image))
            ->header('Content-Type', Storage::disk('private')->mimeType($user->profile_image))
            ->header('Cache-Control', 'private, max-age=86400');
    }

    /**
     * Invalidate all of the user's sessions, including the current one.
     */
    public function destroySessions(Request $request): RedirectResponse
    {
        $userId = $request->user()->id;

        DB::table('sessions')->where('user_id', $userId)->delete();

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::route('login');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
