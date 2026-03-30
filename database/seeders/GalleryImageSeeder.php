<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\GalleryImage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GalleryImageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $premiumUsers = User::where('account_type', User::ACCOUNT_PREMIUM)
            ->where('status', User::STATUS_ACTIVE)
            ->get();

        if ($premiumUsers->isEmpty()) {
            $this->command->warn('No premium users found. Skipping GalleryImageSeeder.');
            return;
        }

        $events = Event::pluck('id')->toArray();

        $fileTypes = [
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
        ];

        $sampleNames = [
            'photo_2026_01', 'photo_2026_02', 'vacation_sunset',
            'team_event', 'concert_night', 'beach_party',
            'conference_hall', 'birthday_bash', 'gallery_shot',
            'panorama_view', 'group_selfie', 'stage_lights',
            'food_table', 'fireworks_show', 'backstage_pass',
        ];

        foreach ($premiumUsers as $user) {
            $imageCount = rand(10, 15);

            for ($i = 0; $i < $imageCount; $i++) {
                $imageId = (string) Str::uuid();
                $ext = array_rand($fileTypes);
                $mimeType = $fileTypes[$ext];
                $originalName = $sampleNames[array_rand($sampleNames)] . '.' . $ext;
                $filePath = "gallery/user_{$user->id}/{$imageId}.{$ext}";
                $fileSize = rand(512000, 4194304); // 500KB - 4MB
                $uploadDate = Carbon::now()->subDays(rand(0, 90));

                $eventId = null;
                if (!empty($events) && rand(0, 1)) {
                    $eventId = $events[array_rand($events)];
                }

                GalleryImage::create([
                    'image_id'       => $imageId,
                    'user_id'        => $user->id,
                    'event_id'       => $eventId,
                    'file_name'      => $originalName,
                    'file_size'      => $fileSize,
                    'file_path'      => $filePath,
                    'file_type'      => $mimeType,
                    'image_alt_text' => 'Sample gallery image ' . ($i + 1) . ' for user ' . $user->name,
                ]);
            }

            $this->command->info("Seeded {$imageCount} gallery images for user #{$user->id} ({$user->name})");
        }
    }
}
