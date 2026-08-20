import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\NotificationCenterController::__invoke
* @see app/Http/Controllers/NotificationCenterController.php:18
* @route '/notifications'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/notifications',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\NotificationCenterController::__invoke
* @see app/Http/Controllers/NotificationCenterController.php:18
* @route '/notifications'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\NotificationCenterController::__invoke
* @see app/Http/Controllers/NotificationCenterController.php:18
* @route '/notifications'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\NotificationCenterController::__invoke
* @see app/Http/Controllers/NotificationCenterController.php:18
* @route '/notifications'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Web\NotificationOpenController::__invoke
* @see app/Http/Controllers/Web/NotificationOpenController.php:18
* @route '/notifications/{notification}/open'
*/
export const open = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: open.url(args, options),
    method: 'post',
})

open.definition = {
    methods: ["post"],
    url: '/notifications/{notification}/open',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Web\NotificationOpenController::__invoke
* @see app/Http/Controllers/Web/NotificationOpenController.php:18
* @route '/notifications/{notification}/open'
*/
open.url = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { notification: args }
    }

    if (Array.isArray(args)) {
        args = {
            notification: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        notification: args.notification,
    }

    return open.definition.url
            .replace('{notification}', parsedArgs.notification.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\NotificationOpenController::__invoke
* @see app/Http/Controllers/Web/NotificationOpenController.php:18
* @route '/notifications/{notification}/open'
*/
open.post = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: open.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Web\NotificationStatusController::__invoke
* @see app/Http/Controllers/Web/NotificationStatusController.php:18
* @route '/notifications/{notification}'
*/
export const update = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/notifications/{notification}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Web\NotificationStatusController::__invoke
* @see app/Http/Controllers/Web/NotificationStatusController.php:18
* @route '/notifications/{notification}'
*/
update.url = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { notification: args }
    }

    if (Array.isArray(args)) {
        args = {
            notification: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        notification: args.notification,
    }

    return update.definition.url
            .replace('{notification}', parsedArgs.notification.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\NotificationStatusController::__invoke
* @see app/Http/Controllers/Web/NotificationStatusController.php:18
* @route '/notifications/{notification}'
*/
update.patch = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

const notifications = {
    index: Object.assign(index, index),
    open: Object.assign(open, open),
    update: Object.assign(update, update),
}

export default notifications