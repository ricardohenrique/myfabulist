import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
import members from './members'
import membership from './membership'
import tasks from './tasks'
/**
* @see \App\Http\Controllers\TaskListController::__invoke
* @see app/Http/Controllers/TaskListController.php:25
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
* @see app/Http/Controllers/TaskListController.php:25
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
* @see app/Http/Controllers/TaskListController.php:25
* @route '/lists/{list}'
*/
show.get = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TaskListController::__invoke
* @see app/Http/Controllers/TaskListController.php:25
* @route '/lists/{list}'
*/
show.head = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Web\TaskListOrderController::__invoke
* @see app/Http/Controllers/Web/TaskListOrderController.php:18
* @route '/lists/order'
*/
export const order = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: order.url(options),
    method: 'put',
})

order.definition = {
    methods: ["put"],
    url: '/lists/order',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Web\TaskListOrderController::__invoke
* @see app/Http/Controllers/Web/TaskListOrderController.php:18
* @route '/lists/order'
*/
order.url = (options?: RouteQueryOptions) => {
    return order.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\TaskListOrderController::__invoke
* @see app/Http/Controllers/Web/TaskListOrderController.php:18
* @route '/lists/order'
*/
order.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: order.url(options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Web\TaskListController::store
* @see app/Http/Controllers/Web/TaskListController.php:21
* @route '/lists'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/lists',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Web\TaskListController::store
* @see app/Http/Controllers/Web/TaskListController.php:21
* @route '/lists'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\TaskListController::store
* @see app/Http/Controllers/Web/TaskListController.php:21
* @route '/lists'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Web\TaskListController::update
* @see app/Http/Controllers/Web/TaskListController.php:32
* @route '/lists/{list}'
*/
export const update = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/lists/{list}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Web\TaskListController::update
* @see app/Http/Controllers/Web/TaskListController.php:32
* @route '/lists/{list}'
*/
update.url = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{list}', parsedArgs.list.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\TaskListController::update
* @see app/Http/Controllers/Web/TaskListController.php:32
* @route '/lists/{list}'
*/
update.put = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Web\TaskListController::destroy
* @see app/Http/Controllers/Web/TaskListController.php:44
* @route '/lists/{list}'
*/
export const destroy = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/lists/{list}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Web\TaskListController::destroy
* @see app/Http/Controllers/Web/TaskListController.php:44
* @route '/lists/{list}'
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
* @see \App\Http\Controllers\Web\TaskListController::destroy
* @see app/Http/Controllers/Web/TaskListController.php:44
* @route '/lists/{list}'
*/
destroy.delete = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Web\TaskOrderController::__invoke
* @see app/Http/Controllers/Web/TaskOrderController.php:17
* @route '/lists/{list}/task-order'
*/
export const taskOrder = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: taskOrder.url(args, options),
    method: 'put',
})

taskOrder.definition = {
    methods: ["put"],
    url: '/lists/{list}/task-order',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Web\TaskOrderController::__invoke
* @see app/Http/Controllers/Web/TaskOrderController.php:17
* @route '/lists/{list}/task-order'
*/
taskOrder.url = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return taskOrder.definition.url
            .replace('{list}', parsedArgs.list.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\TaskOrderController::__invoke
* @see app/Http/Controllers/Web/TaskOrderController.php:17
* @route '/lists/{list}/task-order'
*/
taskOrder.put = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: taskOrder.url(args, options),
    method: 'put',
})

const lists = {
    show: Object.assign(show, show),
    order: Object.assign(order, order),
    store: Object.assign(store, store),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
    members: Object.assign(members, members),
    membership: Object.assign(membership, membership),
    tasks: Object.assign(tasks, tasks),
    taskOrder: Object.assign(taskOrder, taskOrder),
}

export default lists