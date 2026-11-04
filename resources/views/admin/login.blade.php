@extends('admin.layouts.app')

@section('title', 'Secure sign in')
@section('context', 'Administration')

@section('content')
    <section class="login-panel panel">
        <span class="eyebrow">DETAILING DEVILS / ADMIN</span>
        <h1>Welcome back.</h1>
        <p class="muted">Sign in to manage your website&#8217;s search and sharing presentation.</p>
        @isset($loginError)
            <div class="notice error" role="alert">{{ $loginError }}</div>
        @endisset
        <form action="{{ route('admin.login.store') }}" method="post" class="form-stack">
            @csrf
            <div class="field">
                <label for="email">Administrator email</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="username"
                    required
                    maxlength="254"
                    value="{{ old('email') }}"
                >
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    maxlength="4096"
                >
            </div>
            <button class="button" type="submit">Sign in securely <span aria-hidden="true">&rarr;</span></button>
        </form>
        <p class="small muted login-note">Access is provided by the website owner. No public registration.</p>
    </section>
@endsection
