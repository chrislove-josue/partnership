@extends('errors::layout', ['status' => 500])

@section('title', 'Erreur interne du serveur')
@section('message', 'Une erreur inattendue est survenue. Réessayez dans quelques instants.')
@section('hint', "Si le problème persiste, contactez l'administrateur en précisant l'heure de l'incident.")
