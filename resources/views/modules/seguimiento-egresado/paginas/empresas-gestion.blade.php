@extends('modules.seguimiento-egresado.layout')
@section('title', 'Gestión de empleadores')
@section('section_name', 'Gestión de empleadores')

@push('styles')
<style>
  .sg-filtros-empresas { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
  .sg-filtros-empresas .sg-field { min-width: 200px; }
  .sg-tabla-empresas th, .sg-tabla-empresas td { padding-left: 10px; padding-right: 10px; vertical-align: middle; }
  .sg-tabla-empresas td:not(:nth-child(2)) { white-space: nowrap; }
  .sg-emp-lista-cab { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 8px; }
  .sg-emp-lista-cab h2 { margin: 0; }
  .sg-emp-acciones { display: flex; gap: 6px; flex-wrap: wrap; }
  .sg-emp-sub { display: block; color: var(--sg-muted, #536078); font-size: 12px; }
</style>
@endpush

@section('content')
<section class="sg-heading" aria-labelledby="sg-empresas-title">
  <div class="sg-kicker">GESTIÓN DE EMPLEADORES</div>
  <h1 id="sg-empresas-title">Empresas empleadoras</h1>
  <p>El catálogo de empresas con las que la Escuela se relaciona: registra sus datos, búscalos por RUC o razón social y <strong>vincula a los egresados que trabajan en cada una</strong>.</p>
  <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
    @if ($puedeGestionar)
      <button type="button" class="sg-button" onclick="document.getElementById('dlg-empresa').showModal()">+ Nueva empresa</button>
    @endif
    <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.empresas') }}">Encuesta empresarial</a>
  </div>
</section>

<section class="sg-panel sg-bloque" aria-labelledby="sg-emp-buscar">
  <h2 class="sg-section-title" id="sg-emp-buscar">Buscar y filtrar</h2>
  <form method="GET" action="{{ route('seguimiento-egresado.empresa') }}" class="sg-filtros-empresas">
    <div class="sg-field">
      <label class="sg-label" for="q">Texto de búsqueda</label>
      <input class="sg-input" id="q" name="q" type="search" value="{{ $filtros['busqueda'] }}" placeholder="RUC, razón social o nombre comercial">
    </div>
    <div class="sg-field">
      <label class="sg-label" for="sector">Sector económico</label>
      <select class="sg-select" id="sector" name="sector">
        <option value="">Todos los sectores</option>
        @foreach ($sectores as $sector)
          <option value="{{ $sector->id_sector }}" @selected($filtros['sector'] === (int) $sector->id_sector)>{{ $sector->nombre }}</option>
        @endforeach
      </select>
    </div>
    <div class="sg-field">
      <label class="sg-label" for="estado">Estado</label>
      <select class="sg-select" id="estado" name="estado">
        <option value="">Todos</option>
        @foreach ($estados as $opcion)
          <option value="{{ $opcion }}" @selected($filtros['estado'] === $opcion)>{{ $opcion }}</option>
        @endforeach
      </select>
    </div>
    <button class="sg-button" type="submit">Buscar</button>
    @if ($filtros['busqueda'] !== '' || $filtros['estado'] !== null || $filtros['sector'] !== null)
      <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.empresa') }}">Limpiar filtros</a>
    @endif
  </form>
</section>

<section class="sg-panel sg-bloque" aria-labelledby="sg-emp-lista">
  <div class="sg-emp-lista-cab">
    <h2 class="sg-section-title" id="sg-emp-lista">Empresas registradas</h2>
    <p class="sg-help" style="margin: 0;">{{ $empresas->count() }} {{ $empresas->count() === 1 ? 'empresa encontrada' : 'empresas encontradas' }}</p>
  </div>

  @if ($empresas->isEmpty())
    <p class="sg-help">No hay empresas con esos criterios.
      @if ($puedeGestionar) Registra la primera con el botón «+ Nueva empresa». @endif
    </p>
  @else
    <div class="sg-table-wrap">
      <table class="sg-table sg-tabla-empresas">
        <caption class="sg-sr-only">Empresas del catálogo con sus datos y egresados vinculados</caption>
        <thead>
          <tr>
            <th scope="col">RUC</th>
            <th scope="col">Razón social</th>
            <th scope="col">Sector</th>
            <th scope="col">Estado</th>
            <th scope="col" class="sg-numeric">Egresados vinculados</th>
            <th scope="col"><span class="sg-sr-only">Acción</span></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($empresas as $empresa)
            <tr>
              <td>{{ $empresa->ruc }}</td>
              <td>
                <a class="sg-link" href="{{ route('seguimiento-egresado.empresa.detalle', $empresa->id_empresa) }}"><strong>{{ $empresa->razon_social }}</strong></a>
                @if ($empresa->nombre_comercial)<span class="sg-emp-sub">{{ $empresa->nombre_comercial }}</span>@endif
              </td>
              <td>{{ $empresa->sector_nombre ?? '—' }}</td>
              <td>
                <span class="sg-badge {{ $empresa->estado === 'Activo' ? 'sg-badge-success' : 'sg-badge-warning' }}">{{ $empresa->estado }}</span>
              </td>
              <td class="sg-numeric">{{ $empresa->vinculados }}</td>
              <td>
                <div class="sg-emp-acciones">
                  <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.empresa.detalle', $empresa->id_empresa) }}">Ver</a>
                  @if ($puedeGestionar)
                    <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.empresa.detalle', $empresa->id_empresa) }}#editar">Editar</a>
                  @endif
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>

@include('modules.seguimiento-egresado.partials.empresa-formulario', [
  'empresa' => null,
  'sectores' => $sectores,
  'estados' => $estados,
  'accion' => route('seguimiento-egresado.empresa.guardar'),
  'metodo' => 'POST',
  'idDialogo' => 'dlg-empresa',
])
@endsection
