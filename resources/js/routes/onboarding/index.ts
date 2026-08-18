import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Web\OnboardingController::__invoke
* @see app/Http/Controllers/Web/OnboardingController.php:18
* @route '/onboarding'
*/
export const complete = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: complete.url(options),
    method: 'patch',
})

complete.definition = {
    methods: ["patch"],
    url: '/onboarding',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Web\OnboardingController::__invoke
* @see app/Http/Controllers/Web/OnboardingController.php:18
* @route '/onboarding'
*/
complete.url = (options?: RouteQueryOptions) => {
    return complete.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\OnboardingController::__invoke
* @see app/Http/Controllers/Web/OnboardingController.php:18
* @route '/onboarding'
*/
complete.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: complete.url(options),
    method: 'patch',
})

const onboarding = {
    complete: Object.assign(complete, complete),
}

export default onboarding