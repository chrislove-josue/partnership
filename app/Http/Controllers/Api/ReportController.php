<?php

namespace App\Http\Controllers\Api;

use App\Enums\PeriodType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ChurchAccessService;
use App\Services\PartnershipReportExporter;
use App\Services\PartnershipReportSender;
use App\Services\PartnershipReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class ReportController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        try {
            [$user, $churchId, $type, $year, $index] = $this->selection($request);

            return response()->json(app(PartnershipReportService::class)
                ->filterByCategoryGroup(
                    app(PartnershipReportService::class)->buildReport($churchId, $type, $year, $index),
                    (string) $request->string('category_group'),
                ));
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function recipients(Request $request): JsonResponse
    {
        try {
            [$user, $churchId, $type, $year, $index] = $this->selection($request);
            $preview = app(PartnershipReportSender::class)
                ->preview($user, $churchId, $type, $year, $index);

            return response()->json([
                'recipients' => $preview['recipients'],
                'cc' => $preview['cc'],
                'targetLabel' => $preview['targetLabel'],
                'church' => $preview['church'],
                'period' => $preview['period'],
                'totals' => $preview['totals'],
                'confirmRequired' => true,
            ]);
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function export(Request $request)
    {
        try {
            [, $churchId, $type, $year, $index] = $this->selection($request);
            $reports = app(PartnershipReportService::class);
            $report = $reports->buildReport($churchId, $type, $year, $index);
            $report = $reports->filterByCategoryGroup($report, (string) $request->string('category_group'));
            $xlsx = app(PartnershipReportExporter::class)->toXlsx($report);

            return Response::make($xlsx, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$reports->fileName($report).'"',
            ]);
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'confirm' => ['required', 'boolean'],
            'cc_emails' => ['nullable', 'array'],
            'cc_emails.*' => ['email'],
            'category_group' => ['nullable', 'string'],
        ], [
            'confirm.required' => 'La confirmation est obligatoire.',
            'cc_emails.array' => 'Les copies doivent être une liste d’adresses email.',
            'cc_emails.*.email' => 'Une adresse email de copie est invalide.',
        ]);

        try {
            [$user, $churchId, $type, $year, $index] = $this->selection($request);

            $ccEmails = array_key_exists('cc_emails', $data) ? $data['cc_emails'] : null;

            $result = app(PartnershipReportSender::class)->send(
                $user,
                $churchId,
                $type,
                $year,
                $index,
                $request->boolean('confirm'),
                $ccEmails,
                $data['category_group'] ?? null,
            );

            return response()->json($result);
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            return response()->json(['message' => 'Échec de l’envoi : '.$exception->getMessage()], 500);
        }
    }

    /**
     * @return array{0: User, 1: int, 2: PeriodType, 3: int, 4: int|null}
     */
    private function selection(Request $request): array
    {
        $data = $request->validate([
            'church_id' => ['required', 'integer'],
            'type' => ['required', Rule::enum(PeriodType::class)],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'quarter' => ['nullable', 'integer', 'min:1', 'max:4'],
        ], [
            'church_id.required' => 'La sélection d’une église est obligatoire.',
            'type.required' => 'La période est obligatoire.',
            'year.required' => 'L’année est obligatoire.',
        ]);

        $user = $request->user();
        $churchId = (int) $data['church_id'];

        if (! app(ChurchAccessService::class)->canAccessChurchData($user, $churchId)) {
            throw new AuthorizationException('Accès refusé à cette église');
        }

        $type = PeriodType::from($data['type']);

        return [
            $user,
            $churchId,
            $type,
            (int) $data['year'],
            match ($type) {
                PeriodType::MONTH => (int) ($data['month'] ?? 1),
                PeriodType::QUARTER => (int) ($data['quarter'] ?? 1),
                PeriodType::YEAR => null,
            },
        ];
    }
}
