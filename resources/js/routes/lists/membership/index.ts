import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Web\TaskListMembershipController::destroy
* @see app/Http/Controllers/Web/TaskListMembershipController.php:28
* @route '/lists/{list}/membership'
*/
export const destroy = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/lists/{list}/membership',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Web\TaskListMembershipController::destroy
* @see app/Http/Controllers/Web/TaskListMembershipController.php:28
* @route '/lists/{list}/membership'
*/
destroy.url = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{list}', parsedArgs.list.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\TaskListMembershipController::destroy
* @see app/Http/Controllers/Web/TaskListMembershipController.php:28
* @route '/lists/{list}/membership'
*/
destroy.delete = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const membership = {
    destroy: Object.assign(destroy, destroy),
}

export default membership