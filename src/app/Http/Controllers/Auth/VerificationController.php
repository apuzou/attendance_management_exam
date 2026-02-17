<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerificationCodeRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;

/**
 * メール認証コントローラ
 *
 * メール認証コードの送信・確認処理を行う。
 */
class VerificationController extends Controller
{
    /**
     * メール認証通知画面を表示する。
     *
     * 既に認証済みの場合は勤怠画面にリダイレクトする。
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function show()
    {
        if (Auth::check() && Auth::user()->email_verified_at) {
            return redirect()->route('attendance.index');
        }

        return view('auth.verify-email');
    }

    /**
     * 認証コード入力画面を表示する。
     *
     * 未認証の一般ユーザーを認証コード入力画面に誘導する。
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
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

    /**
     * 認証コードを確認してメール認証を完了する。
     *
     * 認証コードが正しい場合、email_verified_atを設定する。
     *
     * @param VerificationCodeRequest $request 認証コードリクエスト
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verify(VerificationCodeRequest $request)
    {
        $user = Auth::user();

        $user->update([
            'email_verified_at' => now(),
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ]);

        return redirect()->route('attendance.index')->with('success', 'メール認証が完了しました。');
    }

    /**
     * 認証メールを再送信する。
     *
     * 新しい認証コードを生成し、メールを送信する。
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resend()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if ($user->email_verified_at) {
            return redirect()->route('attendance.index');
        }

        // 6桁の認証コードを生成
        $verificationCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // 認証コードと有効期限（30分）を保存
        $user->update([
            'verification_code' => $verificationCode,
            'verification_code_expires_at' => now()->addMinutes(30),
        ]);

        Mail::to($user->email)->send(new VerificationCodeMail($user, $verificationCode));

        return back()->with('success', '認証メールを再送信しました。');
    }
}

