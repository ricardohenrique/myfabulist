import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\ListInvitationController::index
* @see app/Http/Controllers/Api/V1/ListInvitationController.php:32
* @route '/api/v1/invitations'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/v1/invitations',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\ListInvitationController::index
* @see app/Http/Controllers/Api/V1/ListInvitationController.php:32
* @route '/api/v1/invitations'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\ListInvitationController::index
* @see app/Http/Controllers/Api/V1/ListInvitationController.php:32
* @route '/api/v1/invitations'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\ListInvitationController::index
* @see app/Http/Controllers/Api/V1/ListInvitationController.php:32
* @route '/api/v1/invitations'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\V1\ListInvitationController::accept
* @see app/Http/Controllers/Api/V1/ListInvitationController.php:42
* @route '/api/v1/invitations/{invitation}/accept'
*/
export const accept = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: accept.url(args, options),
    method: 'post',
})

accept.definition = {
    methods: ["post"],
    url: '/api/v1/invitations/{invitation}/accept',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\V1\ListInvitationController::accept
* @see app/Http/Controllers/Api/V1/ListInvitationController.php:42
* @route '/api/v1/invitations/{invitation}/accept'
*/
accept.url = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { invitation: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { invitation: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            invitation: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        invitation: typeof args.invitation === 'object'
        ? args.invitation.id
        : args.invitation,
    }

    return accept.definition.url
            .replace('{invitation}', parsedArgs.invitation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\ListInvitationController::accept
* @see app/Http/Controllers/Api/V1/ListInvitationController.php:42
* @route '/api/v1/invitations/{invitation}/accept'
*/
accept.post = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: accept.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\V1\ListInvitationController::decline
* @see app/Http/Controllers/Api/V1/ListInvitationController.php:49
* @route '/api/v1/invitations/{invitation}/decline'
*/
export const decline = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: decline.url(args, options),
    method: 'post',
})

decline.definition = {
    methods: ["post"],
    url: '/api/v1/invitations/{invitation}/decline',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\V1\ListInvitationController::decline
* @see app/Http/Controllers/Api/V1/ListInvitationController.php:49
* @route '/api/v1/invitations/{invitation}/decline'
*/
decline.url = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { invitation: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { invitation: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            invitation: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        invitation: typeof args.invitation === 'object'
        ? args.invitation.id
        : args.invitation,
    }

    return decline.definition.url
            .replace('{invitation}', parsedArgs.invitation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\ListInvitationController::decline
* @see app/Http/Controllers/Api/V1/ListInvitationController.php:49
* @route '/api/v1/invitations/{invitation}/decline'
*/
decline.post = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: decline.url(args, options),
    method: 'post',
})

const invitations = {
    index: Object.assign(index, index),
    accept: Object.assign(accept, accept),
    decline: Object.assign(decline, decline),
}

export default invitations