import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
import memberships from './memberships'
/**
* @see \App\Http\Controllers\OrganisationController::index
* @see app/Http/Controllers/OrganisationController.php:19
* @route '/organisations'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/organisations',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OrganisationController::index
* @see app/Http/Controllers/OrganisationController.php:19
* @route '/organisations'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrganisationController::index
* @see app/Http/Controllers/OrganisationController.php:19
* @route '/organisations'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrganisationController::index
* @see app/Http/Controllers/OrganisationController.php:19
* @route '/organisations'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OrganisationController::index
* @see app/Http/Controllers/OrganisationController.php:19
* @route '/organisations'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrganisationController::index
* @see app/Http/Controllers/OrganisationController.php:19
* @route '/organisations'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrganisationController::index
* @see app/Http/Controllers/OrganisationController.php:19
* @route '/organisations'
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
* @see \App\Http\Controllers\OrganisationController::store
* @see app/Http/Controllers/OrganisationController.php:41
* @route '/organisations'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/organisations',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrganisationController::store
* @see app/Http/Controllers/OrganisationController.php:41
* @route '/organisations'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrganisationController::store
* @see app/Http/Controllers/OrganisationController.php:41
* @route '/organisations'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrganisationController::store
* @see app/Http/Controllers/OrganisationController.php:41
* @route '/organisations'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrganisationController::store
* @see app/Http/Controllers/OrganisationController.php:41
* @route '/organisations'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\OrganisationController::show
* @see app/Http/Controllers/OrganisationController.php:50
* @route '/organisations/{organisation}'
*/
export const show = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/organisations/{organisation}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OrganisationController::show
* @see app/Http/Controllers/OrganisationController.php:50
* @route '/organisations/{organisation}'
*/
show.url = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { organisation: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { organisation: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            organisation: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        organisation: typeof args.organisation === 'object'
        ? args.organisation.id
        : args.organisation,
    }

    return show.definition.url
            .replace('{organisation}', parsedArgs.organisation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrganisationController::show
* @see app/Http/Controllers/OrganisationController.php:50
* @route '/organisations/{organisation}'
*/
show.get = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrganisationController::show
* @see app/Http/Controllers/OrganisationController.php:50
* @route '/organisations/{organisation}'
*/
show.head = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OrganisationController::show
* @see app/Http/Controllers/OrganisationController.php:50
* @route '/organisations/{organisation}'
*/
const showForm = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrganisationController::show
* @see app/Http/Controllers/OrganisationController.php:50
* @route '/organisations/{organisation}'
*/
showForm.get = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrganisationController::show
* @see app/Http/Controllers/OrganisationController.php:50
* @route '/organisations/{organisation}'
*/
showForm.head = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

/**
* @see \App\Http\Controllers\CustomerDocumentController::statement
* @see app/Http/Controllers/CustomerDocumentController.php:56
* @route '/organisations/{organisation}/statement.pdf'
*/
export const statement = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: statement.url(args, options),
    method: 'get',
})

statement.definition = {
    methods: ["get","head"],
    url: '/organisations/{organisation}/statement.pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CustomerDocumentController::statement
* @see app/Http/Controllers/CustomerDocumentController.php:56
* @route '/organisations/{organisation}/statement.pdf'
*/
statement.url = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { organisation: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { organisation: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            organisation: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        organisation: typeof args.organisation === 'object'
        ? args.organisation.id
        : args.organisation,
    }

    return statement.definition.url
            .replace('{organisation}', parsedArgs.organisation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CustomerDocumentController::statement
* @see app/Http/Controllers/CustomerDocumentController.php:56
* @route '/organisations/{organisation}/statement.pdf'
*/
statement.get = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: statement.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CustomerDocumentController::statement
* @see app/Http/Controllers/CustomerDocumentController.php:56
* @route '/organisations/{organisation}/statement.pdf'
*/
statement.head = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: statement.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\CustomerDocumentController::statement
* @see app/Http/Controllers/CustomerDocumentController.php:56
* @route '/organisations/{organisation}/statement.pdf'
*/
const statementForm = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: statement.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CustomerDocumentController::statement
* @see app/Http/Controllers/CustomerDocumentController.php:56
* @route '/organisations/{organisation}/statement.pdf'
*/
statementForm.get = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: statement.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CustomerDocumentController::statement
* @see app/Http/Controllers/CustomerDocumentController.php:56
* @route '/organisations/{organisation}/statement.pdf'
*/
statementForm.head = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: statement.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

statement.form = statementForm

const organisations = {
    index: Object.assign(index, index),
    store: Object.assign(store, store),
    show: Object.assign(show, show),
    memberships: Object.assign(memberships, memberships),
    statement: Object.assign(statement, statement),
}

export default organisations