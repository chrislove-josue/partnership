@extends('errors::layout', ['status' => 504])

@section('title', 'Délai de la passerelle dépassé')
@section('message', 'Le serveur na pas répondu à temps. Réessayez dans quelques instants.')
@section('hint', "Si le problème persiste, contactez l'administrateur.")
