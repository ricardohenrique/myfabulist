import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\TaskListTaskController::index
* @see app/Http/Controllers/Api/V1/TaskListTaskController.php:26
* @route '/api/v1/lists/{list}/tasks'
*/
export const index = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/v1/lists/{list}/tasks',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\TaskListTaskController::index
* @see app/Http/Controllers/Api/V1/TaskListTaskController.php:26
* @route '/api/v1/lists/{list}/tasks'
*/
index.url = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { list: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { list: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            list: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        list: typeof args.list === 'object'
        ? args.list.id
        : args.list,
    }

    return index.definition.url
            .replace('{list}', parsedArgs.list.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\TaskListTaskController::index
* @see app/Http/Controllers/Api/V1/TaskListTaskController.php:26
* @route '/api/v1/lists/{list}/tasks'
*/
index.get = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\TaskListTaskController::index
* @see app/Http/Controllers/Api/V1/TaskListTaskController.php:26
* @route '/api/v1/lists/{list}/tasks'
*/
index.head = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\V1\TaskListTaskController::store
* @see app/Http/Controllers/Api/V1/TaskListTaskController.php:35
* @route '/api/v1/lists/{list}/tasks'
*/
export const store = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/v1/lists/{list}/tasks',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\V1\TaskListTaskController::store
* @see app/Http/Controllers/Api/V1/TaskListTaskController.php:35
* @route '/api/v1/lists/{list}/tasks'
*/
store.url = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { list: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { list: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            list: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        list: typeof args.list === 'object'
        ? args.list.id
        : args.list,
    }

    return store.definition.url
            .replace('{list}', parsedArgs.list.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\TaskListTaskController::store
* @see app/Http/Controllers/Api/V1/TaskListTaskController.php:35
* @route '/api/v1/lists/{list}/tasks'
*/
store.post = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

const tasks = {
    index: Object.assign(index, index),
    store: Object.assign(store, store),
}

export default tasks