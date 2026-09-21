<?php

namespace App\Domains\Sponsorships\Controllers\Api;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sponsorships\Models\SponsorshipOrder;
use App\Domains\Sponsorships\Requests\StoreSponsorshipOrderRequest;
use App\Domains\Sponsorships\Services\ResolveGuestSponsorUser;
use App\Domains\Sponsorships\Services\SponsorshipOrderService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SponsorshipOrderController extends Controller
{
    public function store(
        StoreSponsorshipOrderRequest $request,
        SponsorshipOrderService $orderService,
        ResolveGuestSponsorUser $resolveGuestUser
    ): JsonResponse {
        $user = $request->user() ?? $resolveGuestUser->handle((string) $request->validated('email'), $request);

        if ($user->isBanned()) {
            abort(403, 'Akun ini tidak dapat membuat pesanan sponsor.');
        }

        $amount = (int) $request->validated('amount');
        $redirectUrl = $request->validated('redirect_url');
        $entityId = $request->validated('entity_id');

        if ($entityId !== null) {
            /** @var Entity $entity */
            $entity = Entity::query()->findOrFail((int) $entityId);

            $order = $orderService->createOrder(
                $user,
                $entity,
                $amount,
                $redirectUrl,
                $request->validated('website_url')
            );
        } else {
            $order = $orderService->createOrderForNewEntity(
                $user,
                (string) $request->validated('new_entity_name'),
                (int) $request->validated('new_entity_category_id'),
                (string) $request->validated('new_entity_url'),
                $amount,
                $redirectUrl,
                $request->validated('new_entity_description')
            );
        }

        return response()->json([
            'message' => 'Pesanan sponsor berhasil dibuat.',
            'data' => [
                'id' => $order->id,
                'provider_order_id' => $order->provider_order_id,
                'provider_payment_id' => $order->provider_payment_id,
                'payment_link_url' => $order->payment_link_url,
                'amount' => $order->amount,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'expires_at' => $order->expires_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function show(Request $request, SponsorshipOrder $order): JsonResponse
    {
        $user = $request->user();
        if ($order->user_id !== $user->id && ! $user->isAdmin()) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $order->load(['sponsoredEntry.entity', 'sponsoredEntry.period']);

        return response()->json([
            'data' => [
                'id' => $order->id,
                'provider_order_id' => $order->provider_order_id,
                'amount' => $order->amount,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_link_url' => $order->payment_link_url,
                'paid_at' => $order->paid_at?->toIso8601String(),
                'entity' => [
                    'id' => $order->sponsoredEntry->entity->id,
                    'name' => $order->sponsoredEntry->entity->name,
                    'slug' => $order->sponsoredEntry->entity->slug,
                ],
                'period' => [
                    'key' => $order->sponsoredEntry->period->key,
                    'name' => $order->sponsoredEntry->period->name,
                ],
            ],
        ]);
    }
}
