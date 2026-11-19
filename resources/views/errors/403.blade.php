@extends('errors::layout', ['status' => 403])

@section('title', 'Accès refusé')
@section('message', "Vous n'avez pas les autorisations nécessaires pour accéder à cette page.")
@section('hint', "Si vous pensez que c'est une erreur, contactez l'administrateur pour obtenir les droits requis.")
