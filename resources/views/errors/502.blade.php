@extends('errors::layout', ['status' => 502])

@section('title', 'Passerelle indisponible')
@section('message', 'Le serveur a reçu une réponse invalide. Réessayez dans quelques instants.')
@section('hint', "Si le problème persiste, contactez l'administrateur.")
