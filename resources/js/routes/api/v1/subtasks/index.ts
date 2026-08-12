import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\SubtaskController::update
* @see app/Http/Controllers/Api/V1/SubtaskController.php:21
* @route '/api/v1/subtasks/{subtask}'
*/
export const update = (args: { subtask: number | { id: number } } | [subtask: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/api/v1/subtasks/{subtask}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Api\V1\SubtaskController::update
* @see app/Http/Controllers/Api/V1/SubtaskController.php:21
* @route '/api/v1/subtasks/{subtask}'
*/
update.url = (args: { subtask: number | { id: number } } | [subtask: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { subtask: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { subtask: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            subtask: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        subtask: typeof args.subtask === 'object'
        ? args.subtask.id
        : args.subtask,
    }

    return update.definition.url
            .replace('{subtask}', parsedArgs.subtask.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\SubtaskController::update
* @see app/Http/Controllers/Api/V1/SubtaskController.php:21
* @route '/api/v1/subtasks/{subtask}'
*/
update.put = (args: { subtask: number | { id: number } } | [subtask: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Api\V1\SubtaskController::destroy
* @see app/Http/Controllers/Api/V1/SubtaskController.php:28
* @route '/api/v1/subtasks/{subtask}'
*/
export const destroy = (args: { subtask: number | { id: number } } | [subtask: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/v1/subtasks/{subtask}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\V1\SubtaskController::destroy
* @see app/Http/Controllers/Api/V1/SubtaskController.php:28
* @route '/api/v1/subtasks/{subtask}'
*/
destroy.url = (args: { subtask: number | { id: number } } | [subtask: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { subtask: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { subtask: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            subtask: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        subtask: typeof args.subtask === 'object'
        ? args.subtask.id
        : args.subtask,
    }

    return destroy.definition.url
            .replace('{subtask}', parsedArgs.subtask.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\SubtaskController::destroy
* @see app/Http/Controllers/Api/V1/SubtaskController.php:28
* @route '/api/v1/subtasks/{subtask}'
*/
destroy.delete = (args: { subtask: number | { id: number } } | [subtask: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\V1\CompleteSubtaskController::__invoke
* @see app/Http/Controllers/Api/V1/CompleteSubtaskController.php:22
* @route '/api/v1/subtasks/{subtask}/complete'
*/
export const complete = (args: { subtask: number | { id: number } } | [subtask: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

complete.definition = {
    methods: ["post"],
    url: '/api/v1/subtasks/{subtask}/complete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\V1\CompleteSubtaskController::__invoke
* @see app/Http/Controllers/Api/V1/CompleteSubtaskController.php:22
* @route '/api/v1/subtasks/{subtask}/complete'
*/
complete.url = (args: { subtask: number | { id: number } } | [subtask: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { subtask: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { subtask: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            subtask: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        subtask: typeof args.subtask === 'object'
        ? args.subtask.id
        : args.subtask,
    }

    return complete.definition.url
            .replace('{subtask}', parsedArgs.subtask.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\CompleteSubtaskController::__invoke
* @see app/Http/Controllers/Api/V1/CompleteSubtaskController.php:22
* @route '/api/v1/subtasks/{subtask}/complete'
*/
complete.post = (args: { subtask: number | { id: number } } | [subtask: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\V1\RestoreSubtaskController::__invoke
* @see app/Http/Controllers/Api/V1/RestoreSubtaskController.php:23
* @route '/api/v1/subtasks/{subtask}/restore'
*/
export const restore = (args: { subtask: number | { id: number } } | [subtask: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: restore.url(args, options),
    method: 'post',
})

restore.definition = {
    methods: ["post"],
    url: '/api/v1/subtasks/{subtask}/restore',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\V1\RestoreSubtaskController::__invoke
* @see app/Http/Controllers/Api/V1/RestoreSubtaskController.php:23
* @route '/api/v1/subtasks/{subtask}/restore'
*/
restore.url = (args: { subtask: number | { id: number } } | [subtask: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { subtask: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { subtask: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            subtask: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        subtask: typeof args.subtask === 'object'
        ? args.subtask.id
        : args.subtask,
    }

    return restore.definition.url
            .replace('{subtask}', parsedArgs.subtask.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\RestoreSubtaskController::__invoke
* @see app/Http/Controllers/Api/V1/RestoreSubtaskController.php:23
* @route '/api/v1/subtasks/{subtask}/restore'
*/
restore.post = (args: { subtask: number | { id: number } } | [subtask: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: restore.url(args, options),
    method: 'post',
})

const subtasks = {
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
    complete: Object.assign(complete, complete),
    restore: Object.assign(restore, restore),
}

export default subtasks