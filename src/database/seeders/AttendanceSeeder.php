<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AttendanceSeeder extends Seeder
{
    public function run()
    {
        // 一般ユーザー6名を作成
        $users = [];
        $names = ['山田太郎', '佐藤花子', '鈴木一郎', '田中美咲', '伊藤健太', '中村優子'];
        
        foreach ($names as $index => $name) {
            $users[] = User::create([
                'name' => $name,
                'email' => 'user' . ($index + 1) . '@example.com',
                'password' => Hash::make('password123'),
                'role' => 'general',
                'email_verified_at' => now(),
            ]);
        }

        // 2025年11月1日〜2025年11月27日の勤怠データを作成
        $startDate = Carbon::create(2025, 11, 1);
        $endDate = Carbon::create(2025, 11, 27);

        foreach ($users as $user) {
            $currentDate = $startDate->copy();
            
            while ($currentDate->lte($endDate)) {
                $dayOfWeek = $currentDate->dayOfWeek;
                
                // 土日はスキップ
                if ($dayOfWeek === Carbon::SUNDAY || $dayOfWeek === Carbon::SATURDAY) {
                    $currentDate->addDay();
                    continue;
                }
                
                // 通常の勤怠パターン（9:00-18:00、1時間休憩）
                $clockIn = Carbon::createFromTime(9, 0, 0);
                $clockOut = Carbon::createFromTime(18, 0, 0);
                
                // ランダムなバリエーションを追加
                $clockInVariation = rand(-30, 30); // -30分〜+30分の変動
                $clockOutVariation = rand(-30, 30);
                $breakVariation = rand(-15, 15);
                
                $clockIn->addMinutes($clockInVariation);
                $clockOut->addMinutes($clockOutVariation);
                
                $attendance = Attendance::create([
                    'user_id' => $user->id,
                    'date' => $currentDate->toDateString(),
                    'clock_in' => $clockIn->format('H:i:s'),
                    'clock_out' => $clockOut->format('H:i:s'),
                    'note' => null,
                ]);
                
                // 休憩時間を追加（12:00-13:00 + バリエーション）
                $breakStart = Carbon::createFromTime(12, 0, 0)->addMinutes($breakVariation);
                $breakEnd = $breakStart->copy()->addHour();
                
                BreakTime::create([
                    'attendance_id' => $attendance->id,
                    'break_start' => $breakStart->format('H:i:s'),
                    'break_end' => $breakEnd->format('H:i:s'),
                ]);
                
                $currentDate->addDay();
            }
        }
    }
}

