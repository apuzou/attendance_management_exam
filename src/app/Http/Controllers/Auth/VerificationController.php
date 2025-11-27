<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerificationCodeRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;
use Carbon\Carbon;

class VerificationController extends Controller
{
    public function show()
    {
        if (Auth::check() && Auth::user()->email_verified_at) {
            return redirect()->route('attendance.index');
        }

        return view('auth.verify-email');
    }

    public function showCodeInput()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        if (Auth::user()->email_verified_at) {
            return redirect()->route('attendance.index');
        }

        return view('auth.verify-code');
    }

    public function verify(VerificationCodeRequest $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->verification_code) {
            return back()->withErrors(['verification_code' => '認証コードが見つかりません。再送信してください。']);
        }

        if ($user->verification_code !== $request->verification_code) {
            return back()->withErrors(['verification_code' => '認証コードが一致しません。']);
        }

        if ($user->verification_code_expires_at && Carbon::now()->gt($user->verification_code_expires_at)) {
            return back()->withErrors(['verification_code' => '認証コードの有効期限が切れています。再送信してください。']);
        }

        $user->update([
            'email_verified_at' => now(),
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ]);

        return redirect()->route('attendance.index')->with('success', 'メール認証が完了しました。');
    }

    public function resend()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->email_verified_at) {
            return redirect()->route('attendance.index');
        }

        $verificationCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'verification_code' => $verificationCode,
            'verification_code_expires_at' => now()->addMinutes(30),
        ]);

        Mail::to($user->email)->send(new VerificationCodeMail($user, $verificationCode));

        return back()->with('success', '認証メールを再送信しました。');
    }
}

