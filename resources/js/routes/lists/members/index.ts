import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Web\TaskListMemberController::store
* @see app/Http/Controllers/Web/TaskListMemberController.php:29
* @route '/lists/{list}/members'
*/
export const store = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/lists/{list}/members',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Web\TaskListMemberController::store
* @see app/Http/Controllers/Web/TaskListMemberController.php:29
* @route '/lists/{list}/members'
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
* @see \App\Http\Controllers\Web\TaskListMemberController::store
* @see app/Http/Controllers/Web/TaskListMemberController.php:29
* @route '/lists/{list}/members'
*/
store.post = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Web\TaskListMemberController::destroy
* @see app/Http/Controllers/Web/TaskListMemberController.php:36
* @route '/lists/{list}/members/{user}'
*/
export const destroy = (args: { list: number | { id: number }, user: number | { id: number } } | [list: number | { id: number }, user: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/lists/{list}/members/{user}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Web\TaskListMemberController::destroy
* @see app/Http/Controllers/Web/TaskListMemberController.php:36
* @route '/lists/{list}/members/{user}'
*/
destroy.url = (args: { list: number | { id: number }, user: number | { id: number } } | [list: number | { id: number }, user: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            list: args[0],
            user: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        list: typeof args.list === 'object'
        ? args.list.id
        : args.list,
        user: typeof args.user === 'object'
        ? args.user.id
        : args.user,
    }

    return destroy.definition.url
            .replace('{list}', parsedArgs.list.toString())
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\TaskListMemberController::destroy
* @see app/Http/Controllers/Web/TaskListMemberController.php:36
* @route '/lists/{list}/members/{user}'
*/
destroy.delete = (args: { list: number | { id: number }, user: number | { id: number } } | [list: number | { id: number }, user: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const members = {
    store: Object.assign(store, store),
    destroy: Object.assign(destroy, destroy),
}

export default members