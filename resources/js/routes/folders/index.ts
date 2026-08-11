import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Web\FolderOrderController::__invoke
* @see app/Http/Controllers/Web/FolderOrderController.php:18
* @route '/folders/order'
*/
export const order = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: order.url(options),
    method: 'put',
})

order.definition = {
    methods: ["put"],
    url: '/folders/order',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Web\FolderOrderController::__invoke
* @see app/Http/Controllers/Web/FolderOrderController.php:18
* @route '/folders/order'
*/
order.url = (options?: RouteQueryOptions) => {
    return order.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\FolderOrderController::__invoke
* @see app/Http/Controllers/Web/FolderOrderController.php:18
* @route '/folders/order'
*/
order.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: order.url(options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Web\FolderController::store
* @see app/Http/Controllers/Web/FolderController.php:21
* @route '/folders'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/folders',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Web\FolderController::store
* @see app/Http/Controllers/Web/FolderController.php:21
* @route '/folders'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\FolderController::store
* @see app/Http/Controllers/Web/FolderController.php:21
* @route '/folders'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Web\FolderController::update
* @see app/Http/Controllers/Web/FolderController.php:28
* @route '/folders/{folder}'
*/
export const update = (args: { folder: number | { id: number } } | [folder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/folders/{folder}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Web\FolderController::update
* @see app/Http/Controllers/Web/FolderController.php:28
* @route '/folders/{folder}'
*/
update.url = (args: { folder: number | { id: number } } | [folder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { folder: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { folder: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            folder: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        folder: typeof args.folder === 'object'
        ? args.folder.id
        : args.folder,
    }

    return update.definition.url
            .replace('{folder}', parsedArgs.folder.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\FolderController::update
* @see app/Http/Controllers/Web/FolderController.php:28
* @route '/folders/{folder}'
*/
update.put = (args: { folder: number | { id: number } } | [folder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Web\FolderController::destroy
* @see app/Http/Controllers/Web/FolderController.php:35
* @route '/folders/{folder}'
*/
export const destroy = (args: { folder: number | { id: number } } | [folder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/folders/{folder}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Web\FolderController::destroy
* @see app/Http/Controllers/Web/FolderController.php:35
* @route '/folders/{folder}'
*/
destroy.url = (args: { folder: number | { id: number } } | [folder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { folder: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { folder: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            folder: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        folder: typeof args.folder === 'object'
        ? args.folder.id
        : args.folder,
    }

    return destroy.definition.url
            .replace('{folder}', parsedArgs.folder.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\FolderController::destroy
* @see app/Http/Controllers/Web/FolderController.php:35
* @route '/folders/{folder}'
*/
destroy.delete = (args: { folder: number | { id: number } } | [folder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const folders = {
    order: Object.assign(order, order),
    store: Object.assign(store, store),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default folders