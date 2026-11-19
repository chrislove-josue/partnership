<?php

use Illuminate\Support\Facades\DB;

$out = [];

$tables = collect(DB::select('SHOW TABLES'))->map(fn ($row) => array_values((array) $row)[0]);

$counts = [];
foreach ($tables as $table) {
    $counts[$table] = DB::table($table)->count();
}
$out['tables'] = $counts;

$out['churches_by_type'] = DB::table('churches')
    ->select('type', DB::raw('COUNT(*) as total'))
    ->groupBy('type')
    ->orderBy('type')
    ->get()
    ->map(fn ($r) => (array) $r)
    ->all();

$out['churches'] = DB::table('churches as c')
    ->leftJoin('churches as p', 'p.id', '=', 'c.parent_id')
    ->leftJoin('users as m', 'm.id', '=', 'c.manager_id')
    ->orderBy('c.id')
    ->get([
        'c.id', 'c.name', 'c.type', 'c.parent_id', 'p.name as parent_name',
        'c.manager_id', 'm.name as manager_name', 'm.email as manager_email',
    ])
    ->map(fn ($r) => (array) $r)
    ->all();

$out['users_by_role'] = DB::table('model_has_roles as mhr')
    ->join('roles as r', 'r.id', '=', 'mhr.role_id')
    ->select('r.name', DB::raw('COUNT(*) as total'))
    ->groupBy('r.name')
    ->orderBy('r.name')
    ->get()
    ->map(fn ($r) => (array) $r)
    ->all();

$out['users'] = DB::table('users as u')
    ->leftJoin('churches as c', 'c.id', '=', 'u.assigned_church_id')
    ->orderBy('u.id')
    ->get([
        'u.id', 'u.name', 'u.email', 'u.is_active', 'c.name as church_name', 'c.type as church_type',
    ])
    ->map(fn ($r) => (array) $r)
    ->all();

$out['partners_by_church'] = DB::table('partners as p')
    ->leftJoin('churches as c', 'c.id', '=', 'p.church_id')
    ->select('c.name as church_name', DB::raw('COUNT(*) as total'))
    ->groupBy('c.name')
    ->orderByDesc('total')
    ->get()
    ->map(fn ($r) => (array) $r)
    ->all();

$out['donations_by_church'] = DB::table('donations as d')
    ->join('churches as c', 'c.id', '=', 'd.church_id')
    ->select('c.name as church_name', DB::raw('COUNT(*) as total'), DB::raw('COALESCE(SUM(d.amount),0) as amount'))
    ->groupBy('c.name')
    ->orderByDesc('amount')
    ->get()
    ->map(fn ($r) => (array) $r)
    ->all();

$out['donations_by_category'] = DB::table('donations as d')
    ->join('category_dons as cd', 'cd.id', '=', 'd.category_id')
    ->select('cd.name', DB::raw('COUNT(*) as total'), DB::raw('COALESCE(SUM(d.amount),0) as amount'))
    ->groupBy('cd.name')
    ->orderByDesc('amount')
    ->get()
    ->map(fn ($r) => (array) $r)
    ->all();

$out['donations_by_month'] = DB::table('donations')
    ->select(DB::raw("DATE_FORMAT(date, '%Y-%m') as mois"), DB::raw('COUNT(*) as total'), DB::raw('COALESCE(SUM(amount),0) as amount'))
    ->groupBy('mois')
    ->orderBy('mois')
    ->get()
    ->map(fn ($r) => (array) $r)
    ->all();

$out['donation_range'] = (array) DB::table('donations')
    ->select(DB::raw('MIN(date) as debut'), DB::raw('MAX(date) as fin'), DB::raw('COUNT(DISTINCT partner_id) as partenaires'), DB::raw('COUNT(DISTINCT church_id) as eglises'))
    ->first();

$out['cellules_by_church'] = DB::table('cellules as ce')
    ->leftJoin('churches as c', 'c.id', '=', 'ce.church_id')
    ->select('c.name as church_name', DB::raw('COUNT(*) as total'))
    ->groupBy('c.name')
    ->orderByDesc('total')
    ->get()
    ->map(fn ($r) => (array) $r)
    ->all();

$out['integrity'] = [
    'partners_without_church' => DB::table('partners')->whereNull('church_id')->count(),
    'partners_without_cellule' => DB::table('partners')->whereNull('cellule_id')->count(),
    'partners_without_donation' => DB::table('partners')->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('donations')->whereColumn('donations.partner_id', 'partners.id'))->count(),
    'donations_without_partner' => DB::table('donations')->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('partners')->whereColumn('partners.id', 'donations.partner_id'))->count(),
    'churches_without_manager' => DB::table('churches')->whereNull('manager_id')->count(),
    'churches_with_parent' => DB::table('churches')->whereNotNull('parent_id')->count(),
    'churches_without_parent' => DB::table('churches')->whereNull('parent_id')->count(),
    'users_without_church' => DB::table('users')->whereNull('assigned_church_id')->count(),
    'users_inactive' => DB::table('users')->where('is_active', false)->count(),
    'duplicate_emails' => DB::table('partners')->select('email')->whereNotNull('email')->where('email', '!=', '')->groupBy('email')->havingRaw('COUNT(*) > 1')->get()->count(),
    'duplicate_phones' => DB::table('partners')->select('phone')->whereNotNull('phone')->where('phone', '!=', '')->groupBy('phone')->havingRaw('COUNT(*) > 1')->get()->count(),
    'cellules_without_leader' => DB::table('cellules')->whereNull('leader_id')->count(),
    'donations_zero_or_negative' => DB::table('donations')->where('amount', '<=', 0)->count(),
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
