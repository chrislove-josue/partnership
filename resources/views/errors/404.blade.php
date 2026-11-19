@extends('errors::layout', ['status' => 404])

@section('title', 'Page introuvable')
@section('message', "La page recherchée n'existe pas ou a été déplacée.")
@section('hint', "Vérifiez l'adresse dans la barre du navigateur ou utilisez le bouton ci-dessous.")
