<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\AcademicYear;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'school_name' => config('app.name'),
            'student_reporting_enabled' => '1',
            'academic_year' => self::currentAcademicYear(),
            // Present but unused: notifications are in-app only for now.
            'notification_email' => '',
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    public static function currentAcademicYear(): string
    {
        return AcademicYear::forDate(CarbonImmutable::now());
    }
}
