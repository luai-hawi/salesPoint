<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access to a specific feature when the shop-owner's plan has it disabled.
 *
 * Usage in routes:
 *   ->middleware('tier.feature:installments')
 *   ->middleware('tier.feature:sales_promotions')
 *   ->middleware('tier.feature:financial_dashboard')
 */
class TierFeatureMiddleware
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = auth()->user();

        if ($user && !$user->canAccessFeature($feature)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => __('messages.tier_feature_blocked'),
                ], 403);
            }

            abort(403, __('messages.tier_feature_blocked'));
        }

        return $next($request);
    }
}
