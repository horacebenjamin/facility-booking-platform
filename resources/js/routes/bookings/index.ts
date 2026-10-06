import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
import payment from './payment'
import recurring from './recurring'
/**
* @see \App\Http\Controllers\CustomerBookingController::index
* @see app/Http/Controllers/CustomerBookingController.php:23
* @route '/bookings'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/bookings',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CustomerBookingController::index
* @see app/Http/Controllers/CustomerBookingController.php:23
* @route '/bookings'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CustomerBookingController::index
* @see app/Http/Controllers/CustomerBookingController.php:23
* @route '/bookings'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CustomerBookingController::index
* @see app/Http/Controllers/CustomerBookingController.php:23
* @route '/bookings'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\CustomerBookingController::index
* @see app/Http/Controllers/CustomerBookingController.php:23
* @route '/bookings'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CustomerBookingController::index
* @see app/Http/Controllers/CustomerBookingController.php:23
* @route '/bookings'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CustomerBookingController::index
* @see app/Http/Controllers/CustomerBookingController.php:23
* @route '/bookings'
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
* @see \App\Http\Controllers\BookingReviewController::__invoke
* @see app/Http/Controllers/BookingReviewController.php:21
* @route '/bookings/review'
*/
export const review = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: review.url(options),
    method: 'get',
})

review.definition = {
    methods: ["get","head"],
    url: '/bookings/review',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\BookingReviewController::__invoke
* @see app/Http/Controllers/BookingReviewController.php:21
* @route '/bookings/review'
*/
review.url = (options?: RouteQueryOptions) => {
    return review.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\BookingReviewController::__invoke
* @see app/Http/Controllers/BookingReviewController.php:21
* @route '/bookings/review'
*/
review.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: review.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\BookingReviewController::__invoke
* @see app/Http/Controllers/BookingReviewController.php:21
* @route '/bookings/review'
*/
review.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: review.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\BookingReviewController::__invoke
* @see app/Http/Controllers/BookingReviewController.php:21
* @route '/bookings/review'
*/
const reviewForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: review.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\BookingReviewController::__invoke
* @see app/Http/Controllers/BookingReviewController.php:21
* @route '/bookings/review'
*/
reviewForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: review.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\BookingReviewController::__invoke
* @see app/Http/Controllers/BookingReviewController.php:21
* @route '/bookings/review'
*/
reviewForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: review.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

review.form = reviewForm

/**
* @see \App\Http\Controllers\CustomerBookingController::show
* @see app/Http/Controllers/CustomerBookingController.php:52
* @route '/bookings/{booking}'
*/
export const show = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/bookings/{booking}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CustomerBookingController::show
* @see app/Http/Controllers/CustomerBookingController.php:52
* @route '/bookings/{booking}'
*/
show.url = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { booking: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { booking: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            booking: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        booking: typeof args.booking === 'object'
        ? args.booking.id
        : args.booking,
    }

    return show.definition.url
            .replace('{booking}', parsedArgs.booking.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CustomerBookingController::show
* @see app/Http/Controllers/CustomerBookingController.php:52
* @route '/bookings/{booking}'
*/
show.get = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CustomerBookingController::show
* @see app/Http/Controllers/CustomerBookingController.php:52
* @route '/bookings/{booking}'
*/
show.head = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\CustomerBookingController::show
* @see app/Http/Controllers/CustomerBookingController.php:52
* @route '/bookings/{booking}'
*/
const showForm = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CustomerBookingController::show
* @see app/Http/Controllers/CustomerBookingController.php:52
* @route '/bookings/{booking}'
*/
showForm.get = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CustomerBookingController::show
* @see app/Http/Controllers/CustomerBookingController.php:52
* @route '/bookings/{booking}'
*/
showForm.head = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\CustomerBookingController::cancel
* @see app/Http/Controllers/CustomerBookingController.php:74
* @route '/bookings/{booking}/cancel'
*/
export const cancel = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

cancel.definition = {
    methods: ["post"],
    url: '/bookings/{booking}/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CustomerBookingController::cancel
* @see app/Http/Controllers/CustomerBookingController.php:74
* @route '/bookings/{booking}/cancel'
*/
cancel.url = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { booking: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { booking: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            booking: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        booking: typeof args.booking === 'object'
        ? args.booking.id
        : args.booking,
    }

    return cancel.definition.url
            .replace('{booking}', parsedArgs.booking.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CustomerBookingController::cancel
* @see app/Http/Controllers/CustomerBookingController.php:74
* @route '/bookings/{booking}/cancel'
*/
cancel.post = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CustomerBookingController::cancel
* @see app/Http/Controllers/CustomerBookingController.php:74
* @route '/bookings/{booking}/cancel'
*/
const cancelForm = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: cancel.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CustomerBookingController::cancel
* @see app/Http/Controllers/CustomerBookingController.php:74
* @route '/bookings/{booking}/cancel'
*/
cancelForm.post = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: cancel.url(args, options),
    method: 'post',
})

cancel.form = cancelForm

/**
* @see \App\Http\Controllers\CustomerBookingController::amend
* @see app/Http/Controllers/CustomerBookingController.php:89
* @route '/bookings/{booking}'
*/
export const amend = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: amend.url(args, options),
    method: 'patch',
})

amend.definition = {
    methods: ["patch"],
    url: '/bookings/{booking}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\CustomerBookingController::amend
* @see app/Http/Controllers/CustomerBookingController.php:89
* @route '/bookings/{booking}'
*/
amend.url = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { booking: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { booking: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            booking: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        booking: typeof args.booking === 'object'
        ? args.booking.id
        : args.booking,
    }

    return amend.definition.url
            .replace('{booking}', parsedArgs.booking.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CustomerBookingController::amend
* @see app/Http/Controllers/CustomerBookingController.php:89
* @route '/bookings/{booking}'
*/
amend.patch = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: amend.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\CustomerBookingController::amend
* @see app/Http/Controllers/CustomerBookingController.php:89
* @route '/bookings/{booking}'
*/
const amendForm = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: amend.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CustomerBookingController::amend
* @see app/Http/Controllers/CustomerBookingController.php:89
* @route '/bookings/{booking}'
*/
amendForm.patch = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: amend.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

amend.form = amendForm

/**
* @see \App\Http\Controllers\StoreBookingRequestController::__invoke
* @see app/Http/Controllers/StoreBookingRequestController.php:15
* @route '/bookings'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/bookings',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\StoreBookingRequestController::__invoke
* @see app/Http/Controllers/StoreBookingRequestController.php:15
* @route '/bookings'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StoreBookingRequestController::__invoke
* @see app/Http/Controllers/StoreBookingRequestController.php:15
* @route '/bookings'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\StoreBookingRequestController::__invoke
* @see app/Http/Controllers/StoreBookingRequestController.php:15
* @route '/bookings'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\StoreBookingRequestController::__invoke
* @see app/Http/Controllers/StoreBookingRequestController.php:15
* @route '/bookings'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

const bookings = {
    index: Object.assign(index, index),
    payment: Object.assign(payment, payment),
    review: Object.assign(review, review),
    show: Object.assign(show, show),
    cancel: Object.assign(cancel, cancel),
    amend: Object.assign(amend, amend),
    store: Object.assign(store, store),
    recurring: Object.assign(recurring, recurring),
}

export default bookings