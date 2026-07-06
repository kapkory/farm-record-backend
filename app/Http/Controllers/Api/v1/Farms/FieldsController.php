<?php

namespace App\Http\Controllers\Api\v1\Farms;

use App\Http\Controllers\Controller;
use App\Models\Core\Farm;
use App\Models\Core\Field;
use App\Traits\ApiResponse;
use App\Traits\ResolvesClientUuid;
use Illuminate\Http\Request;

class FieldsController extends Controller
{
    use ApiResponse, ResolvesClientUuid;

    /**
     * Create or update a field.
     */
    public function create(Request $request)
    {
        $request->validate([
            'uuid' => 'nullable|uuid',
            'farm_uuid' => 'required|uuid|exists:farms,uuid',
            'name' => 'required|string|max:255',
            'size' => 'numeric|nullable',
            'description' => 'string|nullable',
        ]);

        [$uuid, $existing, $foreign] = $this->resolveClientUuid(
            $request,
            Field::class,
            fn (Field $field) => Farm::farmerOwned($request->user()->id)->where('id', $field->farm_id)->exists()
        );

        if ($foreign) {
            return $this->clientUuidTakenResponse();
        }

        try {
            $farmId = Farm::where('uuid', $request->input('farm_uuid'))->first()->id;
            if ($existing) {
                // A uuid that already exists means update (or an offline
                // create being replayed — updating is equivalent then).
                $field = $existing;

                $field->update([
                    'name' => $request->input('name'),
                    'size' => $request->input('size'),
                    'description' => $request->input('description'),
                    'is_active' => $request->input('status') == 'active',
                ]);

                return $this->successResponse($field, 'Field updated successfully', 200);
            }

            // Check if field with same name exists in the same farm
            $existing = Field::where('name', $request->input('name'))
                ->where('farm_id', $farmId)
                ->first();

            if ($existing) {
                return $this->errorResponse('Field with this name already exists in this farm', 409);
            }

            $field = Field::create([
                'uuid' => $uuid,
                'farm_id' => $farmId,
                'name' => $request->input('name'),
                'size' => $request->input('size'),
                'description' => $request->input('description'),
                'is_active' => $request->input('status') == 'active',
            ]);

            return $this->successResponse($field, 'Field created successfully', 201);
        } catch (\Throwable $e) {
            if ($replayed = $this->findAfterUniqueViolation($e, Field::class, $uuid)) {
                return $this->successResponse($replayed, 'Field already saved');
            }

            return $this->errorResponse('Failed to save field', 500, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * List all fields, optionally filtered by farm_id.
     */
    public function listFields(Request $request, $farm_uid = null)
    {
        $query = Field::select('id', 'uuid', 'farm_id', 'name', 'size', 'description', 'is_active');

        if ($farm_uid) {
            $farmId = Farm::where('uuid', $farm_uid)->first()->id;
            $query->where('farm_id', $farmId);
        }

        $fields = $query->orderBy('name')->get();

        return $this->successResponse($fields, 'Fields retrieved successfully', 200);
    }

    /**
     * Get a single field by UUID.
     */
    public function show($fieldUuid)
    {
        $field = Field::where('uuid', $fieldUuid)->first();

        if (! $field) {
            return $this->errorResponse('Field not found', 404);
        }

        return $this->successResponse($field, 'Field retrieved successfully', 200);
    }

    /**
     * Delete a field by UUID (soft delete).
     */
    public function delete($fieldUuid)
    {
        $field = Field::where('uuid', $fieldUuid)->first();

        if (! $field) {
            return $this->errorResponse('Field not found', 404);
        }

        try {
            $field->delete();

            return $this->successResponse(null, 'Field deleted successfully', 200);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to delete field', 500, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Toggle field active status.
     */
    public function toggleStatus($fieldUuid)
    {
        $field = Field::where('uuid', $fieldUuid)->first();

        if (! $field) {
            return $this->errorResponse('Field not found', 404);
        }

        try {
            $field->update(['is_active' => ! $field->is_active]);
            $status = $field->is_active ? 'activated' : 'deactivated';

            return $this->successResponse($field, "Field {$status} successfully", 200);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to update field status', 500, ['exception' => $e->getMessage()]);
        }
    }
}
