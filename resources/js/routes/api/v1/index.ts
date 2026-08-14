import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
import folders from './folders'
import lists from './lists'
import tasks from './tasks'
import subtasks from './subtasks'
import invitations from './invitations'
/**
* @see routes/api.php:44
* @route '/api/v1/user'
*/
export const user = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: user.url(options),
    method: 'get',
})

user.definition = {
    methods: ["get","head"],
    url: '/api/v1/user',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/api.php:44
* @route '/api/v1/user'
*/
user.url = (options?: RouteQueryOptions) => {
    return user.definition.url + queryParams(options)
}

/**
* @see routes/api.php:44
* @route '/api/v1/user'
*/
user.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: user.url(options),
    method: 'get',
})

/**
* @see routes/api.php:44
* @route '/api/v1/user'
*/
user.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: user.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\V1\InboxController::__invoke
* @see app/Http/Controllers/Api/V1/InboxController.php:21
* @route '/api/v1/inbox'
*/
export const inbox = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: inbox.url(options),
    method: 'get',
})

inbox.definition = {
    methods: ["get","head"],
    url: '/api/v1/inbox',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\InboxController::__invoke
* @see app/Http/Controllers/Api/V1/InboxController.php:21
* @route '/api/v1/inbox'
*/
inbox.url = (options?: RouteQueryOptions) => {
    return inbox.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\InboxController::__invoke
* @see app/Http/Controllers/Api/V1/InboxController.php:21
* @route '/api/v1/inbox'
*/
inbox.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: inbox.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\InboxController::__invoke
* @see app/Http/Controllers/Api/V1/InboxController.php:21
* @route '/api/v1/inbox'
*/
inbox.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: inbox.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\V1\StarredTaskController::__invoke
* @see app/Http/Controllers/Api/V1/StarredTaskController.php:25
* @route '/api/v1/starred'
*/
export const starred = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: starred.url(options),
    method: 'get',
})

starred.definition = {
    methods: ["get","head"],
    url: '/api/v1/starred',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\StarredTaskController::__invoke
* @see app/Http/Controllers/Api/V1/StarredTaskController.php:25
* @route '/api/v1/starred'
*/
starred.url = (options?: RouteQueryOptions) => {
    return starred.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\StarredTaskController::__invoke
* @see app/Http/Controllers/Api/V1/StarredTaskController.php:25
* @route '/api/v1/starred'
*/
starred.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: starred.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\StarredTaskController::__invoke
* @see app/Http/Controllers/Api/V1/StarredTaskController.php:25
* @route '/api/v1/starred'
*/
starred.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: starred.url(options),
    method: 'head',
})

const v1 = {
    user: Object.assign(user, user),
    inbox: Object.assign(inbox, inbox),
    folders: Object.assign(folders, folders),
    lists: Object.assign(lists, lists),
    tasks: Object.assign(tasks, tasks),
    subtasks: Object.assign(subtasks, subtasks),
    starred: Object.assign(starred, starred),
    invitations: Object.assign(invitations, invitations),
}

export default v1