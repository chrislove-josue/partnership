@extends('errors::layout', ['status' => 400])

@section('title', 'Requête invalide')
@section('message', "La requête envoyée n'a pas pu être comprise. Vérifiez les informations saisies et réessayez.")
@section('hint', "Si vous avez utilisé un lien, vérifiez qu'il est complet et essayez à nouveau.")
