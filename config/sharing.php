<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum members per shared list
    |--------------------------------------------------------------------------
    |
    | Plan 1 ("Shared Lists and Collaboration"), Section 6, Q10(c): the owner
    | plus up to 9 collaborators, 10 accepted `task_list_members` rows total.
    | Enforced by `ListSharingService::invite()`/`accept()` — see Step 5.
    |
    */

    'max_members_per_list' => (int) env('SHARING_MAX_MEMBERS_PER_LIST', 10),

];
