@extends('rdv.layout')

@section('title', $titre)

@section('content')
<div class="icon">{{ $icone }}</div>
<h1>{{ $titre }}</h1>
<p>{{ $message }}</p>

@if(!empty($details))
<table class="details">
  @foreach($details as $label => $valeur)
  <tr>
    <td>{{ $label }}</td>
    <td>{{ $valeur }}</td>
  </tr>
  @endforeach
</table>
@endif
@endsection
