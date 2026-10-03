import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\PreviewRecurringBookingRequestController::__invoke
* @see app/Http/Controllers/PreviewRecurringBookingRequestController.php:17
* @route '/bookings/recurring/preview'
*/
export const preview = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: preview.url(options),
    method: 'post',
})

preview.definition = {
    methods: ["post"],
    url: '/bookings/recurring/preview',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PreviewRecurringBookingRequestController::__invoke
* @see app/Http/Controllers/PreviewRecurringBookingRequestController.php:17
* @route '/bookings/recurring/preview'
*/
preview.url = (options?: RouteQueryOptions) => {
    return preview.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PreviewRecurringBookingRequestController::__invoke
* @see app/Http/Controllers/PreviewRecurringBookingRequestController.php:17
* @route '/bookings/recurring/preview'
*/
preview.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: preview.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\PreviewRecurringBookingRequestController::__invoke
* @see app/Http/Controllers/PreviewRecurringBookingRequestController.php:17
* @route '/bookings/recurring/preview'
*/
const previewForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: preview.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\PreviewRecurringBookingRequestController::__invoke
* @see app/Http/Controllers/PreviewRecurringBookingRequestController.php:17
* @route '/bookings/recurring/preview'
*/
previewForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: preview.url(options),
    method: 'post',
})

preview.form = previewForm

/**
* @see \App\Http\Controllers\StoreRecurringBookingRequestController::__invoke
* @see app/Http/Controllers/StoreRecurringBookingRequestController.php:17
* @route '/bookings/recurring'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/bookings/recurring',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\StoreRecurringBookingRequestController::__invoke
* @see app/Http/Controllers/StoreRecurringBookingRequestController.php:17
* @route '/bookings/recurring'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StoreRecurringBookingRequestController::__invoke
* @see app/Http/Controllers/StoreRecurringBookingRequestController.php:17
* @route '/bookings/recurring'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\StoreRecurringBookingRequestController::__invoke
* @see app/Http/Controllers/StoreRecurringBookingRequestController.php:17
* @route '/bookings/recurring'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\StoreRecurringBookingRequestController::__invoke
* @see app/Http/Controllers/StoreRecurringBookingRequestController.php:17
* @route '/bookings/recurring'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

const recurring = {
    preview: Object.assign(preview, preview),
    store: Object.assign(store, store),
}

export default recurring