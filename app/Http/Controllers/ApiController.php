<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class ApiController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests, ApiResponseTrait;

    /**
     * Find a model by ID or return not found response
     */
    protected function findModelOrFail($modelClass, int $id, $resourceClass = null, string $resourceName = 'Resource')
    {
        $model = $modelClass::find($id);

        if (!$model) {
            return $this->notFoundResponse($resourceName);
        }

        return $resourceClass ? new $resourceClass($model) : $model;
    }

    /**
     * Find a model by ID with relationships or return not found response
     */
    protected function findModelWithRelationsOrFail($modelClass, int $id, array $relations = [], $resourceClass = null, string $resourceName = 'Resource')
    {
        $query = $modelClass::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        $model = $query->find($id);

        if (!$model) {
            return $this->notFoundResponse($resourceName);
        }

        return $resourceClass ? new $resourceClass($model) : $model;
    }
}
