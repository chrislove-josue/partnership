<?php

namespace App\Http\Controllers\Api;

use App\Enums\CategoryGroup;
use App\Enums\PeriodType;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Services\ChurchAccessService;
use App\Services\TopChurchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TopChurchController extends Controller
{
    public function __construct(
        private ChurchAccessService $access,
        private TopChurchService $topChurches,
    ) {}

    /** GET /api/top-churches?period=month|quarter|year&month=&quarter=&year= */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:month,quarter,year'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'quarter' => ['nullable', 'integer', 'min:1', 'max:4'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $user = $request->user();

        // Réservé au super admin et au pasteur de groupe.
        $canSeeAllChurches = $user->isSuperAdmin()
            || $user->roleName() === RoleName::GROUP_PASTOR->value;

        if (! $canSeeAllChurches) {
            abort(403, 'Ce classement est réservé au super administrateur et au pasteur de groupe.');
        }

        $period = PeriodType::tryFrom($request->string('period')->toString()) ?? PeriodType::MONTH;
        $year = $request->integer('year') ?: (int) now()->year;
        $index = match ($period) {
            PeriodType::MONTH => $request->integer('month') ?: (int) now()->month,
            PeriodType::QUARTER => $request->integer('quarter') ?: (int) now()->quarter,
            PeriodType::YEAR => 1,
        };

        $items = $this->topChurches->rank(
            $this->access->accessibleChurchIds($user),
            $period,
            $year,
            $index,
        );

        return response()->json([
            'period' => $period->value,
            'year' => $year,
            'index' => $index,
            'can_see_all' => $canSeeAllChurches,
            'count' => $items->count(),
            'data' => $items,
        ]);
    }
}
