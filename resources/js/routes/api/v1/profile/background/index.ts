import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\ProfileController::update
* @see app/Http/Controllers/Api/V1/ProfileController.php:25
* @route '/api/v1/profile/background'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/v1/profile/background',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\V1\ProfileController::update
* @see app/Http/Controllers/Api/V1/ProfileController.php:25
* @route '/api/v1/profile/background'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\ProfileController::update
* @see app/Http/Controllers/Api/V1/ProfileController.php:25
* @route '/api/v1/profile/background'
*/
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

const background = {
    update: Object.assign(update, update),
}

export default background