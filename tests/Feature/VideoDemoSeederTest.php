<?php

use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\Room;
use App\Models\User;
use App\Services\AssetTagGenerator;
use App\Support\EventAction;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RoomSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\VideoDemoSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

const VIDEO_DEMO_ENV = ['DEMO_ADMIN_EMAIL', 'DEMO_ADMIN_PASSWORD', 'DEMO_ASSET_NAME'];

function videoDemoEnv(array $values): void
{
    foreach ($values as $name => $value) {
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
}

beforeEach(function () {
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);

    $this->seed([CategorySeeder::class, RoomSeeder::class]);
    Room::factory()->create(['name' => 'Computer lab']);
});

afterEach(function () {
    foreach (VIDEO_DEMO_ENV as $name) {
        unset($_ENV[$name], $_SERVER[$name]);
    }
});

it('gives the printed sticker a real asset, under exactly its tag', function () {
    $this->seed(VideoDemoSeeder::class);

    $asset = Asset::query()->where('asset_tag', 'INV-GEN-0064')->firstOrFail();

    expect($asset->tag_sequence)->toBe(64)
        ->and($asset->room->name)->toBe('Computer lab')
        ->and($asset->category->name)->toBe('Computer');

    $this->get('/a/INV-GEN-0064')->assertOk()->assertSee('Computer lab');

    expect(AssetEvent::query()
        ->where('subject_id', $asset->id)
        ->where('action', EventAction::ASSET_CREATED)
        ->exists())->toBeTrue();
});

it('fits straight after the demo data, and new tags carry on from it', function () {
    $this->seed([SettingSeeder::class, DemoSeeder::class, VideoDemoSeeder::class]);

    expect(Asset::query()->count())->toBe(64)
        ->and(app(AssetTagGenerator::class)->nextSequence())->toBe(65);
});

it('uses the name chosen for the video', function () {
    videoDemoEnv(['DEMO_ASSET_NAME' => 'Dell OptiPlex 7010']);

    $this->seed(VideoDemoSeeder::class);

    $this->get('/a/INV-GEN-0064')->assertOk()->assertSee('Dell OptiPlex 7010');
});

it('changes nothing when the container starts again', function () {
    videoDemoEnv(['DEMO_ADMIN_EMAIL' => 'director@example.com', 'DEMO_ADMIN_PASSWORD' => 'correct horse battery staple']);

    $this->seed(VideoDemoSeeder::class);
    $this->seed(VideoDemoSeeder::class);

    expect(Asset::query()->where('asset_tag', 'INV-GEN-0064')->count())->toBe(1)
        ->and(User::query()->count())->toBe(1);
});

it('makes the admin account from the host settings', function () {
    videoDemoEnv(['DEMO_ADMIN_EMAIL' => ' Director@Example.com ', 'DEMO_ADMIN_PASSWORD' => 'correct horse battery staple']);

    $this->seed(VideoDemoSeeder::class);

    $user = User::query()->sole();

    expect($user->email)->toBe('director@example.com')
        ->and(Hash::check('correct horse battery staple', $user->password))->toBeTrue();
});

it('makes no account from a weak password, and still finishes', function () {
    videoDemoEnv(['DEMO_ADMIN_EMAIL' => 'director@example.com', 'DEMO_ADMIN_PASSWORD' => 'short']);

    $this->seed(VideoDemoSeeder::class);

    expect(User::query()->count())->toBe(0)
        ->and(Asset::query()->where('asset_tag', 'INV-GEN-0064')->exists())->toBeTrue();
});

it('makes no account when none is configured, so there is never a default password', function () {
    $this->seed(VideoDemoSeeder::class);

    expect(User::query()->count())->toBe(0);
});
