@extends('layouts.guest')
@section('title', 'Masuk - little survey')
@section('content')

<div class="mx-auto w-full max-w-md">
    <a href="{{ route('beranda') }}" class="mb-8 flex min-h-11 w-fit items-center gap-3 rounded-2xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-accent font-extrabold text-white" aria-hidden="true">LS</span>
        <span class="font-bold">little survey</span>
    </a>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
        <h1 class="text-2xl font-extrabold tracking-tight">Masuk</h1>
        <p class="mt-1 text-slate-600">Masuk untuk mengelola survei Anda.</p>

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 flex flex-col gap-5" novalidate>
            @csrf

            <div>
                <label for="email" class="block text-sm font-semibold">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                       class="mt-1.5 min-h-11 w-full rounded-xl border border-slate-300 px-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent @error('email') border-red-600 @enderror">
                @error('email')
                    <p id="email-error" class="mt-1.5 text-sm font-semibold text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold">Kata sandi</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                       class="mt-1.5 min-h-11 w-full rounded-xl border border-slate-300 px-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent @error('password') border-red-600 @enderror">
                @error('password')
                    <p id="password-error" class="mt-1.5 text-sm font-semibold text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex min-h-11 items-center gap-3 text-sm">
                <input type="checkbox" name="remember" value="1" class="h-5 w-5 rounded border-slate-300 accent-accent">
                Ingat saya
            </label>

            <button type="submit" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-accent px-6 font-semibold text-white hover:bg-accent/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">Masuk</button>
        </form>
    </div>
</div>
@endsection
