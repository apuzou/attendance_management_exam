<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class GeneralUserSeeder extends Seeder
{
    /**
     * 一般ユーザーのダミーデータを作成
     * 2025年11月分の勤怠データを含む
     */
    public function run()
    {
        // 3名の一般ユーザーを作成
        $users = [
            [
                'name' => '前田聖太',
                'email' => 'maeda@example.com',
                'password' => 'password123',
            ],
            [
                'name' => '佐藤花子',
                'email' => 'sato@example.com',
                'password' => 'password123',
            ],
            [
                'name' => '鈴木一郎',
                'email' => 'suzuki@example.com',
                'password' => 'password123',
            ],
        ];

        $createdUsers = [];

        foreach ($users as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'role' => 'general',
                'email_verified_at' => now(),
            ]);

            $createdUsers[] = [
                'user' => $user,
                'login_info' => $userData,
            ];
        }

        // 2025年11月の勤怠データを作成
        $startDate = Carbon::create(2025, 11, 1);
        $endDate = Carbon::create(2025, 11, 30);

        foreach ($createdUsers as $userData) {
            $user = $userData['user'];
            
            // 土日を含めて合計9日間の休日を選択
            $holidays = $this->getRandomHolidays($startDate, $endDate, 9);
            
            $currentDate = $startDate->copy();
            
            while ($currentDate->lte($endDate)) {
                // 選択された休日に含まれている場合はスキップ
                if (in_array($currentDate->format('Y-m-d'), $holidays)) {
                    $currentDate->addDay();
                    continue;
                }
                
                // 出勤時刻: 9:00（固定）
                $clockIn = Carbon::createFromTime(9, 0, 0);
                
                // 退勤時刻: 18:00（固定）
                $clockOut = Carbon::createFromTime(18, 0, 0);
                
                $attendance = Attendance::create([
                    'user_id' => $user->id,
                    'date' => $currentDate->toDateString(),
                    'clock_in' => $clockIn->format('H:i:s'),
                    'clock_out' => $clockOut->format('H:i:s'),
                    'note' => null,
                ]);
                
                // 休憩時間を2〜3回で合計1時間（60分）になるように作成
                $this->createBreakTimes($attendance, $clockIn, $clockOut);
                
                $currentDate->addDay();
            }
        }

        // ログイン情報を表示
        echo "\n=== 一般ユーザーログイン情報 ===\n";
        foreach ($createdUsers as $userData) {
            echo "名前: {$userData['login_info']['name']}\n";
            echo "メールアドレス: {$userData['login_info']['email']}\n";
            echo "パスワード: {$userData['login_info']['password']}\n";
            echo "---\n";
        }
        echo "\n";
    }

    /**
     * ランダムな休日を取得（土日を含めて合計指定数だけ選択）
     */
    private function getRandomHolidays(Carbon $startDate, Carbon $endDate, int $count): array
    {
        $holidays = [];
        $currentDate = $startDate->copy();
        
        // まず土日を全て休日リストに追加
        $weekendDays = [];
        $weekdayDays = [];
        
        while ($currentDate->lte($endDate)) {
            $dayOfWeek = $currentDate->dayOfWeek;
            $dateString = $currentDate->format('Y-m-d');
            
            if ($dayOfWeek === Carbon::SUNDAY || $dayOfWeek === Carbon::SATURDAY) {
                $weekendDays[] = $dateString;
            } else {
                $weekdayDays[] = $dateString;
            }
            
            $currentDate->addDay();
        }
        
        // 土日を休日リストに追加
        $holidays = $weekendDays;
        
        // 土日が既に指定数以上の場合、指定数のみ返す
        if (count($holidays) >= $count) {
            shuffle($holidays);
            return array_slice($holidays, 0, $count);
        }
        
        // 残りの必要数を平日からランダムに選択
        $remainingCount = $count - count($holidays);
        shuffle($weekdayDays);
        $additionalHolidays = array_slice($weekdayDays, 0, $remainingCount);
        
        // 土日とランダムに選択された平日を合併
        $holidays = array_merge($holidays, $additionalHolidays);
        
        return $holidays;
    }

    /**
     * 休憩時間を作成（2〜3回で合計60分）
     */
    private function createBreakTimes(Attendance $attendance, Carbon $clockIn, Carbon $clockOut): void
    {
        // 休憩回数をランダムに決定（2回または3回）
        $breakCount = rand(2, 3);
        
        // 合計60分になるように休憩時間を配分
        $breakDurations = [];
        if ($breakCount === 2) {
            // 2回の場合: 30分 + 30分、または 20分 + 40分
            $patterns = [
                [30, 30],
                [20, 40],
                [25, 35],
            ];
            $breakDurations = $patterns[array_rand($patterns)];
        } else {
            // 3回の場合: 20分 + 20分 + 20分、または 15分 + 20分 + 25分
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
        
        // 休憩時間の開始時刻を適切に分散して配置
        $breakTimes = [];
        $totalBreakMinutes = array_sum($breakDurations); // 60分
        $availableMinutes = $workEndMinutes - $workStartMinutes - $totalBreakMinutes; // 480分
        
        // 休憩間の間隔を計算（均等に分散）
        $interval = floor($availableMinutes / ($breakCount + 1));
        
        $currentMinute = $workStartMinutes + $interval;
        foreach ($breakDurations as $duration) {
            $breakStart = $currentMinute;
            $breakEnd = $breakStart + $duration;
            
            // 休憩時間を作成
            $breakStartTime = Carbon::createFromTime(0, 0, 0)->addMinutes($breakStart);
            $breakEndTime = Carbon::createFromTime(0, 0, 0)->addMinutes($breakEnd);
            
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

