import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\AvailabilityController::index
* @see app/Http/Controllers/AvailabilityController.php:18
* @route '/availability'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/availability',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AvailabilityController::index
* @see app/Http/Controllers/AvailabilityController.php:18
* @route '/availability'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AvailabilityController::index
* @see app/Http/Controllers/AvailabilityController.php:18
* @route '/availability'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\AvailabilityController::index
* @see app/Http/Controllers/AvailabilityController.php:18
* @route '/availability'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AvailabilityController::index
* @see app/Http/Controllers/AvailabilityController.php:18
* @route '/availability'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\AvailabilityController::index
* @see app/Http/Controllers/AvailabilityController.php:18
* @route '/availability'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\AvailabilityController::index
* @see app/Http/Controllers/AvailabilityController.php:18
* @route '/availability'
*/
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \App\Http\Controllers\CheckAvailabilityController::__invoke
* @see app/Http/Controllers/CheckAvailabilityController.php:16
* @route '/availability/check'
*/
export const check = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: check.url(options),
    method: 'post',
})

check.definition = {
    methods: ["post"],
    url: '/availability/check',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CheckAvailabilityController::__invoke
* @see app/Http/Controllers/CheckAvailabilityController.php:16
* @route '/availability/check'
*/
check.url = (options?: RouteQueryOptions) => {
    return check.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CheckAvailabilityController::__invoke
* @see app/Http/Controllers/CheckAvailabilityController.php:16
* @route '/availability/check'
*/
check.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: check.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CheckAvailabilityController::__invoke
* @see app/Http/Controllers/CheckAvailabilityController.php:16
* @route '/availability/check'
*/
const checkForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: check.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CheckAvailabilityController::__invoke
* @see app/Http/Controllers/CheckAvailabilityController.php:16
* @route '/availability/check'
*/
checkForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: check.url(options),
    method: 'post',
})

check.form = checkForm

const availability = {
    index: Object.assign(index, index),
    check: Object.assign(check, check),
}

export default availability