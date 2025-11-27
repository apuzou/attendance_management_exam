<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerificationCodeRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;

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

