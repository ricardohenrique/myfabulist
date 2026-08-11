import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults, validateParameters } from './../../wayfinder'
/**
* @see \App\Http\Controllers\StaticPrototypeController::__invoke
* @see app/Http/Controllers/StaticPrototypeController.php:15
* @route '/prototype/{view?}'
*/
export const show = (args?: { view?: string | number } | [view: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/prototype/{view?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\StaticPrototypeController::__invoke
* @see app/Http/Controllers/StaticPrototypeController.php:15
* @route '/prototype/{view?}'
*/
show.url = (args?: { view?: string | number } | [view: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { view: args }
    }

    if (Array.isArray(args)) {
        args = {
            view: args[0],
        }
    }

    args = applyUrlDefaults(args)

    validateParameters(args, [
        "view",
    ])

    const parsedArgs = {
        view: args?.view,
    }

    return show.definition.url
            .replace('{view?}', parsedArgs.view?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\StaticPrototypeController::__invoke
* @see app/Http/Controllers/StaticPrototypeController.php:15
* @route '/prototype/{view?}'
*/
show.get = (args?: { view?: string | number } | [view: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\StaticPrototypeController::__invoke
* @see app/Http/Controllers/StaticPrototypeController.php:15
* @route '/prototype/{view?}'
*/
show.head = (args?: { view?: string | number } | [view: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const prototype = {
    show: Object.assign(show, show),
}

export default prototype