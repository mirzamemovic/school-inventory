<?php

namespace Database\Seeders;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Room;
use App\Models\User;
use App\Services\AdminAccounts;
use App\Services\AuditLogger;
use App\Support\Actor;
use App\Support\AdminPassword;
use App\Support\EventAction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * The two things the one-day video demo needs on top of the demo data
 * (VIDEO-DEMO.md). docker/start.sh runs this each time the container starts,
 * because the password and the asset name are only known to the host. Nothing
 * else calls it, and the school's real deployment never does.
 */
class VideoDemoSeeder extends Seeder
{
    /**
     * Already printed on a sticker, so it has to be this tag and no other. The
     * demo data stops at sequence 63, which leaves this one free.
     */
    private const STICKER_TAG = 'INV-GEN-0064';

    private const STICKER_SEQUENCE = 64;

    public function run(AuditLogger $log, AdminAccounts $accounts): void
    {
        $this->sticker($log);
        $this->account($accounts);
    }

    private function sticker(AuditLogger $log): void
    {
        if (Asset::query()->where('asset_tag', self::STICKER_TAG)->exists()) {
            return;
        }

        $room = Room::query()->where('name', 'Computer lab')->firstOrFail();

        $asset = new Asset([
            'name' => env('DEMO_ASSET_NAME') ?: 'Computer lab PC',
            'category_id' => Category::query()->where('name', 'Computer')->value('id'),
            'room_id' => $room->id,
            'status' => AssetStatus::InUse,
            'condition' => AssetCondition::Good,
        ]);

        // Set by the tag generator everywhere else, and never mass-assigned.
        $asset->forceFill([
            'asset_tag' => self::STICKER_TAG,
            'tag_sequence' => self::STICKER_SEQUENCE,
        ])->save();

        $log->record($asset, EventAction::ASSET_CREATED, [
            'name' => $asset->name,
            'room' => $room->name,
        ], Actor::system());
    }

    /**
     * A public address needs a real password, so there is no default one. A
     * refused password leaves the public pages running: the log says why, and
     * the fix is to set a better one and restart.
     */
    private function account(AdminAccounts $accounts): void
    {
        $email = Str::lower(trim((string) env('DEMO_ADMIN_EMAIL')));
        $password = (string) env('DEMO_ADMIN_PASSWORD');

        if ($email === '' || $password === '') {
            $this->command?->warn('No admin account: DEMO_ADMIN_EMAIL or DEMO_ADMIN_PASSWORD is not set.');

            return;
        }

        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            return;
        }

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => AdminPassword::rules($email)],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->command?->warn("No admin account: {$error}");
            }

            return;
        }

        $accounts->createFromConsole(['name' => 'Director', 'email' => $email], $password);
    }
}
