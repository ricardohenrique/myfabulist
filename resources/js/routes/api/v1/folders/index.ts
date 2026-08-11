import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\FolderOrderController::__invoke
* @see app/Http/Controllers/Api/V1/FolderOrderController.php:18
* @route '/api/v1/folders/order'
*/
export const order = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: order.url(options),
    method: 'put',
})

order.definition = {
    methods: ["put"],
    url: '/api/v1/folders/order',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Api\V1\FolderOrderController::__invoke
* @see app/Http/Controllers/Api/V1/FolderOrderController.php:18
* @route '/api/v1/folders/order'
*/
order.url = (options?: RouteQueryOptions) => {
    return order.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\FolderOrderController::__invoke
* @see app/Http/Controllers/Api/V1/FolderOrderController.php:18
* @route '/api/v1/folders/order'
*/
order.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: order.url(options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Api\V1\FolderController::index
* @see app/Http/Controllers/Api/V1/FolderController.php:24
* @route '/api/v1/folders'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/v1/folders',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\FolderController::index
* @see app/Http/Controllers/Api/V1/FolderController.php:24
* @route '/api/v1/folders'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\FolderController::index
* @see app/Http/Controllers/Api/V1/FolderController.php:24
* @route '/api/v1/folders'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\FolderController::index
* @see app/Http/Controllers/Api/V1/FolderController.php:24
* @route '/api/v1/folders'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\V1\FolderController::store
* @see app/Http/Controllers/Api/V1/FolderController.php:33
* @route '/api/v1/folders'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/v1/folders',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\V1\FolderController::store
* @see app/Http/Controllers/Api/V1/FolderController.php:33
* @route '/api/v1/folders'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\FolderController::store
* @see app/Http/Controllers/Api/V1/FolderController.php:33
* @route '/api/v1/folders'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\V1\FolderController::show
* @see app/Http/Controllers/Api/V1/FolderController.php:42
* @route '/api/v1/folders/{folder}'
*/
export const show = (args: { folder: number | { id: number } } | [folder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/v1/folders/{folder}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\FolderController::show
* @see app/Http/Controllers/Api/V1/FolderController.php:42
* @route '/api/v1/folders/{folder}'
*/
show.url = (args: { folder: number | { id: number } } | [folder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{folder}', parsedArgs.folder.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\FolderController::show
* @see app/Http/Controllers/Api/V1/FolderController.php:42
* @route '/api/v1/folders/{folder}'
*/
show.get = (args: { folder: number | { id: number } } | [folder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\FolderController::show
* @see app/Http/Controllers/Api/V1/FolderController.php:42
* @route '/api/v1/folders/{folder}'
*/
show.head = (args: { folder: number | { id: number } } | [folder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\V1\FolderController::update
* @see app/Http/Controllers/Api/V1/FolderController.php:49
* @route '/api/v1/folders/{folder}'
*/
export const update = (args: { folder: number | { id: number } } | [folder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/api/v1/folders/{folder}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Api\V1\FolderController::update
* @see app/Http/Controllers/Api/V1/FolderController.php:49
* @route '/api/v1/folders/{folder}'
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
* @see \App\Http\Controllers\Api\V1\FolderController::update
* @see app/Http/Controllers/Api/V1/FolderController.php:49
* @route '/api/v1/folders/{folder}'
*/
update.put = (args: { folder: number | { id: number } } | [folder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Api\V1\FolderController::update
* @see app/Http/Controllers/Api/V1/FolderController.php:49
* @route '/api/v1/folders/{folder}'
*/
update.patch = (args: { folder: number | { id: number } } | [folder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\V1\FolderController::destroy
* @see app/Http/Controllers/Api/V1/FolderController.php:56
* @route '/api/v1/folders/{folder}'
*/
export const destroy = (args: { folder: number | { id: number } } | [folder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/v1/folders/{folder}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\V1\FolderController::destroy
* @see app/Http/Controllers/Api/V1/FolderController.php:56
* @route '/api/v1/folders/{folder}'
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
* @see \App\Http\Controllers\Api\V1\FolderController::destroy
* @see app/Http/Controllers/Api/V1/FolderController.php:56
* @route '/api/v1/folders/{folder}'
*/
destroy.delete = (args: { folder: number | { id: number } } | [folder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const folders = {
    order: Object.assign(order, order),
    index: Object.assign(index, index),
    store: Object.assign(store, store),
    show: Object.assign(show, show),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default folders