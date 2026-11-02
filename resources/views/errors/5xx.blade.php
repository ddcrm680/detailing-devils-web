@extends('errors.layout')

@section('code', (string) $exception->getStatusCode())
@section('title', 'Service Interrupted')
@section('description', 'We’re unable to complete this request right now. Please try again later. If you submitted a form, confirm its outcome before trying again.')
