<?php

namespace Database\Seeders;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\FundingSource;
use App\Enums\InspectionResult;
use App\Enums\LicenseModel;
use App\Enums\LicenseType;
use App\Enums\LoanCondition;
use App\Enums\PersonType;
use App\Enums\ReporterType;
use App\Enums\ReportStatus;
use App\Enums\RoomType;
use App\Enums\SupplyUnit;
use App\Models\Asset;
use App\Models\BookLoan;
use App\Models\BookTitle;
use App\Models\Category;
use App\Models\License;
use App\Models\Person;
use App\Models\Report;
use App\Models\Room;
use App\Models\RoomContent;
use App\Models\Supply;
use App\Services\AssetTagGenerator;
use App\Services\AuditLogger;
use App\Services\InspectionRecorder;
use App\Support\AcademicYear;
use App\Support\Actor;
use App\Support\EventAction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * A whole school, invented.
 *
 * Every person here is fictional and says so: each name ends with "(demo)", so
 * nobody can mistake a seeded row for a real student. That matters more than it
 * sounds — this data is meant to be shown to people, and a plausible-looking
 * child's name in a screenshot is a small disclosure incident of its own.
 *
 * Run it explicitly and never as part of DatabaseSeeder:
 *
 *     php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    /** Suffixed to every invented person. Also makes the rows trivial to find and remove. */
    private const FICTIONAL = ' (demo)';

    private const CLASSES = ['10a', '10b', '10c', '11a', '11b', '11c', '12a', '12b', '12c', '12d'];

    public function run(): void
    {
        $this->guardAgainstProduction();

        $rooms = $this->rooms();
        $staff = $this->staff();
        $students = $this->students();
        $assets = $this->assets($rooms, $staff);

        $this->furniture($rooms);
        $this->supplies($rooms);
        $this->books($students);
        $this->reports($assets);
        $this->licenses($assets);
        $this->inspections($assets);

        $this->command?->info('Demo data seeded. Every person is fictional and marked "(demo)".');
    }

    /**
     * The whole point of a demo fixture is that it is fake, which makes it the
     * last thing that should ever reach a live school database.
     */
    private function guardAgainstProduction(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'DemoSeeder must never run in production. It creates fictional students, '
                .'assets and reports that would be indistinguishable from real records once mixed in.'
            );
        }
    }

    /** @return Collection<string, Room> */
    private function rooms(): Collection
    {
        $rooms = [
            ['Room 8', 'First', RoomType::Classroom],
            ['Room 9', 'First', RoomType::Classroom],
            ['Room 10', 'First', RoomType::Classroom],
            ['Room 11', 'Second', RoomType::Classroom],
            ['Room 12', 'Second', RoomType::Classroom],
            ['Physics lab', 'Ground', RoomType::Lab],
            ['Computer lab', 'Ground', RoomType::Lab],
            ['Director office', 'Ground', RoomType::Office],
            ['Secretary office', 'Ground', RoomType::Office],
            ['Store room', 'Ground', RoomType::Storage],
            ['Main corridor', 'Ground', RoomType::Corridor],
        ];

        // Eleven here plus the off-site room RoomSeeder already creates: twelve.
        return collect($rooms)
            ->mapWithKeys(fn (array $row) => [
                $row[0] => Room::query()->firstOrCreate(
                    ['name' => $row[0]],
                    ['floor' => $row[1], 'type' => $row[2]],
                ),
            ])
            // The off-site room is seeded by RoomSeeder and belongs in the mix.
            ->put('Off-site', Room::query()->firstOrCreate(
                ['name' => 'Off-site / with staff'],
                ['type' => RoomType::Offsite],
            ));
    }

    /** @return Collection<int, Person> */
    private function staff(): Collection
    {
        return $this->namesFor(20)
            ->map(fn (string $name, int $i) => Person::query()->firstOrCreate(
                ['name' => $name],
                [
                    'type' => PersonType::Staff,
                    'email' => $this->emailFor($name),
                    'active' => true,
                ],
            ))
            ->values();
    }

    /** @return Collection<int, Person> */
    private function students(): Collection
    {
        $students = $this->namesFor(100, offset: 20)
            ->values()
            ->map(fn (string $name, int $i) => Person::query()->firstOrCreate(
                ['name' => $name],
                [
                    'type' => PersonType::Student,
                    'class' => self::CLASSES[intdiv($i, 10)],
                    'active' => true,
                ],
            ));

        // Two who have left, so the retention command has something to show.
        // The observer stamps left_at for both.
        $students->take(2)->each(function (Person $person, int $i) {
            $person->update(['active' => false]);
            $person->forceFill(['left_at' => now()->subMonths($i === 0 ? 14 : 6)->toDateString()])->save();
        });

        return $students;
    }

    /**
     * @param  Collection<string, Room>  $rooms
     * @param  Collection<int, Person>  $staff
     * @return Collection<int, Asset>
     */
    private function assets(Collection $rooms, Collection $staff): Collection
    {
        $tags = app(AssetTagGenerator::class);
        $log = app(AuditLogger::class);
        $categories = Category::query()->pluck('id', 'name');

        // make, model, category, how many, which rooms they live in
        $fleet = [
            ['Dell', 'OptiPlex 3070', 'Computer', 14, ['Computer lab']],
            ['Dell', 'P2419H', 'Monitor', 14, ['Computer lab']],
            ['Lenovo', 'ThinkPad E14', 'Laptop', 6, ['Off-site', 'Director office', 'Secretary office']],
            ['Epson', 'EB-X41', 'Projector', 6, ['Room 8', 'Room 9', 'Room 10', 'Room 11', 'Room 12', 'Physics lab']],
            ['HP', 'LaserJet M404', 'Printer', 3, ['Secretary office', 'Director office', 'Computer lab']],
            ['TP-Link', 'Archer C6', 'Network', 4, ['Main corridor', 'Computer lab', 'Store room']],
            ['Promethean', 'ActivPanel 75', 'Interactive board', 2, ['Room 11', 'Room 12']],
            ['Vernier', 'Go Direct sensor kit', 'Lab equipment', 4, ['Physics lab']],
            ['JBL', 'EON615 speaker', 'Audio', 2, ['Main corridor']],
            ['Gloria', 'P6 GA extinguisher', 'Fire safety', 6, ['Main corridor', 'Physics lab', 'Computer lab', 'Store room']],
            ['Cederroth', 'First aid station', 'First aid', 2, ['Physics lab', 'Main corridor']],
        ];

        $created = collect();
        $roomNames = $rooms->keys()->all();

        foreach ($fleet as [$make, $model, $categoryName, $count, $where]) {
            $needsInspection = in_array($categoryName, ['Fire safety', 'First aid'], true);

            for ($i = 0; $i < $count; $i++) {
                $roomName = $where[$i % count($where)];
                $room = $rooms->get($roomName) ?? $rooms->get($roomNames[$i % count($roomNames)]);
                $serial = $this->serialFor($make, $model, $i);

                // Already seeded. Take the existing one rather than making a
                // second copy of the same machine.
                if ($existing = Asset::query()->where('serial_number', $serial)->first()) {
                    $created->push($existing);

                    continue;
                }

                $asset = new Asset([
                    'name' => $make.' '.$model,
                    'make' => $make,
                    'model' => $model,
                    'category_id' => $categories[$categoryName],
                    'room_id' => $room->id,
                    'serial_number' => $serial,
                    'status' => AssetStatus::InUse,
                    'condition' => AssetCondition::Good,
                    'funding_source' => $this->fundingFor($categoryName),
                    'purchase_date' => now()->subMonths(random_int(6, 60))->toDateString(),
                    'purchase_price' => $this->priceFor($categoryName),
                    'supplier' => 'Demo Supplies sh.p.k.',
                    'warranty_end' => now()->addDays(random_int(90, 400))->toDateString(),
                    'needs_inspection' => $needsInspection,
                    'inspection_interval_months' => $needsInspection ? ($categoryName === 'Fire safety' ? 12 : 6) : null,
                ]);

                $tags->assignAndSave($asset);
                $log->record($asset, EventAction::ASSET_CREATED, [
                    'name' => $asset->name,
                    'room' => $room->name,
                ], Actor::system());

                $created->push($asset);
            }
        }

        // A handful that are not simply in use, so the status filters mean something.
        $created->slice(0, 2)->each(fn (Asset $a) => $a->update(['status' => AssetStatus::InStorage]));
        $created->slice(2, 2)->each(fn (Asset $a) => $a->update(['status' => AssetStatus::Retired, 'disposal_date' => now()->subMonths(3)->toDateString()]));
        $created->slice(4, 1)->each(fn (Asset $a) => $a->update(['status' => AssetStatus::Lost]));

        // One warranty running out, so Home shows a mix and not five of the same row.
        $created->firstWhere('model', 'ActivPanel 75')?->update(['warranty_end' => now()->addDays(45)->toDateString()]);

        // Laptops travel, so they get a custodian while keeping their home room.
        $created->where('model', 'ThinkPad E14')->values()->each(
            fn (Asset $asset, int $i) => $asset->update(['assigned_person_id' => $staff[$i % $staff->count()]->id])
        );

        return $created;
    }

    /** @param  Collection<string, Room>  $rooms */
    private function furniture(Collection $rooms): void
    {
        $lines = [
            ['Student desk', 30], ['Student chair', 30], ['Teacher desk', 1],
            ['Cupboard', 2], ['Whiteboard', 1], ['Bookshelf', 2],
        ];

        $rooms->filter(fn (Room $room) => $room->type === RoomType::Classroom)
            ->each(function (Room $room) use ($lines) {
                foreach ($lines as [$item, $quantity]) {
                    RoomContent::query()->firstOrCreate(
                        ['room_id' => $room->id, 'item_name' => $item],
                        ['quantity' => $quantity, 'condition' => AssetCondition::Good],
                    );
                }
            });
    }

    /** @param  Collection<string, Room>  $rooms */
    private function supplies(Collection $rooms): void
    {
        $store = $rooms->get('Store room');

        $supplies = [
            ['A4 paper', SupplyUnit::Reams, 3, 5],        // below minimum on purpose
            ['Black toner', SupplyUnit::Boxes, 12, 4],
            ['White chalk', SupplyUnit::Boxes, 2, 4],     // below minimum on purpose
            ['Board markers', SupplyUnit::Packs, 18, 6],
            ['Cleaning cloths', SupplyUnit::Packs, 9, 3],
        ];

        foreach ($supplies as [$name, $unit, $quantity, $minimum]) {
            Supply::query()->firstOrCreate(['name' => $name], [
                'unit' => $unit,
                'quantity' => $quantity,
                'minimum_level' => $minimum,
                'room_id' => $store?->id,
                'storage_location' => 'Second shelf',
            ]);
        }
    }

    /** @param  Collection<int, Person>  $students */
    private function books(Collection $students): void
    {
        $subjects = ['Mathematics', 'Albanian language', 'Physics', 'Chemistry', 'Biology', 'History', 'Geography', 'English', 'Informatics', 'Civics'];
        $titles = collect();

        foreach ([10, 11, 12] as $grade) {
            foreach ($subjects as $subject) {
                $titles->push(BookTitle::query()->firstOrCreate(
                    ['title' => $subject.' '.$grade],
                    ['subject' => $subject, 'grade' => $grade, 'total_copies' => random_int(35, 60)],
                ));
            }
        }

        $year = AcademicYear::current();
        $due = AcademicYear::endsOn($year)->toDateString();
        $log = app(AuditLogger::class);

        $students->filter(fn (Person $p) => $p->active)->each(function (Person $student) use ($titles, $year, $due, $log) {
            $grade = $student->grade();
            $forGrade = $titles->where('grade', $grade)->take(4);

            $issued = 0;

            foreach ($forGrade as $index => $title) {
                // Most books are still out; a few have already come back.
                $returned = $index === 0 && $student->id % 4 === 0;

                $loan = BookLoan::query()->firstOrCreate(
                    ['person_id' => $student->id, 'book_title_id' => $title->id, 'academic_year' => $year],
                    [
                        'issued_at' => now()->subMonths(2)->toDateString(),
                        'due_at' => $due,
                        'returned_at' => $returned ? now()->subWeeks(2)->toDateString() : null,
                        'condition_on_return' => $returned ? LoanCondition::Good : null,
                    ],
                );
            }

            $log->record($student, EventAction::BOOK_ISSUED, [
                'titles' => $forGrade->count(),
                'academic_year' => $year,
            ], Actor::system());
        });
    }

    /** @param  Collection<int, Asset>  $assets */
    private function reports(Collection $assets): void
    {
        // Nothing here is keyed on a natural identifier, so a second run would
        // simply invent the same faults again.
        if (Report::query()->exists()) {
            return;
        }

        $log = app(AuditLogger::class);

        $scripts = [
            ['Epson', 'It does not turn on', 'The lamp flashes red three times and nothing happens.', ReportStatus::Open, ReporterType::Staff, 9],
            ['Dell', 'It is physically damaged', 'The power button is stuck in.', ReportStatus::Open, ReporterType::Student, 2],
            ['HP', 'It does not turn on', 'Paper jams every time, then it stops.', ReportStatus::InProgress, ReporterType::Staff, 5],
            ['JBL', 'It is physically damaged', 'The mesh on the front is torn.', ReportStatus::Resolved, ReporterType::Staff, 40],
            ['Promethean', 'It does not turn on', 'Touch stopped responding on the left half.', ReportStatus::Resolved, ReporterType::Staff, 60],
            ['Lenovo', 'It is missing', 'Not in the cupboard where it usually lives.', ReportStatus::Dismissed, ReporterType::Other, 30],
        ];

        $reporters = $this->namesFor(6, offset: 120)->values();

        foreach ($scripts as $i => [$make, $chip, $words, $status, $type, $daysAgo]) {
            $asset = $assets->firstWhere('make', $make);

            if (! $asset) {
                continue;
            }

            $report = new Report;
            $report->reference = Report::newReference();
            $report->asset_id = $asset->id;
            $report->reporter_name = $reporters[$i];
            $report->reporter_type = $type;
            $report->device_token = 'demo-device-'.$i;
            $report->ip_address = '10.0.0.'.(10 + $i);
            $report->description = $chip."\n".$words;
            $report->status = $status;
            $report->asset_status_before = AssetStatus::InUse;
            $report->asset_condition_before = AssetCondition::Good;
            $report->created_at = now()->subDays($daysAgo);

            if ($status !== ReportStatus::Open) {
                $report->first_viewed_at = now()->subDays($daysAgo)->addHours(6);
            }

            if ($status === ReportStatus::InProgress) {
                $report->acknowledged_at = now()->subDays($daysAgo)->addDay();
            }

            if ($status->isClosed()) {
                $report->resolved_at = now()->subDays(max(0, $daysAgo - 4));
                $report->resolution_note = $status === ReportStatus::Resolved
                    ? 'Repaired on site.'
                    : 'Found in the other cupboard.';
            }

            $report->save();

            $log->record($report, EventAction::REPORT_CREATED, [
                'asset_tag' => $asset->asset_tag,
                'reference' => $report->reference,
            ], new Actor($type->actorType(), null, $report->reporter_name, $report->device_token));

            // Only the ones still open leave the item under repair.
            if (! $status->isClosed()) {
                $asset->update(['status' => AssetStatus::UnderRepair, 'condition' => AssetCondition::Broken]);
            }
        }
    }

    /** @param  Collection<int, Asset>  $assets */
    private function licenses(Collection $assets): void
    {
        $log = app(AuditLogger::class);
        $pc = $assets->firstWhere('model', 'OptiPlex 3070');

        $licenses = [
            ['Microsoft 365 Education', 'Microsoft', LicenseType::Software, LicenseModel::Subscription, 25, 18, now()->addDays(38), 240.00, true, 'it@demo-school.example', null],
            ['ESET Endpoint Protection', 'ESET', LicenseType::Antivirus, LicenseModel::Volume, 40, 36, now()->subDays(11), 310.00, false, 'director@demo-school.example', null],
            ['demo-school.example', 'Loopia', LicenseType::Domain, LicenseModel::Subscription, null, null, now()->addMonths(9), 14.00, true, 'office@demo-school.example', null],
            ['Shared hosting, 20GB', 'Loopia', LicenseType::Hosting, LicenseModel::Subscription, null, null, now()->addMonths(4), 89.00, true, 'office@demo-school.example', null],
            // The one that must never show a red badge.
            ['Windows 11 Pro OEM', 'Microsoft', LicenseType::Software, LicenseModel::Perpetual, 1, 1, null, null, false, 'preinstalled', $pc?->id],
        ];

        foreach ($licenses as [$name, $vendor, $type, $model, $total, $used, $expires, $cost, $auto, $account, $assetId]) {
            $license = License::query()->firstOrCreate(['name' => $name], [
                'vendor' => $vendor,
                'type' => $type,
                'licence_model' => $model,
                'seats_total' => $total,
                'seats_used' => $used,
                'purchase_date' => now()->subYear()->toDateString(),
                'expires_at' => $expires?->toDateString(),
                'renewal_cost' => $cost,
                'auto_renew' => $auto,
                'account_reference' => $account,
                'asset_id' => $assetId,
            ]);

            $log->record($license, EventAction::LICENCE_CREATED, [
                'name' => $license->name,
                'vendor' => $license->vendor,
                'expires_at' => $license->expires_at?->toDateString(),
            ], Actor::system());
        }
    }

    /** @param  Collection<int, Asset>  $assets */
    private function inspections(Collection $assets): void
    {
        $recorder = app(InspectionRecorder::class);
        $inspector = 'Rrahim Zeka'.self::FICTIONAL;

        // Some checked recently, some left to go overdue so the list is not empty.
        $assets->where('needs_inspection', true)->values()->each(function (Asset $asset, int $i) use ($recorder, $inspector) {
            if ($i % 3 === 2) {
                return; // never inspected, so it shows as overdue
            }

            if ($asset->inspections()->exists()) {
                return;
            }

            $recorder->record(
                asset: $asset,
                result: InspectionResult::Pass,
                inspectorName: $inspector,
                notes: null,
                actor: Actor::system(),
            );

            if ($i % 3 === 1) {
                // Backdated far enough that its interval has elapsed.
                $asset->forceFill(['last_inspected_at' => now()->subMonths(18)])->save();
            }
        });
    }

    /**
     * Invented people. The "(demo)" suffix is the point: nobody should ever
     * have to guess whether a row is real.
     *
     * @return Collection<int, string>
     */
    private function namesFor(int $count, int $offset = 0): Collection
    {
        $first = ['Arben', 'Blerta', 'Driton', 'Elira', 'Fatos', 'Gentiana', 'Ilir', 'Jeta', 'Kushtrim', 'Luljeta',
            'Mentor', 'Nora', 'Odise', 'Pranvera', 'Qendrim', 'Rina', 'Shpend', 'Teuta', 'Uran', 'Vlora',
            'Ylber', 'Zana', 'Agron', 'Besa', 'Dardan', 'Erza'];

        $last = ['Aliu', 'Berisha', 'Curri', 'Demaj', 'Elshani', 'Fejza', 'Gashi', 'Hoxha', 'Ismajli', 'Jashari',
            'Krasniqi', 'Lleshi', 'Morina', 'Nikqi', 'Osmani', 'Prekazi', 'Qorri', 'Rexhepi', 'Salihu', 'Thaqi'];

        $names = collect();

        for ($i = 0; $names->count() < $count; $i++) {
            $index = $i + $offset;
            $name = $first[$index % count($first)].' '.$last[intdiv($index, count($first)) % count($last)];
            $candidate = $name.self::FICTIONAL;

            if (! $names->contains($candidate)) {
                $names->push($candidate);
            }

            if ($i > $count * 10) {
                break; // never spin forever
            }
        }

        return $names;
    }

    private function emailFor(string $name): string
    {
        $handle = str($name)->replace(self::FICTIONAL, '')->slug('.')->toString();

        return $handle.'@demo-school.example';
    }

    /** Deterministic, so re-running the seeder recognises what it already made. */
    private function serialFor(string $make, string $model, int $i): string
    {
        $stem = strtoupper(substr($make, 0, 3)).strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $model), 0, 4));

        return $stem.'-'.str_pad((string) (1000 + $i), 5, '0', STR_PAD_LEFT).'-DEMO';
    }

    private function fundingFor(string $category): FundingSource
    {
        return match ($category) {
            'Computer', 'Monitor' => FundingSource::Project,
            'Interactive board' => FundingSource::Donation,
            'Lab equipment' => FundingSource::Donation,
            default => FundingSource::Budget,
        };
    }

    private function priceFor(string $category): float
    {
        return match ($category) {
            'Computer' => 480.00,
            'Laptop' => 720.00,
            'Monitor' => 130.00,
            'Projector' => 410.00,
            'Printer' => 260.00,
            'Interactive board' => 1850.00,
            'Lab equipment' => 340.00,
            'Audio' => 590.00,
            default => 45.00,
        };
    }
}
