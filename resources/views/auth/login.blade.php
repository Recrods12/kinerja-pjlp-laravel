@extends('layouts.app')

@section('content')
  <section class="auth-panel">
    <div class="brand-lockup">
      <span class="brand-mark">K</span>
      <div>
        <p class="eyebrow">Dinas Tenaga Kerja, Transmigrasi dan Energi</p>
        <h1>Kinerja Harian PJLP</h1>
      </div>
    </div>

    <form class="form-stack" method="post" action="{{ route('login.store') }}">
      @csrf
      <label>
        <span>Username</span>
        <input name="username" value="{{ old('username') }}" autocomplete="username" required autofocus>
      </label>
      <label>
        <span>Password</span>
        <input name="password" type="password" autocomplete="current-password" required>
      </label>
      <label class="checkline">
        <input name="remember" type="checkbox" value="1">
        <span>Ingat saya</span>
      </label>
      @error('username')
        <p class="error-text">{{ $message }}</p>
      @enderror
      <button class="primary-action" type="submit">Masuk</button>
    </form>
  </section>
@endsection
