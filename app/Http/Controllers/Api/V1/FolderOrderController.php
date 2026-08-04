<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateFolderOrderRequest;
use App\Services\FolderService;
use Illuminate\Http\Response;

class FolderOrderController extends Controller
{
    public function __construct(
        private readonly FolderService $folders,
    ) {}

    public function __invoke(UpdateFolderOrderRequest $request): Response
    {
        $this->folders->reorder($request->user(), $request->folderIds());

        return response()->noContent();
    }
}
