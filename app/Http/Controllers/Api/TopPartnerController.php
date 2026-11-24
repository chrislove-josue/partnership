<?php

namespace App\Http\Controllers\Api;

use App\Enums\CategoryGroup;
use App\Enums\PeriodType;
use App\Http\Controllers\Controller;
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
     * GET /api/top-partners?period=month|quarter|year&month=11&quarter=4&year=2026
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:month,quarter,year'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'quarter' => ['nullable', 'integer', 'min:1', 'max:4'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'church_id' => ['nullable', 'integer'],
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

        // Une seule église créditée : jamais de classement mélangé.
        $churchId = $request->integer('church_id') ?: (int) ($this->access->defaultChurchId($user) ?? 0);
        $churchIds = in_array($churchId, $accessibleIds, true)
            ? [$churchId]
            : (($accessibleIds[0] ?? null) ? [$accessibleIds[0]] : []);

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
            'church_id' => $churchIds === [$churchId] ? $churchId : null,
            'category_group' => $group?->value,
            'category_id' => $request->integer('category_id') ?: null,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'count' => $items->count(),
            'data' => $items,
        ]);
    }
}
