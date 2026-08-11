import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
import tasks from './tasks'
/**
* @see \App\Http\Controllers\Api\V1\TaskListOrderController::__invoke
* @see app/Http/Controllers/Api/V1/TaskListOrderController.php:18
* @route '/api/v1/lists/order'
*/
export const order = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: order.url(options),
    method: 'put',
})

order.definition = {
    methods: ["put"],
    url: '/api/v1/lists/order',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Api\V1\TaskListOrderController::__invoke
* @see app/Http/Controllers/Api/V1/TaskListOrderController.php:18
* @route '/api/v1/lists/order'
*/
order.url = (options?: RouteQueryOptions) => {
    return order.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\TaskListOrderController::__invoke
* @see app/Http/Controllers/Api/V1/TaskListOrderController.php:18
* @route '/api/v1/lists/order'
*/
order.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: order.url(options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Api\V1\TaskListController::index
* @see app/Http/Controllers/Api/V1/TaskListController.php:23
* @route '/api/v1/lists'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/v1/lists',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\TaskListController::index
* @see app/Http/Controllers/Api/V1/TaskListController.php:23
* @route '/api/v1/lists'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\TaskListController::index
* @see app/Http/Controllers/Api/V1/TaskListController.php:23
* @route '/api/v1/lists'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\TaskListController::index
* @see app/Http/Controllers/Api/V1/TaskListController.php:23
* @route '/api/v1/lists'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\V1\TaskListController::store
* @see app/Http/Controllers/Api/V1/TaskListController.php:32
* @route '/api/v1/lists'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/v1/lists',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\V1\TaskListController::store
* @see app/Http/Controllers/Api/V1/TaskListController.php:32
* @route '/api/v1/lists'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\TaskListController::store
* @see app/Http/Controllers/Api/V1/TaskListController.php:32
* @route '/api/v1/lists'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\V1\TaskListController::show
* @see app/Http/Controllers/Api/V1/TaskListController.php:45
* @route '/api/v1/lists/{list}'
*/
export const show = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/v1/lists/{list}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\TaskListController::show
* @see app/Http/Controllers/Api/V1/TaskListController.php:45
* @route '/api/v1/lists/{list}'
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
* @see \App\Http\Controllers\Api\V1\TaskListController::show
* @see app/Http/Controllers/Api/V1/TaskListController.php:45
* @route '/api/v1/lists/{list}'
*/
show.get = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\TaskListController::show
* @see app/Http/Controllers/Api/V1/TaskListController.php:45
* @route '/api/v1/lists/{list}'
*/
show.head = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\V1\TaskListController::update
* @see app/Http/Controllers/Api/V1/TaskListController.php:52
* @route '/api/v1/lists/{list}'
*/
export const update = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/api/v1/lists/{list}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Api\V1\TaskListController::update
* @see app/Http/Controllers/Api/V1/TaskListController.php:52
* @route '/api/v1/lists/{list}'
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
* @see \App\Http\Controllers\Api\V1\TaskListController::update
* @see app/Http/Controllers/Api/V1/TaskListController.php:52
* @route '/api/v1/lists/{list}'
*/
update.put = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Api\V1\TaskListController::update
* @see app/Http/Controllers/Api/V1/TaskListController.php:52
* @route '/api/v1/lists/{list}'
*/
update.patch = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\V1\TaskListController::destroy
* @see app/Http/Controllers/Api/V1/TaskListController.php:64
* @route '/api/v1/lists/{list}'
*/
export const destroy = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/v1/lists/{list}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\V1\TaskListController::destroy
* @see app/Http/Controllers/Api/V1/TaskListController.php:64
* @route '/api/v1/lists/{list}'
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
* @see \App\Http\Controllers\Api\V1\TaskListController::destroy
* @see app/Http/Controllers/Api/V1/TaskListController.php:64
* @route '/api/v1/lists/{list}'
*/
destroy.delete = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\V1\TaskOrderController::__invoke
* @see app/Http/Controllers/Api/V1/TaskOrderController.php:19
* @route '/api/v1/lists/{list}/task-order'
*/
export const taskOrder = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: taskOrder.url(args, options),
    method: 'put',
})

taskOrder.definition = {
    methods: ["put"],
    url: '/api/v1/lists/{list}/task-order',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Api\V1\TaskOrderController::__invoke
* @see app/Http/Controllers/Api/V1/TaskOrderController.php:19
* @route '/api/v1/lists/{list}/task-order'
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
* @see \App\Http\Controllers\Api\V1\TaskOrderController::__invoke
* @see app/Http/Controllers/Api/V1/TaskOrderController.php:19
* @route '/api/v1/lists/{list}/task-order'
*/
taskOrder.put = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: taskOrder.url(args, options),
    method: 'put',
})

const lists = {
    order: Object.assign(order, order),
    index: Object.assign(index, index),
    store: Object.assign(store, store),
    show: Object.assign(show, show),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
    tasks: Object.assign(tasks, tasks),
    taskOrder: Object.assign(taskOrder, taskOrder),
}

export default lists