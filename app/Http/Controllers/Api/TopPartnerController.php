<?php

namespace App\Http\Controllers\Api;

use App\Enums\CategoryGroup;
use App\Enums\PeriodType;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Services\ChurchAccessService;
use App\Services\TopPartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TopPartnerController extends Controller
{
    public function __construct(
        private ChurchAccessService $access,
        private TopPartnerService $topPartners,
    ) {}

    /**
     * Classement des partenaires par montant donné sur une période.
     * GET /api/top-partners?period=month|quarter|year&month=11&quarter=4&year=2026&church_id=
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:month,quarter,year'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'quarter' => ['nullable', 'integer', 'min:1', 'max:4'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'church_id' => ['nullable', 'string'],
            'category_group' => ['nullable', 'string', 'in:church_finances,ministry_partnership,local_partnership'],
            'category_id' => ['nullable', 'integer', 'exists:category_dons,id'],
        ]);

        $period = PeriodType::tryFrom($request->string('period')->toString()) ?? PeriodType::MONTH;
        $year = $request->integer('year') ?: (int) now()->year;

        $index = match ($period) {
            PeriodType::MONTH => $request->integer('month') ?: (int) now()->month,
            PeriodType::QUARTER => $request->integer('quarter') ?: (int) now()->quarter,
            PeriodType::YEAR => 1,
        };

        $user = $request->user();
        $accessibleIds = array_map('intval', $this->access->accessibleChurchIds($user));

        // Seuls le super admin et le pasteur de groupe peuvent voir toutes
        // les églises de la base (`church_id=all`).
        $canSeeAllChurches = $user->isSuperAdmin()
            || $user->roleName() === RoleName::GROUP_PASTOR->value;

        if ($request->string('church_id')->toString() === 'all' && ! $canSeeAllChurches) {
            abort(403, 'Seul le super administrateur ou un pasteur de groupe peut voir toutes les églises.');
        }

        // Par défaut : église affectée. Une église précise = celle-ci ; vide =
        // toutes les églises accessibles ; `all` = toutes les églises de la base.
        $requestedId = $request->integer('church_id');
        $defaultId = (int) ($this->access->defaultChurchId($user) ?? 0);
        $rawChurchId = $request->string('church_id')->toString();

        if ($rawChurchId === 'all') {
            $churchIds = Church::query()->pluck('id')->map('intval')->all();
        } elseif ($request->has('church_id')) {
            $churchIds = $requestedId && in_array($requestedId, $accessibleIds, true)
                ? [$requestedId]
                : $accessibleIds;
        } else {
            $churchIds = $defaultId && in_array($defaultId, $accessibleIds, true)
                ? [$defaultId]
                : $accessibleIds;
        }

        $group = CategoryGroup::tryFrom($request->string('category_group')->toString());

        $items = $this->topPartners->rank(
            $churchIds,
            $period,
            $year,
            $index,
            20,
            $group,
            $request->integer('category_id') ?: null,
        );

        [$from, $to] = $this->topPartners->range($period, $year, $index);

        return response()->json([
            'period' => $period->value,
            'year' => $year,
            'index' => $index,
            'church_id' => count($churchIds) === 1 ? $churchIds[0] : null,
            'churches' => count($churchIds),
            'can_see_all' => $canSeeAllChurches,
            'category_group' => $group?->value,
            'category_id' => $request->integer('category_id') ?: null,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'count' => $items->count(),
            'data' => $items,
        ]);
    }
}
