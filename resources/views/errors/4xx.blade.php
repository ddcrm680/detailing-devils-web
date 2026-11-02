@extends('errors.layout')

@section('code', (string) $exception->getStatusCode())
@section('title', 'Request Could Not Be Completed')
@section('description', 'We couldn’t open this page with the current request. Please check the link or return home to continue.')
