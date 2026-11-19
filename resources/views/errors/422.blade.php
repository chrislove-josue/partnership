@extends('errors::layout', ['status' => 422])

@section('title', 'Données invalides')
@section('message', "Certaines informations envoyées ne sont pas valides. Corrigez-les et réessayez.")
@section('hint', 'Vérifiez particulièrement les champs obligatoires, les dates et les montants.')
