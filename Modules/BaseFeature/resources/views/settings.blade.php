@extends('basefeature::layouts.master')

@section('content')
    <h1>Pengaturan Web</h1>

    @if (session('status'))
        <p>{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('tenant.settings.update') }}">
        @csrf

        <div>
            <label for="brand_name">Nama Brand</label>
            <input id="brand_name" type="text" name="brand_name" value="{{ old('brand_name', $setting->brand_name) }}">
        </div>

        <div>
            <label for="description">Deskripsi</label>
            <textarea id="description" name="description" rows="4">{{ old('description', $setting->description) }}</textarea>
        </div>

        <div>
            <label for="theme_color">Warna Tema</label>
            <input id="theme_color" type="color" name="theme_color" value="{{ old('theme_color', $setting->theme_color) }}">
        </div>

        <button type="submit">Simpan</button>
    </form>
@endsection
