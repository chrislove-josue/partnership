@extends('errors::layout', ['status' => 503])

@section('title', 'Service temporairement indisponible')
@section('message', 'Le service est temporairement indisponible. Réessayez dans quelques instants.')
@section('hint', 'Une maintenance est peut-être en cours. Merci de patienter avant de réessayer.')
