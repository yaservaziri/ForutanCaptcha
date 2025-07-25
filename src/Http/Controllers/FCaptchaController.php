<?php

namespace Forutan\Captcha\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Forutan\Captcha\Models\FCaptcha;

class FCaptchaController extends Controller
{
    protected string $context;
    public function __construct()
    {
        $this->context = session('fcaptcha_context', 'default');

        if (!$this->context) {
            abort(400, 'Missing captcha context');
        }
    }    
    public function show()
    {
        $context = $this->context;
        $token = Str::random(64);
        $salt = Str::random(64);

        $categories = FCaptcha::distinct()->pluck('category');
        abort_if($categories->isEmpty(), 500, 'No categories found.');

        $selectedCategory = $categories->random();
        $correctCount = rand(config("fcaptcha.min_correct.$context", 3), config("fcaptcha.max_correct.$context", 6));
        $availableCorrect = FCaptcha::where('category', $selectedCategory)->count();

        if ($availableCorrect < $correctCount) {
            abort(500);
        }

        $correctImages = FCaptcha::where('category', $selectedCategory)
            ->inRandomOrder()
            ->take($correctCount)
            ->get();
        $incorrectImages = FCaptcha::where('category', '!=', $selectedCategory)
            ->inRandomOrder()
            ->take(config("fcaptcha.image_count.$context", 12) - $correctCount)
            ->get();

        $shuffledImages = $correctImages->concat($incorrectImages)->shuffle();

        $answers = [];
        $images = $shuffledImages->map(function ($img) use (&$answers, $salt, $selectedCategory) {
            $payload = base64_encode("{$img->id}|$salt");
            $hash = encrypt($payload);
            $img->hash = $hash;
            if ($img->category === $selectedCategory) {
                $answers[] = $hash;
            }
            return $img;
        });

        session([
          "fcaptcha_token.$context" => $token,
          "fcaptcha_token_created_at.$context" => now(),
          "fcaptcha_salt.$context" => $salt,
          "fcaptcha_answers.$context" => $answers
        ]);

        $reference = FCaptcha::where('category', $selectedCategory)->inRandomOrder()->first();
        $refPayload = base64_encode("{$reference->id}|$salt");
        $reference->hash = encrypt($refPayload);
        
        return response()->view('fcaptcha::fcaptcha', [
            'images' => $images,
            'reference' => $reference,
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => gmdate('D, d M Y H:i:s') . ' GMT',
        ]);
    }
    public function image($hash)
    {
        try {
            $decrypted = decrypt($hash);
            [$id, $salt] = explode('|', base64_decode($decrypted));
        } catch (\Exception $e) {
            Log::warning('FCaptcha: Invalid image hash decryption.', ['hash' => $hash]);
            abort(403, 'Invalid image hash.');
        }

        $captcha = FCaptcha::find($id);
        abort_if(!$captcha, 404, 'Image not found.');

        $path = "fcaptcha/{$captcha->image}";
        abort_unless(Storage::disk('local')->exists($path), 404, 'Image file missing.');

        $imageData = Storage::disk('local')->get($path);
        $image = @imagecreatefromstring($imageData);
        abort_if(!$image, 500, 'Failed to process image.');

        $width = imagesx($image);
        $height = imagesy($image);

        // Add noise
        for ($i = 0; $i < 150; $i++) {
            $x = rand(0, $width - 1);
            $y = rand(0, $height - 1);
            $color = imagecolorallocate($image, rand(180, 255), rand(180, 255), rand(180, 255));
            imagesetpixel($image, $x, $y, $color);
        }

        // Add lines
        for ($i = 0; $i < 3; $i++) {
            $lineColor = imagecolorallocate($image, rand(100, 200), rand(100, 200), rand(100, 200));
            imageline(
                $image,
                rand(0, $width),
                rand(0, $height),
                rand(0, $width),
                rand(0, $height),
                $lineColor
            );
        }

        // Add random text
        $fontPath = config('fcaptcha.font_path', base_path('vendor/forutan/captcha/resources/fonts/lightweight.ttf'));
        abort_unless(file_exists($fontPath), 500, 'Font not found.');

        $text = Str::random(6);
        imagettftext(
            $image,
            rand(16, 20),
            rand(-15, 15),
            rand(10, max(10, $width - 120)),
            rand(26, $height - 10),
            imagecolorallocate($image, rand(0, 120), rand(0, 120), rand(0, 120)),
            $fontPath,
            $text
        );

        ob_start();
        imagejpeg($image, null, 85);
        $output = ob_get_clean();
        imagedestroy($image);
        unset($imageData, $image);

        return response($output, 200)
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'selected_images' => ['required', 'array', 'min:1'],
            'selected_images.*' => ['required', 'string'],
            'fcaptcha_token' => ['required', 'string'],
        ]);

        $context = $this->context;

        $blockKey = "fcaptcha_blocked.$context";
        if (session()->has($blockKey)) {
            $blockedUntil = session($blockKey);
            if (now()->timestamp < $blockedUntil) {
                $remaining = $blockedUntil - now()->timestamp;
                return redirect()->route('fcaptcha.show')->withErrors([
                    "Too many failed attempts. Try again in $remaining seconds."
                ]);
            } else {
                session()->forget($blockKey);
                session()->forget("fcaptcha_attempts.$context");
            }
        }

        $tokenCreatedAt = session("fcaptcha_token_created_at.$context");
        $expireDuration = config("fcaptcha.expire_minutes.$context", 2) * 60;
        if (!$tokenCreatedAt || now()->diffInSeconds($tokenCreatedAt) > $expireDuration) {
            session()->forget([
                "fcaptcha_token.$context",
                "fcaptcha_token_created_at.$context",
                "fcaptcha_salt.$context",
                "fcaptcha_answers.$context",
            ]);
            return redirect()->route('fcaptcha.show')->withErrors(['Captcha expired, please try again.']);
        }

        $token = session("fcaptcha_token.$context");
        $answers = session("fcaptcha_answers.$context", []);
        if (!$token || $request->input('fcaptcha_token') !== $token) {
            return redirect()->route('fcaptcha.show')->withErrors(['Invalid captcha session token.']);
        }

        $selectedHashes = array_unique($request->input('selected_images', []));

        if (empty(array_diff($selectedHashes, $answers)) && empty(array_diff($answers, $selectedHashes))) {
            session(["fcaptcha_passed.$context" => true]);
            session()->forget([
                "fcaptcha_token.$context",
                "fcaptcha_token_created_at.$context",
                "fcaptcha_salt.$context",
                "fcaptcha_answers.$context",
                "fcaptcha_attempts.$context",
                "fcaptcha_blocked.$context",
            ]);
            return redirect()->intended(config("fcaptcha.redirect_on_pass.$context", '/'))
                ->with('message', 'Verification passed!');
        }

        $attempts = session()->increment("fcaptcha_attempts.$context");

        $maxAttempts = config("fcaptcha.max_attempts.$context", 3);
        if ($attempts >= $maxAttempts) {
            $blockDuration = config("fcaptcha.block_duration_minutes.$context", 60);
            session([$blockKey => now()->addMinutes($blockDuration)->timestamp]);
            return redirect()->route('fcaptcha.show')->withErrors([
                "Too many failed attempts. You are blocked for $blockDuration minutes."
            ]);
        }

        return redirect()->route('fcaptcha.show')->withErrors([
            "Incorrect selection. Attempt $attempts of $maxAttempts."
        ]);
    }
}
