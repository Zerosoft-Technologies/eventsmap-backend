<?php

namespace App\Http\Requests\Admin\EventV2;

use Illuminate\Foundation\Http\FormRequest;

class SyncEventV2TalentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'present|array',
            'items.*.id' => 'required|integer|distinct|exists:talents,id',
            'items.*.sort_order' => 'sometimes|integer|min:0',
        ];
    }
}
