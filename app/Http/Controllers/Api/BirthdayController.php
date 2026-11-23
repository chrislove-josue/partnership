<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BirthdayService;
use App\Services\ChurchAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BirthdayController extends Controller
{
    public function __construct(
        private ChurchAccessService $access,
        private BirthdayService $birthdays,
    ) {
    }

    /**
     * Liste des anniversaires d'un mois pour les églises accessibles.
     * GET /api/birthdays?month=11&year=2026
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $month = $request->integer('month') ?: (int) now()->month;
        $year = $request->integer('year') ?: (int) now()->year;

        $items = $this->birthdays->monthBirthdays(
            $month,
            $year,
            $this->access->accessibleChurchIds($request->user()),
        );

        return response()->json([
            'month' => $month,
            'year' => $year,
            'label' => \Illuminate\Support\Carbon::create($year, $month, 1)->locale('fr')->isoFormat('MMMM YYYY'),
            'count' => $items->count(),
            'data' => $items,
        ]);
    }
}
