@extends('errors::layout', ['status' => 405])

@section('title', 'Action non autorisée')
@section('message', "Cette action n'est pas disponible pour cette page.")
@section('hint', 'Rechargez la page puis réessayez. Si le problème persiste, contactez l’administrateur.')
