import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\TaskListController::__invoke
* @see app/Http/Controllers/TaskListController.php:18
* @route '/lists/{list}'
*/
export const show = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/lists/{list}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TaskListController::__invoke
* @see app/Http/Controllers/TaskListController.php:18
* @route '/lists/{list}'
*/
show.url = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{list}', parsedArgs.list.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TaskListController::__invoke
* @see app/Http/Controllers/TaskListController.php:18
* @route '/lists/{list}'
*/
show.get = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TaskListController::__invoke
* @see app/Http/Controllers/TaskListController.php:18
* @route '/lists/{list}'
*/
show.head = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const lists = {
    show: Object.assign(show, show),
}

export default lists