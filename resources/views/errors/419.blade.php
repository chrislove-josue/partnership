@extends('errors::layout', ['status' => 419])

@section('title', 'Session expirée')
@section('message', 'Votre session a expiré pour des raisons de sécurité. Rechargez la page et reconnectez-vous.')
@section('hint', 'Vos données enregistrées avant l’expiration ont été conservées.')
