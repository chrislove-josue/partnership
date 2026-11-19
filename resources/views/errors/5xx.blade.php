@extends('errors::layout', ['status' => 500])

@section('title', 'Erreur du serveur')
@section('message', 'Une erreur est survenue au niveau du serveur. Réessayez dans quelques instants.')
@section('hint', "Si le problème persiste, contactez l'administrateur en précisant l'heure de l'incident.")
