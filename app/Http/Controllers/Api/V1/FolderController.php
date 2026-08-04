<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DestroyFolderRequest;
use App\Http\Requests\Api\V1\StoreFolderRequest;
use App\Http\Requests\Api\V1\UpdateFolderRequest;
use App\Http\Resources\Api\V1\FolderResource;
use App\Models\Folder;
use App\Services\FolderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FolderController extends Controller
{
    public function __construct(
        private readonly FolderService $folders,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Folder::class);

        $folders = $this->folders->allFor($request->user());

        return FolderResource::collection($folders)->response();
    }

    public function store(StoreFolderRequest $request): JsonResponse
    {
        $folder = $this->folders->create($request->user(), $request->validated('name'));

        return FolderResource::make($folder)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Folder $folder): JsonResponse
    {
        $this->authorize('view', $folder);

        return FolderResource::make($folder->load('taskLists'))->response();
    }

    public function update(UpdateFolderRequest $request, Folder $folder): JsonResponse
    {
        $folder = $this->folders->rename($folder, $request->validated('name'));

        return FolderResource::make($folder)->response();
    }

    public function destroy(DestroyFolderRequest $request, Folder $folder): Response
    {
        match ($request->listsStrategy()) {
            'detach' => $this->folders->detachLists($folder),
            'delete' => $this->folders->deleteWithLists($folder),
            default => $this->folders->delete($folder),
        };

        return response()->noContent();
    }
}
