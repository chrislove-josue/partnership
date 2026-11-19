@extends('errors::layout', ['status' => 418])

@section('title', 'Requête invalide')
@section('message', "La requête n'a pas pu être traitée. Vérifiez vos informations et réessayez.")
@section('hint', "Si le problème persiste, contactez l'administrateur.")
