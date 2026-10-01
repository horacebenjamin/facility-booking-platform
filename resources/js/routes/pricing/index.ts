import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\QuotePricingController::__invoke
* @see app/Http/Controllers/QuotePricingController.php:20
* @route '/pricing/quote'
*/
export const quote = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: quote.url(options),
    method: 'post',
})

quote.definition = {
    methods: ["post"],
    url: '/pricing/quote',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\QuotePricingController::__invoke
* @see app/Http/Controllers/QuotePricingController.php:20
* @route '/pricing/quote'
*/
quote.url = (options?: RouteQueryOptions) => {
    return quote.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\QuotePricingController::__invoke
* @see app/Http/Controllers/QuotePricingController.php:20
* @route '/pricing/quote'
*/
quote.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: quote.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\QuotePricingController::__invoke
* @see app/Http/Controllers/QuotePricingController.php:20
* @route '/pricing/quote'
*/
const quoteForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: quote.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\QuotePricingController::__invoke
* @see app/Http/Controllers/QuotePricingController.php:20
* @route '/pricing/quote'
*/
quoteForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: quote.url(options),
    method: 'post',
})

quote.form = quoteForm

const pricing = {
    quote: Object.assign(quote, quote),
}

export default pricing