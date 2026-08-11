import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Web\TaskController::update
* @see app/Http/Controllers/Web/TaskController.php:21
* @route '/tasks/{task}'
*/
export const update = (args: { task: number | { id: number } } | [task: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/tasks/{task}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Web\TaskController::update
* @see app/Http/Controllers/Web/TaskController.php:21
* @route '/tasks/{task}'
*/
update.url = (args: { task: number | { id: number } } | [task: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { task: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { task: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            task: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        task: typeof args.task === 'object'
        ? args.task.id
        : args.task,
    }

    return update.definition.url
            .replace('{task}', parsedArgs.task.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\TaskController::update
* @see app/Http/Controllers/Web/TaskController.php:21
* @route '/tasks/{task}'
*/
update.put = (args: { task: number | { id: number } } | [task: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Web\TaskController::destroy
* @see app/Http/Controllers/Web/TaskController.php:33
* @route '/tasks/{task}'
*/
export const destroy = (args: { task: number | { id: number } } | [task: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/tasks/{task}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Web\TaskController::destroy
* @see app/Http/Controllers/Web/TaskController.php:33
* @route '/tasks/{task}'
*/
destroy.url = (args: { task: number | { id: number } } | [task: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { task: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { task: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            task: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        task: typeof args.task === 'object'
        ? args.task.id
        : args.task,
    }

    return destroy.definition.url
            .replace('{task}', parsedArgs.task.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\TaskController::destroy
* @see app/Http/Controllers/Web/TaskController.php:33
* @route '/tasks/{task}'
*/
destroy.delete = (args: { task: number | { id: number } } | [task: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Web\CompleteTaskController::__invoke
* @see app/Http/Controllers/Web/CompleteTaskController.php:16
* @route '/tasks/{task}/complete'
*/
export const complete = (args: { task: number | { id: number } } | [task: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

complete.definition = {
    methods: ["post"],
    url: '/tasks/{task}/complete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Web\CompleteTaskController::__invoke
* @see app/Http/Controllers/Web/CompleteTaskController.php:16
* @route '/tasks/{task}/complete'
*/
complete.url = (args: { task: number | { id: number } } | [task: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { task: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { task: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            task: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        task: typeof args.task === 'object'
        ? args.task.id
        : args.task,
    }

    return complete.definition.url
            .replace('{task}', parsedArgs.task.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\CompleteTaskController::__invoke
* @see app/Http/Controllers/Web/CompleteTaskController.php:16
* @route '/tasks/{task}/complete'
*/
complete.post = (args: { task: number | { id: number } } | [task: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Web\RestoreTaskController::__invoke
* @see app/Http/Controllers/Web/RestoreTaskController.php:16
* @route '/tasks/{task}/restore'
*/
export const restore = (args: { task: number | { id: number } } | [task: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: restore.url(args, options),
    method: 'post',
})

restore.definition = {
    methods: ["post"],
    url: '/tasks/{task}/restore',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Web\RestoreTaskController::__invoke
* @see app/Http/Controllers/Web/RestoreTaskController.php:16
* @route '/tasks/{task}/restore'
*/
restore.url = (args: { task: number | { id: number } } | [task: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { task: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { task: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            task: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        task: typeof args.task === 'object'
        ? args.task.id
        : args.task,
    }

    return restore.definition.url
            .replace('{task}', parsedArgs.task.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\RestoreTaskController::__invoke
* @see app/Http/Controllers/Web/RestoreTaskController.php:16
* @route '/tasks/{task}/restore'
*/
restore.post = (args: { task: number | { id: number } } | [task: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: restore.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Web\StarTaskController::__invoke
* @see app/Http/Controllers/Web/StarTaskController.php:17
* @route '/tasks/{task}/star'
*/
export const star = (args: { task: number | { id: number } } | [task: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: star.url(args, options),
    method: 'put',
})

star.definition = {
    methods: ["put"],
    url: '/tasks/{task}/star',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Web\StarTaskController::__invoke
* @see app/Http/Controllers/Web/StarTaskController.php:17
* @route '/tasks/{task}/star'
*/
star.url = (args: { task: number | { id: number } } | [task: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { task: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { task: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            task: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        task: typeof args.task === 'object'
        ? args.task.id
        : args.task,
    }

    return star.definition.url
            .replace('{task}', parsedArgs.task.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\StarTaskController::__invoke
* @see app/Http/Controllers/Web/StarTaskController.php:17
* @route '/tasks/{task}/star'
*/
star.put = (args: { task: number | { id: number } } | [task: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: star.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Web\MoveTaskController::__invoke
* @see app/Http/Controllers/Web/MoveTaskController.php:17
* @route '/tasks/{task}/move'
*/
export const move = (args: { task: number | { id: number } } | [task: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: move.url(args, options),
    method: 'post',
})

move.definition = {
    methods: ["post"],
    url: '/tasks/{task}/move',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Web\MoveTaskController::__invoke
* @see app/Http/Controllers/Web/MoveTaskController.php:17
* @route '/tasks/{task}/move'
*/
move.url = (args: { task: number | { id: number } } | [task: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { task: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { task: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            task: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        task: typeof args.task === 'object'
        ? args.task.id
        : args.task,
    }

    return move.definition.url
            .replace('{task}', parsedArgs.task.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\MoveTaskController::__invoke
* @see app/Http/Controllers/Web/MoveTaskController.php:17
* @route '/tasks/{task}/move'
*/
move.post = (args: { task: number | { id: number } } | [task: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: move.url(args, options),
    method: 'post',
})

const tasks = {
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
    complete: Object.assign(complete, complete),
    restore: Object.assign(restore, restore),
    star: Object.assign(star, star),
    move: Object.assign(move, move),
}

export default tasks