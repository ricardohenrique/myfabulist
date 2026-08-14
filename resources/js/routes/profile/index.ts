import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import password from './password'
/**
* @see \App\Http\Controllers\Web\ProfileController::update
* @see app/Http/Controllers/Web/ProfileController.php:18
* @route '/profile'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/profile',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Web\ProfileController::update
* @see app/Http/Controllers/Web/ProfileController.php:18
* @route '/profile'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\ProfileController::update
* @see app/Http/Controllers/Web/ProfileController.php:18
* @route '/profile'
*/
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

const profile = {
    update: Object.assign(update, update),
    password: Object.assign(password, password),
}

export default profile