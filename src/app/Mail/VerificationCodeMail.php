<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * メール認証コードメール
 *
 * ユーザー登録後のメール認証コード送信用メールクラス。
 */
class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var User 対象ユーザー */
    public $user;

    /** @var string 認証コード */
    public $verificationCode;

    /**
     * コンストラクタ
     *
     * @param User $user 対象ユーザー
     * @param string $verificationCode 認証コード
     */
    public function __construct(User $user, string $verificationCode)
    {
        $this->user = $user;
        $this->verificationCode = $verificationCode;
    }

    /**
     * メールの内容を構築する。
     *
     * @return \Illuminate\Mail\Mailable
     */
    public function build()
    {
        return $this->subject('【COACHTECH】メール認証コード')
            ->view('emails.verification-code')
            ->with([
                'verificationCode' => $this->verificationCode,
                'userName' => $this->user->name,
            ]);
    }
}

