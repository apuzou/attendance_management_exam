<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * 勤怠CSVサービス
 *
 * 月次勤怠のCSV出力を行う。
 */
class AttendanceCsvService
{
    /**
     * 月次勤怠のCSVを生成する。
     *
     * @param Collection $attendances 勤怠コレクション
     * @param Carbon $month 対象月
     * @param User $targetUser 対象ユーザー
     * @return string CSV文字列（UTF-8 BOM付き）
     */
    public function generateMonthlyCsv(Collection $attendances, Carbon $month, User $targetUser): string
    {
        $csvData = [];
        $csvData[] = ['日付', '出勤時刻', '退勤時刻', '休憩時間', '実働時間'];

        $daysInMonth = $month->daysInMonth;
        $firstDay = $month->copy()->startOfMonth();

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDate = $firstDay->copy()->addDays($day - 1);
            $attendance = $attendances->first(function ($att) use ($currentDate) {
                return $att->date->format('Y-m-d') === $currentDate->format('Y-m-d');
            });

            $csvData[] = [
                $currentDate->format('Y-m-d'),
                $attendance && $attendance->clock_in ? date('H:i', strtotime($attendance->clock_in)) : '',
                $attendance && $attendance->clock_out ? date('H:i', strtotime($attendance->clock_out)) : '',
                $attendance ? $attendance->getTotalBreakTime() : '',
                $attendance ? $attendance->getWorkTime() : '',
            ];
        }

        $output = fopen('php://temp', 'r+');
        fwrite($output, "\xEF\xBB\xBF");

        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }

    /**
     * 月次勤怠CSVのファイル名を生成する。
     *
     * @param int $userId 対象ユーザーID
     * @param Carbon $month 対象月
     * @return string ファイル名
     */
    public function generateFilename(int $userId, Carbon $month): string
    {
        return 'attendance_' . $userId . '_' . $month->format('Ymd') . '.csv';
    }
}
