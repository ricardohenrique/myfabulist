import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Web\ProfileController::update
* @see app/Http/Controllers/Web/ProfileController.php:48
* @route '/profile/completion-sound'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/profile/completion-sound',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Web\ProfileController::update
* @see app/Http/Controllers/Web/ProfileController.php:48
* @route '/profile/completion-sound'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\ProfileController::update
* @see app/Http/Controllers/Web/ProfileController.php:48
* @route '/profile/completion-sound'
*/
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

const completionSound = {
    update: Object.assign(update, update),
}

export default completionSound