<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    /**
     * ユーザーのダミーデータを作成
     * 7名のユーザー（フルアクセス権限1名、部門アクセス権限2名、一般ユーザー4名）と
     * 2025年11月分の勤怠データを含む
     */
    public function run()
    {
        // 7名のユーザーを作成
        $users = [
            [
                'name' => '高知太郎',
                'email' => 'admin@example.com',
                'password' => 'password123',
                'role' => 'admin',
                'department_code' => 1, // フルアクセス権限
            ],
            [
                'name' => '佐藤花子',
                'email' => 'a_admin@example.com',
                'password' => 'password123',
                'role' => 'admin',
                'department_code' => 2, // A部門管理者（部門アクセス権限）
            ],
            [
                'name' => '鈴木一郎',
                'email' => 'b_admin@example.com',
                'password' => 'password123',
                'role' => 'admin',
                'department_code' => 3, // B部門管理者（部門アクセス権限）
            ],
            [
                'name' => '田中太郎',
                'email' => 'tanaka@example.com',
                'password' => 'password123',
                'role' => 'general',
                'department_code' => 2, // A部門一般ユーザー
            ],
            [
                'name' => '山田花子',
                'email' => 'yamada@example.com',
                'password' => 'password123',
                'role' => 'general',
                'department_code' => 2, // A部門一般ユーザー
            ],
            [
                'name' => '高橋次郎',
                'email' => 'takahashi@example.com',
                'password' => 'password123',
                'role' => 'general',
                'department_code' => 3, // B部門一般ユーザー
            ],
            [
                'name' => '伊藤三郎',
                'email' => 'ito@example.com',
                'password' => 'password123',
                'role' => 'general',
                'department_code' => 3, // B部門一般ユーザー
            ],
        ];

        $createdUsers = [];

        // 各ユーザーデータを処理（既存の場合は取得、新規の場合は作成）
        foreach ($users as $userData) {
            // メールアドレスをキーにユーザーを取得または作成（重複防止）
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'role' => $userData['role'],
                    'department_code' => $userData['department_code'],
                    'email_verified_at' => now(), // シーダーで作成するユーザーは認証済みとする
                ]
            );

            // 既存ユーザーの場合、パスワードとdepartment_codeを更新（再実行時に最新の設定を反映）
            if ($user->wasRecentlyCreated === false) {
                $user->update([
                    'password' => Hash::make($userData['password']),
                    'department_code' => $userData['department_code'],
                ]);
            }

            // 作成・取得したユーザー情報を配列に保存（後で勤怠データ作成時に使用）
            $createdUsers[] = [
                'user' => $user,
                'login_info' => $userData,
            ];
        }

        // 2025年11月の勤怠データを作成（各ユーザーに対して）
        $startDate = Carbon::create(2025, 11, 1);
        $endDate = Carbon::create(2025, 11, 30);

        foreach ($createdUsers as $userData) {
            $user = $userData['user'];
            
            // 土日を含めて合計9日間の休日をランダムに選択（ユーザーごとに異なる休日パターン）
            $holidays = $this->getRandomHolidays($startDate, $endDate, 9);
            
            // 11月1日から11月30日まで1日ずつ処理
            $currentDate = $startDate->copy();
            
            while ($currentDate->lte($endDate)) {
                // 選択された休日に含まれている場合はスキップ（勤怠データを作成しない）
                if (in_array($currentDate->format('Y-m-d'), $holidays)) {
                    $currentDate->addDay();
                    continue;
                }
                
                // 出勤時刻: 9:00（固定）
                $clockIn = Carbon::createFromTime(9, 0, 0);
                
                // 退勤時刻: 18:00（固定）
                $clockOut = Carbon::createFromTime(18, 0, 0);
                
                // 勤怠レコードを作成（出勤・退勤時刻を含む）
                $attendance = Attendance::create([
                    'user_id' => $user->id,
                    'date' => $currentDate->toDateString(),
                    'clock_in' => $clockIn->format('H:i:s'),
                    'clock_out' => $clockOut->format('H:i:s'),
                    'note' => null,
                ]);
                
                // 休憩時間を2〜3回で合計1時間（60分）になるように作成（出勤と退勤の間に均等に分散）
                $this->createBreakTimes($attendance, $clockIn, $clockOut);
                
                // 次の日に進む
                $currentDate->addDay();
            }
        }

        // 作成したユーザーのログイン情報をコンソールに表示（テスト用）
        echo "\n=== ユーザーログイン情報 ===\n";
        
        // フルアクセス権限のユーザー情報を表示（department_code=1の管理者）
        echo "\n【フルアクセス権限】\n";
        foreach ($createdUsers as $userData) {
            if ($userData['login_info']['department_code'] === 1) {
                echo "名前: {$userData['login_info']['name']}\n";
                echo "メールアドレス: {$userData['login_info']['email']}\n";
                echo "パスワード: {$userData['login_info']['password']}\n";
                echo "権限: フルアクセス（全ユーザーの勤怠を参照・修正・承認可能）\n";
                echo "---\n";
            }
        }
        
        // 部門アクセス権限の管理者情報を表示（department_code!=1の管理者）
        echo "\n【部門アクセス権限（管理者）】\n";
        foreach ($createdUsers as $userData) {
            if ($userData['login_info']['role'] === 'admin' && $userData['login_info']['department_code'] !== 1) {
                echo "名前: {$userData['login_info']['name']}\n";
                echo "メールアドレス: {$userData['login_info']['email']}\n";
                echo "パスワード: {$userData['login_info']['password']}\n";
                echo "権限: 部門管理者（同じ部門のメンバーの勤怠を参照・修正可能）\n";
                echo "部門コード: {$userData['login_info']['department_code']}\n";
                echo "---\n";
            }
        }
        
        // 一般ユーザー情報を表示
        echo "\n【一般ユーザー】\n";
        foreach ($createdUsers as $userData) {
            if ($userData['login_info']['role'] === 'general') {
                echo "名前: {$userData['login_info']['name']}\n";
                echo "メールアドレス: {$userData['login_info']['email']}\n";
                echo "パスワード: {$userData['login_info']['password']}\n";
                echo "部門コード: {$userData['login_info']['department_code']}\n";
                echo "---\n";
            }
        }
        echo "\n";
    }

    /**
     * ランダムな休日を取得（土日を含めて合計指定数だけ選択）
     * 
     * @param Carbon $startDate 開始日
     * @param Carbon $endDate 終了日
     * @param int $count 選択する休日の日数
     * @return array 休日の日付文字列配列（Y-m-d形式）
     */
    private function getRandomHolidays(Carbon $startDate, Carbon $endDate, int $count): array
    {
        $holidays = [];
        $currentDate = $startDate->copy();
        
        // 期間内の全ての日付を土日と平日に分類
        $weekendDays = []; // 土日のリスト
        $weekdayDays = []; // 平日のリスト
        
        while ($currentDate->lte($endDate)) {
            $dayOfWeek = $currentDate->dayOfWeek;
            $dateString = $currentDate->format('Y-m-d');
            
            // 土日かどうかで分類
            if ($dayOfWeek === Carbon::SUNDAY || $dayOfWeek === Carbon::SATURDAY) {
                $weekendDays[] = $dateString;
            } else {
                $weekdayDays[] = $dateString;
            }
            
            $currentDate->addDay();
        }
        
        // まず土日を全て休日リストに追加（必ず含める）
        $holidays = $weekendDays;
        
        // 土日が既に指定数以上の場合、土日からランダムに指定数だけ選択して返す
        if (count($holidays) >= $count) {
            shuffle($holidays); // ランダムに並び替え
            return array_slice($holidays, 0, $count);
        }
        
        // 残りの必要数を平日からランダムに選択して追加
        $remainingCount = $count - count($holidays);
        shuffle($weekdayDays); // ランダムに並び替え
        $additionalHolidays = array_slice($weekdayDays, 0, $remainingCount);
        
        // 土日とランダムに選択された平日を合併して返す
        $holidays = array_merge($holidays, $additionalHolidays);
        
        return $holidays;
    }

    /**
     * 休憩時間を作成（2〜3回で合計60分）
     * 出勤時刻と退勤時刻の間に均等に分散して配置
     * 
     * @param Attendance $attendance 勤怠レコード
     * @param Carbon $clockIn 出勤時刻（使用しないが、将来的な拡張のため保持）
     * @param Carbon $clockOut 退勤時刻（使用しないが、将来的な拡張のため保持）
     * @return void
     */
    private function createBreakTimes(Attendance $attendance, Carbon $clockIn, Carbon $clockOut): void
    {
        // 休憩回数をランダムに決定（2回または3回）
        $breakCount = rand(2, 3);
        
        // 合計60分になるように休憩時間を配分（パターンからランダムに選択）
        $breakDurations = [];
        if ($breakCount === 2) {
            // 2回の場合: 合計60分になるパターン（30分+30分、20分+40分、25分+35分）
            $patterns = [
                [30, 30],
                [20, 40],
                [25, 35],
            ];
            $breakDurations = $patterns[array_rand($patterns)];
        } else {
            // 3回の場合: 合計60分になるパターン（20分+20分+20分、15分+20分+25分など）
            $patterns = [
                [20, 20, 20],
                [15, 20, 25],
                [15, 15, 30],
                [10, 25, 25],
            ];
            $breakDurations = $patterns[array_rand($patterns)];
        }
        
        // 出勤時刻と退勤時刻を分に変換（9:00 = 540分、18:00 = 1080分）
        $workStartMinutes = 540; // 9:00
        $workEndMinutes = 1080; // 18:00
        
        // 休憩時間の開始時刻を適切に分散して配置するための計算
        $totalBreakMinutes = array_sum($breakDurations); // 60分（合計休憩時間）
        $availableMinutes = $workEndMinutes - $workStartMinutes - $totalBreakMinutes; // 480分（実働時間）
        
        // 休憩間の間隔を計算（均等に分散するため、休憩回数+1で割る）
        // 例: 2回の休憩の場合、開始前・休憩1と2の間・休憩2と終了の間の3区間に分ける
        $interval = floor($availableMinutes / ($breakCount + 1));
        
        // 最初の休憩開始位置を計算（出勤時刻から最初の間隔分だけ進んだ位置）
        $currentMinute = $workStartMinutes + $interval;
        
        // 各休憩時間を作成
        foreach ($breakDurations as $duration) {
            $breakStart = $currentMinute;
            $breakEnd = $breakStart + $duration;
            
            // 分から時分秒形式に変換（Carbonで時刻オブジェクトを作成）
            $breakStartTime = Carbon::createFromTime(0, 0, 0)->addMinutes($breakStart);
            $breakEndTime = Carbon::createFromTime(0, 0, 0)->addMinutes($breakEnd);
            
            // 休憩時間レコードを作成
            BreakTime::create([
                'attendance_id' => $attendance->id,
                'break_start' => $breakStartTime->format('H:i:s'),
                'break_end' => $breakEndTime->format('H:i:s'),
            ]);
            
            // 次の休憩位置を計算（現在の休憩終了後、間隔を空ける）
            $currentMinute = $breakEnd + $interval;
        }
    }
}
