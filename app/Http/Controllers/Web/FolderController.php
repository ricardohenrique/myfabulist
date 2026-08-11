<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\DestroyFolderRequest;
use App\Http\Requests\Web\StoreFolderRequest;
use App\Http\Requests\Web\UpdateFolderRequest;
use App\Models\Folder;
use App\Services\FolderService;
use Illuminate\Http\RedirectResponse;

class FolderController extends Controller
{
    public function __construct(
        private readonly FolderService $folders,
    ) {}

    public function store(StoreFolderRequest $request): RedirectResponse
    {
        $folder = $this->folders->create($request->user(), $request->validated('name'));

        return back()->with('success', "Folder “{$folder->name}” created.");
    }

    public function update(UpdateFolderRequest $request, Folder $folder): RedirectResponse
    {
        $folder = $this->folders->rename($folder, $request->validated('name'));

        return back()->with('success', "Folder renamed to “{$folder->name}”.");
    }

    public function destroy(DestroyFolderRequest $request, Folder $folder): RedirectResponse
    {
        $strategy = $request->listsStrategy();

        match ($strategy) {
            'detach' => $this->folders->detachLists($folder),
            'delete' => $this->folders->deleteWithLists($folder),
            default => $this->folders->delete($folder),
        };

        return $strategy === 'delete'
            ? redirect()->route('inbox')->with('success', 'Folder and its lists deleted.')
            : back()->with('success', $strategy === 'detach'
                ? 'Folder deleted. Its lists are now ungrouped.'
                : 'Folder deleted.');
    }
}
