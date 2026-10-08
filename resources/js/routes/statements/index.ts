import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\CustomerDocumentController::personal
* @see app/Http/Controllers/CustomerDocumentController.php:46
* @route '/statements/personal.pdf'
*/
export const personal = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: personal.url(options),
    method: 'get',
})

personal.definition = {
    methods: ["get","head"],
    url: '/statements/personal.pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CustomerDocumentController::personal
* @see app/Http/Controllers/CustomerDocumentController.php:46
* @route '/statements/personal.pdf'
*/
personal.url = (options?: RouteQueryOptions) => {
    return personal.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CustomerDocumentController::personal
* @see app/Http/Controllers/CustomerDocumentController.php:46
* @route '/statements/personal.pdf'
*/
personal.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: personal.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CustomerDocumentController::personal
* @see app/Http/Controllers/CustomerDocumentController.php:46
* @route '/statements/personal.pdf'
*/
personal.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: personal.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\CustomerDocumentController::personal
* @see app/Http/Controllers/CustomerDocumentController.php:46
* @route '/statements/personal.pdf'
*/
const personalForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: personal.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CustomerDocumentController::personal
* @see app/Http/Controllers/CustomerDocumentController.php:46
* @route '/statements/personal.pdf'
*/
personalForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: personal.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CustomerDocumentController::personal
* @see app/Http/Controllers/CustomerDocumentController.php:46
* @route '/statements/personal.pdf'
*/
personalForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: personal.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

personal.form = personalForm

const statements = {
    personal: Object.assign(personal, personal),
}

export default statements