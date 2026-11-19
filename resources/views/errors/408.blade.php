@extends('errors::layout', ['status' => 408])

@section('title', 'Délai dépassé')
@section('message', 'La demande a mis trop de temps à aboutir. Vérifiez votre connexion et réessayez.')
@section('hint', 'Évitez de renvoyer plusieurs fois la même demande.')
