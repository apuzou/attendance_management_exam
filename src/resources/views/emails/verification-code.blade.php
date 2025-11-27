<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メール認証コード</title>
</head>
<body>
    <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #000; padding-bottom: 10px; display: inline-block;">
            COACHTECH
        </h1>

        <div style="margin-top: 30px;">
            <p>{{ $userName }}様</p>

            <p>お疲れさまです。</p>

            <p>勤怠管理システムへのご登録ありがとうございます。</p>

            <p>メール認証を完了するために、以下の認証コードを入力してください。</p>

            <div style="background-color: #f5f5f5; border: 2px solid #000; border-radius: 8px; padding: 20px; text-align: center; margin: 30px 0;">
                <div style="font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #000;">
                    {{ $verificationCode }}
                </div>
            </div>

            <p>このコードは30分間有効です。</p>

            <p style="color: #666; font-size: 12px; margin-top: 30px;">
                このメールに心当たりがない場合は、このメールを無視してください。
            </p>
        </div>
    </div>
</body>
</html>

