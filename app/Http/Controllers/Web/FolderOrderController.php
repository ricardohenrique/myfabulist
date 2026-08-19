<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpdateFolderOrderRequest;
use App\Services\FolderService;
use Illuminate\Http\RedirectResponse;

class FolderOrderController extends Controller
{
    public function __construct(
        private readonly FolderService $folders,
    ) {}

    public function __invoke(UpdateFolderOrderRequest $request): RedirectResponse
    {
        $this->folders->reorder($request->user(), $request->folderIds());

        return back();
    }
}
