@extends('errors::layout', ['status' => 401])

@section('title', 'Authentification requise')
@section('message', 'Vous devez vous connecter pour accéder à cette page.')
@section('hint', 'Connectez-vous avec votre compte ChurchManager pour continuer.')
