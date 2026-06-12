<?php

namespace App\Services\V2;

use App\Models\EventInvitation;
use App\Models\TalentSubcategory;
use App\Models\TalentV2;
use App\Support\ProfilePublicationStatus;
use App\Support\PublishStatus;
use App\Support\TalentDateOfBirth;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TalentService
{
    public function __construct(
        private readonly EventInvitationService $eventInvitationService
    ) {}

    public function getUserTalents(User $user)
    {
        $talents = TalentV2::where('user_id', $user->id)
            ->with(['category', 'eventCategory', 'user', 'talentCategory', 'talentSubcategories'])
            ->orderBy('created_at', 'desc')
            ->get();

        $this->eventInvitationService->hydrateUpcomingAcceptedInvitationEventsOnProfiles($talents, EventInvitation::TYPE_TALENT);
        $this->eventInvitationService->hydratePastAcceptedInvitationEventsOnProfiles($talents, EventInvitation::TYPE_TALENT);

        return $talents;
    }

    public function create(array $data, User $user): TalentV2
    {
        TalentDateOfBirth::applyAgeFromDateOfBirth($data);

        $talent = DB::transaction(function () use ($data, $user) {
            $talentData = [
                'user_id' => $user->id,
                'status' => ProfilePublicationStatus::DRAFT,
                'publish_status' => PublishStatus::DRAFT,
                'title' => $data['title'],
                'slug' => $this->generateUniqueSlug($data['title']),
                'event_type' => $data['event_type'] ?? 'free',
                'category_id' => $data['category_id'] ?? null,
                'talent_category_id' => $data['talent_category_id'] ?? null,
                'city' => $data['city'] ?? null,
                'address' => $data['address'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'is_approved' => false,
            ];

            $optionalFields = [
                'subcategory_ids', 'description',
                'contact_phone', 'contact_email', 'contact_website',
                'contact_box_message', 'contact_box_design_message',
                'facebook_url', 'instagram_url', 'tiktok_url',
                'fan_club_url', 'nationality', 'show_nationality', 'age', 'date_of_birth', 'show_age', 'languages', 'highlights',
                'show_upcoming_events', 'show_past_events', 'show_contact_box',
            ];

            foreach ($optionalFields as $field) {
                if (array_key_exists($field, $data)) {
                    $talentData[$field] = $data[$field];
                }
            }

            // Handle main image
            if (isset($data['image_path'])) {
                if ($data['image_path'] instanceof UploadedFile) {
                    if ($data['image_path']->getError() !== UPLOAD_ERR_OK) {
                        throw new \Exception('File upload failed: ' . $data['image_path']->getErrorMessage());
                    }
                    $talentData['image_path'] = $this->storeImage($data['image_path']);
                } elseif (is_string($data['image_path'])) {
                    $talentData['image_path'] = $data['image_path'];
                }
            }

            // Handle additional images
            if (!empty($data['additional_images']) && is_array($data['additional_images'])) {
                $paths = [];
                foreach ($data['additional_images'] as $imageReference) {
                    if ($imageReference instanceof UploadedFile) {
                        $paths[] = $this->storeImage($imageReference);
                    } elseif (is_string($imageReference) && $imageReference !== '') {
                        $paths[] = $imageReference;
                    }
                }
                if (!empty($paths)) {
                    $talentData['additional_images'] = $paths;
                }
            }

            $talent = TalentV2::create($talentData);

            if (array_key_exists('talent_subcategory_ids', $data) || array_key_exists('subcategory_ids', $data)) {
                $this->syncTalentTaxonomyRelations($talent, $data);
            }

            Log::info('Talent created', ['talent_id' => $talent->id, 'user_id' => $user->id]);

            return $talent->load(['category', 'eventCategory', 'user', 'talentCategory', 'talentSubcategories']);
        });

        $this->inferTalentTaxonomyCategoryIfMissingFromPivot($talent);

        $talent = $talent->fresh()->load(['category', 'eventCategory', 'user', 'talentCategory', 'talentSubcategories']);

        $this->eventInvitationService->hydrateUpcomingAcceptedInvitationEventsOnProfiles([$talent], EventInvitation::TYPE_TALENT);
        $this->eventInvitationService->hydratePastAcceptedInvitationEventsOnProfiles([$talent], EventInvitation::TYPE_TALENT);

        return $talent;
    }

    public function update(TalentV2 $talent, array $data, ?Request $request = null): TalentV2
    {
        TalentDateOfBirth::applyAgeFromDateOfBirth($data);

        $talent = DB::transaction(function () use ($talent, $data, $request) {
            $allowedFields = [
                'title', 'event_type', 'category_id', 'subcategory_ids',
                'status', 'publish_status',
                'talent_category_id',
                'city', 'address', 'latitude', 'longitude',
                'description', 'contact_phone', 'contact_email', 'contact_website',
                'contact_box_message', 'contact_box_design_message',
                'facebook_url', 'instagram_url', 'tiktok_url',
                'fan_club_url', 'nationality', 'show_nationality', 'age', 'date_of_birth', 'show_age', 'languages', 'highlights',
                'show_upcoming_events', 'show_past_events', 'show_contact_box',
            ];

            $updateData = [];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            // Handle main image
            if ($request && $request->hasFile('image_path')) {
                if (!empty($talent->image_path)) {
                    $this->deleteImage($talent->image_path);
                }

                $file = $request->file('image_path');
                if ($file->getError() !== UPLOAD_ERR_OK) {
                    throw new \Exception('File upload failed: ' . $file->getErrorMessage());
                }
                $updateData['image_path'] = $this->storeImage($file);
            } elseif (isset($data['image_path']) && is_string($data['image_path'])) {
                $updateData['image_path'] = $data['image_path'];
            }

            // Handle additional images
            if ($request && $request->hasFile('additional_images')) {
                $this->deleteAllAdditionalImages($talent);

                $newAdditionalImages = [];
                foreach ($request->file('additional_images') as $image) {
                    if ($image->getError() !== UPLOAD_ERR_OK) {
                        Log::error('Additional image upload error', [
                            'error_code' => $image->getError(),
                            'error_message' => $image->getErrorMessage()
                        ]);
                        continue;
                    }
                    $newAdditionalImages[] = $this->storeImage($image);
                }
                $updateData['additional_images'] = $newAdditionalImages;
            } elseif (array_key_exists('additional_images', $data)) {
                if (is_array($data['additional_images']) && !empty($data['additional_images'])) {
                    $filteredImages = array_filter($data['additional_images'], function ($image) {
                        return !is_null($image) && $image !== '';
                    });

                    if (!empty($filteredImages)) {
                        $this->deleteAllAdditionalImages($talent);
                        $updateData['additional_images'] = array_values($filteredImages);
                    } else {
                        $this->deleteAllAdditionalImages($talent);
                        $updateData['additional_images'] = null;
                    }
                } else {
                    // Empty array or null — clear all additional images
                    $this->deleteAllAdditionalImages($talent);
                    $updateData['additional_images'] = null;
                }
            } elseif (isset($data['remove_additional_images']) && $data['remove_additional_images'] === true) {
                $this->deleteAllAdditionalImages($talent);
                $updateData['additional_images'] = null;
            }

            $talent->update($updateData);

            if (array_key_exists('talent_subcategory_ids', $data)
                || array_key_exists('subcategory_ids', $data)) {
                $this->syncTalentTaxonomyRelations($talent, $data);
            }

            $this->ensureTalentCategorySynced($talent->fresh(['talentSubcategories']));
            Log::info('Talent updated', ['talent_id' => $talent->id]);

            return $talent->fresh()->load(['category', 'eventCategory', 'user', 'talentCategory', 'talentSubcategories']);
        });

        $this->eventInvitationService->hydrateUpcomingAcceptedInvitationEventsOnProfiles([$talent], EventInvitation::TYPE_TALENT);
        $this->eventInvitationService->hydratePastAcceptedInvitationEventsOnProfiles([$talent], EventInvitation::TYPE_TALENT);

        return $talent;
    }

    /**
     * Writes pivot rows and mirrors JSON {@code talents_v2.subcategory_ids}; uses talent subcategory IDs from
     * {@code talent_subcategory_ids} or legacy {@code subcategory_ids}.
     */
    public function syncTalentTaxonomyRelations(TalentV2 $talent, array $data): void
    {
        if (! array_key_exists('talent_subcategory_ids', $data) && ! array_key_exists('subcategory_ids', $data)) {
            return;
        }

        $syncIds = $this->normalizedTalentPivotSubcategoryIds([
            ...$data,
            'talent_subcategory_ids' => $data['talent_subcategory_ids'] ?? [],
            'subcategory_ids' => $data['subcategory_ids'] ?? $talent->subcategory_ids ?? [],
        ]);
        $talent->talentSubcategories()->sync($syncIds);
        $talent->forceFill(['subcategory_ids' => $syncIds])->saveQuietly();
    }

    /**
     * When {@code talent_category_id} was omitted but subcategories imply a talent root category (pivot / JSON).
     */
    public function inferTalentTaxonomyCategoryIfMissingFromPivot(TalentV2 $talent): void
    {
        $this->ensureTalentCategorySynced($talent->fresh(['talentSubcategories']));
    }

    public function delete(TalentV2 $talent): void
    {
        DB::transaction(function () use ($talent) {
            $talent->delete();
            Log::info('Talent soft deleted', ['talent_id' => $talent->id]);
        });
    }

    // ──────────────────────────────────────
    // Image Helpers
    // ──────────────────────────────────────

    private function storeImage(UploadedFile $image): string
    {
        $extension = $image->getClientOriginalExtension();
        $hash = Str::random(40);
        $filename = $hash . '.' . strtolower($extension);

        $path = $image->storeAs('talents', $filename, 'public');

        if (!$path) {
            throw new \Exception('Failed to store image file');
        }

        return $path;
    }

    private function deleteImage(?string $imagePath): void
    {
        if (!$imagePath) {
            return;
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $imagePath)) {
            return;
        }

        if (Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }
    }

    private function deleteAllAdditionalImages(TalentV2 $talent): void
    {
        if (!empty($talent->additional_images) && is_array($talent->additional_images)) {
            foreach ($talent->additional_images as $imagePath) {
                if (is_string($imagePath)) {
                    $this->deleteImage($imagePath);
                }
            }
        }
    }

    private function normalizedTalentPivotSubcategoryIds(array $data): array
    {
        $raw = [];
        if (! empty($data['talent_subcategory_ids']) && is_array($data['talent_subcategory_ids'])) {
            $raw = $data['talent_subcategory_ids'];
        } elseif (! empty($data['subcategory_ids']) && is_array($data['subcategory_ids'])) {
            $raw = $data['subcategory_ids'];
        }

        return array_values(array_unique(array_filter(array_map('intval', $raw))));
    }

    /**
     * Set {@code talent_category_id} when the UI only submits subcategories (pivot).
     */
    private function ensureTalentCategorySynced(TalentV2 $talent): void
    {
        if ($talent->talent_category_id !== null) {
            return;
        }

        $subs = $talent->relationLoaded('talentSubcategories')
            ? $talent->talentSubcategories
            : $talent->talentSubcategories()->get();

        $first = $subs->first();
        if ($first && $first->talent_category_id) {
            $talent->updateQuietly(['talent_category_id' => $first->talent_category_id]);

            return;
        }

        if (! empty($talent->subcategory_ids) && is_array($talent->subcategory_ids)) {
            $sid = (int) ($talent->subcategory_ids[0] ?? 0);
            $parentFromJson = TalentSubcategory::query()->whereKey($sid)->value('talent_category_id');
            if ($parentFromJson) {
                $talent->updateQuietly(['talent_category_id' => $parentFromJson]);
            }
        }
    }

    private function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while (TalentV2::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
