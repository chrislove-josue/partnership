@extends('errors::layout', ['status' => 429])

@section('title', 'Trop de demandes')
@section('message', 'Vous avez effectué trop de demandes. Patientez quelques instants avant de réessayer.')
@section('hint', 'Cette limitation protège votre compte. Réessayez dans une minute.')
