import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\OrganisationMembershipController::store
* @see app/Http/Controllers/OrganisationMembershipController.php:18
* @route '/organisations/{organisation}/memberships'
*/
export const store = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/organisations/{organisation}/memberships',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrganisationMembershipController::store
* @see app/Http/Controllers/OrganisationMembershipController.php:18
* @route '/organisations/{organisation}/memberships'
*/
store.url = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{organisation}', parsedArgs.organisation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrganisationMembershipController::store
* @see app/Http/Controllers/OrganisationMembershipController.php:18
* @route '/organisations/{organisation}/memberships'
*/
store.post = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrganisationMembershipController::store
* @see app/Http/Controllers/OrganisationMembershipController.php:18
* @route '/organisations/{organisation}/memberships'
*/
const storeForm = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrganisationMembershipController::store
* @see app/Http/Controllers/OrganisationMembershipController.php:18
* @route '/organisations/{organisation}/memberships'
*/
storeForm.post = (args: { organisation: number | { id: number } } | [organisation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\OrganisationMembershipController::update
* @see app/Http/Controllers/OrganisationMembershipController.php:32
* @route '/organisations/{organisation}/memberships/{membership}'
*/
export const update = (args: { organisation: number | { id: number }, membership: number | { id: number } } | [organisation: number | { id: number }, membership: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/organisations/{organisation}/memberships/{membership}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\OrganisationMembershipController::update
* @see app/Http/Controllers/OrganisationMembershipController.php:32
* @route '/organisations/{organisation}/memberships/{membership}'
*/
update.url = (args: { organisation: number | { id: number }, membership: number | { id: number } } | [organisation: number | { id: number }, membership: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            organisation: args[0],
            membership: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        organisation: typeof args.organisation === 'object'
        ? args.organisation.id
        : args.organisation,
        membership: typeof args.membership === 'object'
        ? args.membership.id
        : args.membership,
    }

    return update.definition.url
            .replace('{organisation}', parsedArgs.organisation.toString())
            .replace('{membership}', parsedArgs.membership.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrganisationMembershipController::update
* @see app/Http/Controllers/OrganisationMembershipController.php:32
* @route '/organisations/{organisation}/memberships/{membership}'
*/
update.patch = (args: { organisation: number | { id: number }, membership: number | { id: number } } | [organisation: number | { id: number }, membership: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\OrganisationMembershipController::update
* @see app/Http/Controllers/OrganisationMembershipController.php:32
* @route '/organisations/{organisation}/memberships/{membership}'
*/
const updateForm = (args: { organisation: number | { id: number }, membership: number | { id: number } } | [organisation: number | { id: number }, membership: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrganisationMembershipController::update
* @see app/Http/Controllers/OrganisationMembershipController.php:32
* @route '/organisations/{organisation}/memberships/{membership}'
*/
updateForm.patch = (args: { organisation: number | { id: number }, membership: number | { id: number } } | [organisation: number | { id: number }, membership: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

/**
* @see \App\Http\Controllers\OrganisationMembershipController::destroy
* @see app/Http/Controllers/OrganisationMembershipController.php:51
* @route '/organisations/{organisation}/memberships/{membership}'
*/
export const destroy = (args: { organisation: number | { id: number }, membership: number | { id: number } } | [organisation: number | { id: number }, membership: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/organisations/{organisation}/memberships/{membership}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\OrganisationMembershipController::destroy
* @see app/Http/Controllers/OrganisationMembershipController.php:51
* @route '/organisations/{organisation}/memberships/{membership}'
*/
destroy.url = (args: { organisation: number | { id: number }, membership: number | { id: number } } | [organisation: number | { id: number }, membership: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            organisation: args[0],
            membership: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        organisation: typeof args.organisation === 'object'
        ? args.organisation.id
        : args.organisation,
        membership: typeof args.membership === 'object'
        ? args.membership.id
        : args.membership,
    }

    return destroy.definition.url
            .replace('{organisation}', parsedArgs.organisation.toString())
            .replace('{membership}', parsedArgs.membership.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrganisationMembershipController::destroy
* @see app/Http/Controllers/OrganisationMembershipController.php:51
* @route '/organisations/{organisation}/memberships/{membership}'
*/
destroy.delete = (args: { organisation: number | { id: number }, membership: number | { id: number } } | [organisation: number | { id: number }, membership: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\OrganisationMembershipController::destroy
* @see app/Http/Controllers/OrganisationMembershipController.php:51
* @route '/organisations/{organisation}/memberships/{membership}'
*/
const destroyForm = (args: { organisation: number | { id: number }, membership: number | { id: number } } | [organisation: number | { id: number }, membership: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrganisationMembershipController::destroy
* @see app/Http/Controllers/OrganisationMembershipController.php:51
* @route '/organisations/{organisation}/memberships/{membership}'
*/
destroyForm.delete = (args: { organisation: number | { id: number }, membership: number | { id: number } } | [organisation: number | { id: number }, membership: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const memberships = {
    store: Object.assign(store, store),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default memberships