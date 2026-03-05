<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AnalyticsUserSession;
use App\Models\AnalyticsProductView;
use App\Models\AnalyticsCartActivity;
use App\Models\AnalyticsUserStat;
use Jenssegers\Agent\Agent;


class Analytic_viewController extends Controller
{
    public function trackSession(Request $request)
    {
        $agent = new Agent();

        AnalyticsUserSession::updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'ip_address'     => $request->ip(),
                'country'        => geoip($request->ip())->country ?? 'Unknown',
                'device'         => $agent->device(),
                'browser'        => $agent->browser(),
                'platform'       => $agent->platform(),
                'last_activity'  => now()
            ]
        );

        return response()->json(['success' => true]);
    }

    public function trackProductView($productId)
    {
        AnalyticsProductView::create([
            'user_id'   => Auth::id(),
            'product_id'=> $productId
        ]);

        return response()->json(['success' => true]);
    }

    public function trackAddToCart(Request $request)
    {
        AnalyticsCartActivity::create([
            'user_id'   => Auth::id(),
            'product_id'=> $request->product_id,
            'qty'       => $request->qty,
            'status'    => 'added'
        ]);

        return response()->json(['success' => true]);
    }

    public function trackRemoveFromCart(Request $request)
    {
        AnalyticsCartActivity::create([
            'user_id'   => Auth::id(),
            'product_id'=> $request->product_id,
            'qty'       => 0,
            'status'    => 'removed'
        ]);

        return response()->json(['success' => true]);
    }

    public function updateUserStats($userId, $orderAmount)
    {
        $stat = AnalyticsUserStat::firstOrNew([
            'user_id' => $userId
        ]);

        $stat->total_orders += 1;
        $stat->total_spent += $orderAmount;
        $stat->last_order_date = now();
        $stat->save();
    }

}
