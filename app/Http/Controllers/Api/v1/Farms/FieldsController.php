<?php

namespace App\Http\Controllers\Api\v1\Farms;

use App\Http\Controllers\Controller;
use App\Models\Core\Field;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FieldsController extends Controller
{
    use ApiResponse;

    /**
     * Create or update a field.
     */
    public function create(Request $request, $fieldUuid = null)
    {
        $request->validate([
            'farm_id' => 'required|integer|exists:farms,id',
            'name' => 'required|string|max:255|unique:fields,name,NULL,id,farm_id,' . request('farm_id'),
            'size' => 'integer|nullable',
            'description' => 'string|nullable',
        ]);

        try {
            if ($fieldUuid) {
                // Update existing field
                $field = Field::where('uuid', $fieldUuid)->first();
                if (!$field) {
                    return $this->errorResponse('Field not found', 404);
                }

                $field->update([
                    'farm_id' => $request->input('farm_id'),
                    'name' => $request->input('name'),
                    'size' => $request->input('size'),
                    'description' => $request->input('description'),
                ]);

                return $this->successResponse($field, 'Field updated successfully', 200);
            }

            // Check if field with same name exists in the same farm
            $existing = Field::where('name', $request->input('name'))
                ->where('farm_id', $request->input('farm_id'))
                ->first();

            if ($existing) {
                return $this->errorResponse('Field with this name already exists in this farm', 409);
            }

            $field = Field::create([
                'uuid' => Str::orderedUuid(),
                'farm_id' => $request->input('farm_id'),
                'name' => $request->input('name'),
                'size' => $request->input('size'),
                'description' => $request->input('description'),
                'is_active' => true,
            ]);

            return $this->successResponse($field, 'Field created successfully', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to save field', 500, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * List all fields, optionally filtered by farm_id.
     */
    public function listFields(Request $request)
    {
        $query = Field::select('id', 'uuid', 'farm_id', 'name', 'size', 'description', 'is_active');

        if ($request->filled('farm_id')) {
            $query->where('farm_id', $request->input('farm_id'));
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

        if (!$field) {
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

        if (!$field) {
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

        if (!$field) {
            return $this->errorResponse('Field not found', 404);
        }

        try {
            $field->update(['is_active' => !$field->is_active]);
            $status = $field->is_active ? 'activated' : 'deactivated';
            return $this->successResponse($field, "Field {$status} successfully", 200);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to update field status', 500, ['exception' => $e->getMessage()]);
        }
    }
}
