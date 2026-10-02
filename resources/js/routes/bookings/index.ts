import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\BookingReviewController::__invoke
* @see app/Http/Controllers/BookingReviewController.php:19
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
* @see app/Http/Controllers/BookingReviewController.php:19
* @route '/bookings/review'
*/
review.url = (options?: RouteQueryOptions) => {
    return review.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\BookingReviewController::__invoke
* @see app/Http/Controllers/BookingReviewController.php:19
* @route '/bookings/review'
*/
review.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: review.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\BookingReviewController::__invoke
* @see app/Http/Controllers/BookingReviewController.php:19
* @route '/bookings/review'
*/
review.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: review.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\BookingReviewController::__invoke
* @see app/Http/Controllers/BookingReviewController.php:19
* @route '/bookings/review'
*/
const reviewForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: review.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\BookingReviewController::__invoke
* @see app/Http/Controllers/BookingReviewController.php:19
* @route '/bookings/review'
*/
reviewForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: review.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\BookingReviewController::__invoke
* @see app/Http/Controllers/BookingReviewController.php:19
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
* @see \App\Http\Controllers\StoreBookingRequestController::__invoke
* @see app/Http/Controllers/StoreBookingRequestController.php:14
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
* @see app/Http/Controllers/StoreBookingRequestController.php:14
* @route '/bookings'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StoreBookingRequestController::__invoke
* @see app/Http/Controllers/StoreBookingRequestController.php:14
* @route '/bookings'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\StoreBookingRequestController::__invoke
* @see app/Http/Controllers/StoreBookingRequestController.php:14
* @route '/bookings'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\StoreBookingRequestController::__invoke
* @see app/Http/Controllers/StoreBookingRequestController.php:14
* @route '/bookings'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

const bookings = {
    review: Object.assign(review, review),
    store: Object.assign(store, store),
}

export default bookings