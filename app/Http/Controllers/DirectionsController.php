<?php

namespace App\Http\Controllers;

use App\Contracts\DirectionsServiceInterface;
use App\Http\Requests\DirectionsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DirectionsController extends Controller
{
    public function __construct(
        private readonly DirectionsServiceInterface $directionsService,
    ) {}

    /**
     * Return a road-network route between two coordinate pairs.
     *
     * Result is cached server-side on rounded coordinates (5 decimals ≈ ~1m)
     * with a short TTL so repeated requests from the same responder don't
     * hammer the upstream provider.
     */
    public function __invoke(DirectionsRequest $request): JsonResponse
    {
        $data = $request->validated();

        $fromLat = (float) $data['from_lat'];
        $fromLng = (float) $data['from_lng'];
        $toLat = (float) $data['to_lat'];
        $toLng = (float) $data['to_lng'];

        $cacheKey = sprintf(
            'directions:%s,%s;%s,%s',
            number_format($fromLat, 5, '.', ''),
            number_format($fromLng, 5, '.', ''),
            number_format($toLat, 5, '.', ''),
            number_format($toLng, 5, '.', ''),
        );

        try {
            $payload = Cache::remember($cacheKey, now()->addSeconds(60), function () use ($fromLat, $fromLng, $toLat, $toLng): array {
                $route = $this->directionsService->route($fromLat, $fromLng, $toLat, $toLng);

                return [
                    'coordinates' => $route['coordinates'],
                    'distance_km' => round($route['distance_meters'] / 1000, 2),
                    'duration_min' => max(1, (int) round($route['duration_seconds'] / 60)),
                    'steps' => $route['steps'] ?? [],
                ];
            });
        } catch (\Throwable $e) {
            Log::warning('DirectionsController upstream failure', [
                'message' => $e->getMessage(),
                'from' => [$fromLat, $fromLng],
                'to' => [$toLat, $toLng],
            ]);

            return response()->json([
                'message' => 'Unable to compute route.',
            ], 502);
        }

        return response()->json($payload);
    }
}
