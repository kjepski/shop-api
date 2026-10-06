<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;

class CategoryHasChildrenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A category with subcategories cannot be deleted.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], Response::HTTP_CONFLICT);
    }
}
