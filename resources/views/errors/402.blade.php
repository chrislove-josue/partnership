@extends('errors::layout', ['status' => 402])

@section('title', 'Paiement requis')
@section('message', "Cette ressource nécessite un paiement. Contactez l'administrateur pour y accéder.")
@section('hint', "Aucun paiement n'est demandé sur cette page. Signalez cette erreur à l'administrateur.")
