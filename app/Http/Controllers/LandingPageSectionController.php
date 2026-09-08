<?php

namespace App\Http\Controllers;

use App\Models\LandingPageSection;
use App\Models\ProductCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LandingPageSectionController extends Controller
{
    private function resolveProductCollection(?string $value): ?ProductCollection
    {
        if (! $value) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (ctype_digit($trimmed)) {
            return ProductCollection::query()->whereKey((int) $trimmed)->first();
        }

        return ProductCollection::query()
            ->where('slug', $trimmed)
            ->orWhere('name', $trimmed)
            ->first();
    }

    /**
     * Display a listing of sections
     */
    public function index(Request $request)
    {
        $query = LandingPageSection::query();

        // Filter by section_type if provided
        if ($request->has('section_type')) {
            $query->where('section_type', $request->section_type);
        }

        $sections = $query->orderBy('display_order')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'sections' => $sections,
        ]);
    }

    /**
     * Get sections by type
     */
    public function getByType($type)
    {
        $sections = LandingPageSection::where('section_type', $type)
            ->orderBy('display_order')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'sections' => $sections,
        ]);
    }

    /**
     * Get active sections for landing page
     */
    public function active()
    {
        $now = now();

        $sections = LandingPageSection::where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->orderBy('display_order')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'sections' => $sections,
        ]);
    }

    /**
     * Store a newly created section
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'section_type' => 'required|in:hero,product_grid,video,faq,testimonials,how_it_works,email_subscribe',
            'source_type' => 'nullable|in:collection,tag',
            'source_value' => 'nullable|string|max:255',
            'product_count' => 'nullable|integer|min:1|max:50',
            'display_style' => 'nullable|in:grid,slider',
            'is_active' => 'nullable|boolean',
            'display_order' => 'nullable|integer',
            'description' => 'nullable|string',
            'config' => 'nullable|json',
            'status' => 'nullable|in:draft,published,archived',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // FAQ is a single permanent landing page block with a fixed heading.
        if ($request->section_type === 'faq') {
            $data = $request->all();
            $data['title'] = 'Frequently Asked Questions';
            $data['description'] = null;

            // Handle config field - decode if it's a JSON string
            if (isset($data['config']) && is_string($data['config'])) {
                try {
                    $decoded = json_decode($data['config'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $data['config'] = $decoded;
                    }
                } catch (\Exception $e) {
                    // If decoding fails, keep the original value
                }
            }

            $existingFaqSection = LandingPageSection::where('section_type', 'faq')->first();
            if ($existingFaqSection) {
                $existingFaqSection->update($data);

                return response()->json([
                    'success' => true,
                    'section' => $existingFaqSection->fresh(),
                ]);
            }

            $section = LandingPageSection::create($data);

            return response()->json([
                'success' => true,
                'section' => $section->fresh(),
            ], 201);
        }

        // Validate source_value for product_grid sections - must be a valid collection
        if ($request->section_type === 'product_grid' && $request->has('source_value')) {
            $collection = $this->resolveProductCollection($request->source_value);
            if (! $collection) {
                return response()->json([
                    'success' => false,
                    'errors' => ['source_value' => ['The selected collection does not exist.']],
                ], 422);
            }
        }

        $data = $request->all();

        // Handle config field - decode if it's a JSON string
        if (isset($data['config']) && is_string($data['config'])) {
            try {
                $decoded = json_decode($data['config'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data['config'] = $decoded;
                }
            } catch (\Exception $e) {
                // If decoding fails, keep the original value
            }
        }

        $section = LandingPageSection::create($data);

        // For hero sliders: Ensure only one is active/published at a time
        if ($section->section_type === 'hero' &&
            ($section->status === 'published' || $section->is_active)) {
            $this->deactivateOtherHeroSliders($section->id);
        }

        return response()->json([
            'success' => true,
            'section' => $section->fresh(),
        ], 201);
    }

    /**
     * Display the specified section
     */
    public function show(LandingPageSection $landingPageSection)
    {
        return response()->json([
            'success' => true,
            'section' => $landingPageSection,
        ]);
    }

    /**
     * Update the specified section
     */
    public function update(Request $request, LandingPageSection $landingPageSection)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'section_type' => 'sometimes|required|in:hero,product_grid,video,faq,testimonials,how_it_works,email_subscribe',
            'source_type' => 'nullable|in:collection,tag',
            'source_value' => 'nullable|string|max:255',
            'product_count' => 'nullable|integer|min:1|max:50',
            'display_style' => 'nullable|in:grid,slider',
            'is_active' => 'nullable|boolean',
            'display_order' => 'nullable|integer',
            'description' => 'nullable|string',
            'config' => 'nullable|json',
            'status' => 'nullable|in:draft,published,archived',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validate source_value for product_grid sections - must be a valid collection
        if ($request->has('section_type') && $request->section_type === 'product_grid' && $request->has('source_value')) {
            $sourceValue = $request->source_value ?? $landingPageSection->source_value;
            $collection = $this->resolveProductCollection($sourceValue);
            if (! $collection) {
                return response()->json([
                    'success' => false,
                    'errors' => ['source_value' => ['The selected collection does not exist.']],
                ], 422);
            }
        }

        $data = $request->all();

        // FAQ is a single permanent landing page block with a fixed heading.
        if (($data['section_type'] ?? $landingPageSection->section_type) === 'faq') {
            $data['title'] = 'Frequently Asked Questions';
            $data['description'] = null;
        }

        // Handle config field - decode if it's a JSON string
        if (isset($data['config']) && is_string($data['config'])) {
            try {
                $decoded = json_decode($data['config'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data['config'] = $decoded;
                }
            } catch (\Exception $e) {
                // If decoding fails, keep the original value
            }
        }

        // Check if this is a hero slider being published/activated
        $isHeroBeingActivated = false;
        if ($landingPageSection->section_type === 'hero') {
            $newStatus = $data['status'] ?? $landingPageSection->status;
            $newIsActive = isset($data['is_active']) ? $data['is_active'] : $landingPageSection->is_active;
            $isHeroBeingActivated = ($newStatus === 'published' || $newIsActive);
        }

        $landingPageSection->update($data);

        // For hero sliders: Ensure only one is active/published at a time
        if ($isHeroBeingActivated) {
            $this->deactivateOtherHeroSliders($landingPageSection->id);
        }

        return response()->json([
            'success' => true,
            'section' => $landingPageSection->fresh(),
        ]);
    }

    /**
     * Remove the specified section
     */
    public function destroy(LandingPageSection $landingPageSection)
    {
        if ($landingPageSection->section_type === 'faq') {
            return response()->json([
                'success' => false,
                'message' => 'FAQ is a permanent landing page section and cannot be deleted.',
            ], 403);
        }

        $landingPageSection->delete();

        return response()->json([
            'success' => true,
            'message' => 'Section deleted successfully',
        ]);
    }

    /**
     * Update section order
     */
    public function updateOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sections' => 'required|array',
            'sections.*.id' => 'required|exists:landing_page_sections,id',
            'sections.*.display_order' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->sections as $item) {
            LandingPageSection::where('id', $item['id'])
                ->update(['display_order' => $item['display_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Section order updated successfully',
        ]);
    }

    /**
     * Publish a section
     */
    public function publish($id)
    {
        $section = LandingPageSection::findOrFail($id);
        $section->status = 'published';
        $section->published_at = now();
        $section->save();

        // For hero sliders: Ensure only one is active/published at a time
        if ($section->section_type === 'hero') {
            $this->deactivateOtherHeroSliders($section->id);
        }

        return response()->json([
            'success' => true,
            'section' => $section->fresh(),
            'message' => 'Section published successfully',
        ]);
    }

    /**
     * Unpublish a section
     */
    public function unpublish($id)
    {
        $section = LandingPageSection::findOrFail($id);
        $section->status = 'draft';
        $section->save();

        return response()->json([
            'success' => true,
            'section' => $section->fresh(),
            'message' => 'Section unpublished successfully',
        ]);
    }

    /**
     * Deactivate other hero sliders (ensure only one hero slider is active)
     */
    private function deactivateOtherHeroSliders($currentSectionId)
    {
        LandingPageSection::where('section_type', 'hero')
            ->where('id', '!=', $currentSectionId)
            ->where(function ($query) {
                $query->where('status', 'published')
                    ->orWhere('is_active', true);
            })
            ->update([
                'status' => 'draft',
                'is_active' => false,
            ]);
    }
}
