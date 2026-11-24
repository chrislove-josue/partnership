<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BirthdayService;
use App\Services\ChurchAccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BirthdayController extends Controller
{
    public function __construct(
        private ChurchAccessService $access,
        private BirthdayService $birthdays,
    ) {}

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

        $label = Carbon::create($year, $month, 1)
            ->locale('fr')
            ->isoFormat('MMMM YYYY');

        return response()->json([
            'month' => $month,
            'year' => $year,
            'label' => Str::ucfirst($label),
            'count' => $items->count(),
            'data' => $items,
        ]);
    }

    /**
     * Exporte la liste des anniversaires du mois en PDF.
     * GET /api/birthdays/export?month=11&year=2026
     */
    public function export(Request $request)
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

        $label = Str::ucfirst(
            Carbon::create($year, $month, 1)->locale('fr')->isoFormat('MMMM YYYY'),
        );

        $pdf = Pdf::loadView('pdf.birthdays', [
            'items' => $items,
            'label' => $label,
            'count' => $items->count(),
        ])->setPaper('a4', 'portrait');

        $filename = sprintf('anniversaires_%04d-%02d.pdf', $year, $month);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
