import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Web\ProfileController::update
* @see app/Http/Controllers/Web/ProfileController.php:35
* @route '/profile/background'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/profile/background',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Web\ProfileController::update
* @see app/Http/Controllers/Web/ProfileController.php:35
* @route '/profile/background'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\ProfileController::update
* @see app/Http/Controllers/Web/ProfileController.php:35
* @route '/profile/background'
*/
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

const background = {
    update: Object.assign(update, update),
}

export default background