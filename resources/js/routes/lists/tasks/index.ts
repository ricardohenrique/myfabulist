import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Web\TaskListTaskController::store
* @see app/Http/Controllers/Web/TaskListTaskController.php:19
* @route '/lists/{list}/tasks'
*/
export const store = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/lists/{list}/tasks',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Web\TaskListTaskController::store
* @see app/Http/Controllers/Web/TaskListTaskController.php:19
* @route '/lists/{list}/tasks'
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
* @see \App\Http\Controllers\Web\TaskListTaskController::store
* @see app/Http/Controllers/Web/TaskListTaskController.php:19
* @route '/lists/{list}/tasks'
*/
store.post = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

const tasks = {
    store: Object.assign(store, store),
}

export default tasks