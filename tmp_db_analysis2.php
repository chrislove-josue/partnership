<?php

use Illuminate\Support\Facades\DB;

$out = [];

$out['roles'] = DB::table('roles as r')
    ->leftJoin('model_has_roles as mhr', 'mhr.role_id', '=', 'r.id')
    ->select('r.id', 'r.name', DB::raw('COUNT(mhr.model_id) as users'))
    ->groupBy('r.id', 'r.name')
    ->orderBy('r.id')
    ->get()->map(fn ($r) => (array) $r)->all();

$out['permissions'] = DB::table('permissions')->orderBy('name')->pluck('name')->all();

$out['role_permissions'] = DB::table('roles as r')
    ->join('role_has_permissions as rp', 'rp.role_id', '=', 'r.id')
    ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
    ->select('r.name as role', 'p.name as permission')
    ->orderBy('r.name')->orderBy('p.name')
    ->get()->groupBy('role')->map(fn ($rows) => $rows->pluck('permission')->all())->all();

$out['user_roles'] = DB::table('users as u')
    ->join('model_has_roles as mhr', 'mhr.model_id', '=', 'u.id')
    ->join('roles as r', 'r.id', '=', 'mhr.role_id')
    ->select('u.id', 'u.email', 'r.name as role')
    ->orderBy('u.id')->orderBy('r.name')
    ->get()->groupBy('email')->map(fn ($rows) => $rows->pluck('role')->all())->all();

$out['categories'] = DB::table('category_dons')->orderBy('id')->get(['id', 'name', 'color'])->map(fn ($r) => (array) $r)->all();

$out['self_parents'] = DB::table('churches')->whereColumn('parent_id', 'id')->get(['id', 'name', 'type'])->map(fn ($r) => (array) $r)->all();

$out['children_counts'] = DB::table('churches as c')
    ->leftJoin('churches as ch', 'ch.parent_id', '=', 'c.id')
    ->select('c.name', 'c.type', DB::raw('COUNT(ch.id) as enfants'))
    ->groupBy('c.id', 'c.name', 'c.type')
    ->orderBy('c.id')
    ->get()->map(fn ($r) => (array) $r)->all();

$out['users_per_church'] = DB::table('users as u')
    ->join('churches as c', 'c.id', '=', 'u.assigned_church_id')
    ->select('c.name', DB::raw('COUNT(*) as users'))
    ->groupBy('c.name')->orderByDesc('users')
    ->get()->map(fn ($r) => (array) $r)->all();

$out['last_migrations'] = DB::table('migrations')->orderByDesc('id')->limit(8)->get(['migration', 'batch'])->map(fn ($r) => (array) $r)->all();

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
