@extends('modules.seguimiento-egresado.layout')
@section('title', $empresa->razon_social)
@section('section_name', 'Detalle de la empresa')

@push('styles')
<style>
  .sg-emp-ficha { display: grid; gap: 16px 26px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin: 0; }
  .sg-emp-ficha div { min-width: 0; }
  .sg-emp-ficha dt { color: var(--sg-muted, #536078); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 3px; }
  .sg-emp-ficha dd { margin: 0; font-weight: 600; overflow-wrap: anywhere; }
  .sg-emp-det-cab { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 10px; }
  .sg-emp-det-cab h2 { margin: 0; }
  .sg-emp-vincular { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
  .sg-emp-vincular .sg-field { min-width: 280px; flex: 1; max-width: 560px; }
  .sg-tabla-vinculados th, .sg-tabla-vinculados td { padding-left: 10px; padding-right: 10px; vertical-align: middle; }
  .sg-tabla-vinculados td:not(:nth-child(4)) { white-space: nowrap; }
  .sg-emp-volver { display: inline-flex; margin-bottom: 4px; }
</style>
@endpush

@section('content')
<nav class="sg-emp-volver" aria-label="Ruta de navegación">
  <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.empresa') }}">← Volver al listado</a>
</nav>

<section class="sg-heading" aria-labelledby="sg-empresa-detalle">
  <div class="sg-kicker">GESTIÓN DE EMPLEADORES</div>
  <h1 id="sg-empresa-detalle">{{ $empresa->razon_social }}</h1>
  <p>
    RUC {{ $empresa->ruc }} · {{ $empresa->sector_nombre ?? 'Sin sector asignado' }}
    <span class="sg-badge {{ $empresa->estado === 'Activo' ? 'sg-badge-success' : 'sg-badge-warning' }}">{{ $empresa->estado }}</span>
  </p>
  <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
    @if ($puedeGestionar)
      <a class="sg-button" href="#editar" onclick="abrirEdicion(); return false;">Editar datos</a>
      <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.empresas') }}">Encuesta empresarial</a>
    @else
      <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.empresas') }}">Encuesta empresarial</a>
    @endif
  </div>
</section>

<section class="sg-panel sg-bloque" aria-labelledby="sg-empresa-datos">
  <h2 class="sg-section-title" id="sg-empresa-datos">Datos de la empresa</h2>
  <dl class="sg-emp-ficha">
    <div><dt>RUC</dt><dd>{{ $empresa->ruc }}</dd></div>
    <div><dt>Razón social</dt><dd>{{ $empresa->razon_social }}</dd></div>
    <div><dt>Nombre comercial</dt><dd>{{ $empresa->nombre_comercial ?? '—' }}</dd></div>
    <div><dt>Sector económico</dt><dd>{{ $empresa->sector_nombre ?? '—' }}</dd></div>
    <div><dt>Dirección</dt><dd>{{ $empresa->direccion ?? '—' }}</dd></div>
    <div><dt>Teléfono</dt><dd>{{ $empresa->telefono ?? '—' }}</dd></div>
    <div><dt>Correo de contacto</dt><dd>{{ $empresa->correo ?? '—' }}</dd></div>
    <div><dt>Página web</dt><dd>{{ $empresa->pagina_web ?? '—' }}</dd></div>
    <div><dt>Estado</dt><dd>{{ $empresa->estado }}</dd></div>
  </dl>
</section>

<section class="sg-panel sg-bloque" aria-labelledby="sg-empresa-vinculados">
  <div class="sg-emp-det-cab">
    <h2 class="sg-section-title" id="sg-empresa-vinculados" style="margin: 0;">Egresados vinculados</h2>
    <p class="sg-help" style="margin: 0;">{{ $vinculados->count() }} {{ $vinculados->count() === 1 ? 'egresado vinculado' : 'egresados vinculados' }}</p>
  </div>
  <p class="sg-help" style="margin-bottom: 10px;">Egresados que trabajan o colaboran en esta empresa. El vínculo se guarda con el documento del egresado, así que se conserva aunque cambie su correo.</p>

  @if ($vinculados->isEmpty())
    <p class="sg-help">Todavía no hay ningún egresado vinculado a esta empresa.</p>
  @else
    <div class="sg-table-wrap">
      <table class="sg-table sg-tabla-vinculados">
        <caption class="sg-sr-only">Egresados vinculados a {{ $empresa->razon_social }}</caption>
        <thead>
          <tr>
            <th scope="col">Egresado</th>
            <th scope="col">Documento</th>
            <th scope="col">Promoción</th>
            <th scope="col">Correo institucional</th>
            @if ($puedeGestionar)<th scope="col"><span class="sg-sr-only">Acción</span></th>@endif
          </tr>
        </thead>
        <tbody>
          @foreach ($vinculados as $egresado)
            <tr>
              <td><strong>{{ trim($egresado->apellido_paterno.' '.$egresado->apellido_materno).', '.$egresado->nombres }}</strong>
                <span class="sg-emp-sub" style="display: block; color: var(--sg-muted); font-size: 12px;">{{ $egresado->programa }}</span>
              </td>
              <td>{{ $egresado->tipo_documento }} {{ $egresado->numero_documento }}</td>
              <td>{{ $egresado->anio_ingreso ?? '—' }}</td>
              <td>{{ $egresado->correo_institucional ?? '—' }}</td>
              @if ($puedeGestionar)
                <td>
                  <form method="POST" action="{{ route('seguimiento-egresado.empresa.desvincular', [$empresa->id_empresa, $egresado->id_egresado]) }}"
                        data-sg-confirmar="Se quitará a este egresado de la empresa. ¿Continuar?">
                    @csrf
                    <button type="submit" class="sg-button sg-button-secondary">Desvincular</button>
                  </form>
                </td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>

@if ($puedeGestionar)
  <section class="sg-panel sg-bloque" aria-labelledby="sg-empresa-asociar" id="editar">
    <h2 class="sg-section-title" id="sg-empresa-asociar">Asociar un egresado</h2>
    <p class="sg-help" style="margin-bottom: 10px;">Escribe el nombre o el documento para filtrar la lista; solo aparecen los egresados que todavía no están vinculados.</p>

    @if ($disponibles->isEmpty())
      <p class="sg-help">Ya están vinculados todos los egresados del padrón.</p>
    @else
      <form method="POST" action="{{ route('seguimiento-egresado.empresa.vincular', $empresa->id_empresa) }}" class="sg-emp-vincular">
        @csrf
        <div class="sg-field">
          <label class="sg-label" for="filtro-egresado">Filtrar egresados</label>
          <input class="sg-input" id="filtro-egresado" type="search" placeholder="Apellido, nombre o documento" autocomplete="off">
        </div>
        <div class="sg-field">
          <label class="sg-label" for="id_egresado">Egresado a vincular *</label>
          <select class="sg-select" id="id_egresado" name="id_egresado" required size="1">
            <option value="">Elige un egresado…</option>
            @foreach ($disponibles as $disponible)
              <option value="{{ $disponible->id }}">{{ trim($disponible->apellido_paterno.' '.$disponible->apellido_materno).', '.$disponible->nombres }} · {{ $disponible->numero_documento }}{{ $disponible->anio_ingreso ? ' · Promoción '.$disponible->anio_ingreso : '' }}</option>
            @endforeach
          </select>
          @error('id_egresado')<p class="sg-error-formulario" role="alert" style="color: var(--sg-danger, #a11d12); font-size: 13px; margin: 4px 0 0;">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="sg-button">Vincular egresado</button>
      </form>
      <p class="sg-help" style="margin-top: 8px;" id="cuenta-filtro">{{ $disponibles->count() }} egresados disponibles.</p>
    @endif
  </section>
@endif

@include('modules.seguimiento-egresado.partials.empresa-formulario', [
  'empresa' => $empresa,
  'sectores' => $sectores,
  'estados' => $estados,
  'accion' => route('seguimiento-egresado.empresa.actualizar', $empresa->id_empresa),
  'metodo' => 'PUT',
  'idDialogo' => 'dlg-empresa',
])
@endsection

@push('scripts')
<script>
  (() => {
    // El listado llega con #editar cuando se pide editar la empresa desde ahí.
    window.abrirEdicion = () => {
      const dialogo = document.getElementById('dlg-empresa');
      if (dialogo && typeof dialogo.showModal === 'function') dialogo.showModal();
    };
    if (location.hash === '#editar') window.abrirEdicion();

    const filtro = document.getElementById('filtro-egresado');
    const select = document.getElementById('id_egresado');
    if (!filtro || !select) return;
    const opciones = [...select.options].filter((o) => o.value);
    filtro.addEventListener('input', () => {
      const texto = filtro.value.trim().toLowerCase();
      let visibles = 0;
      opciones.forEach((opcion) => {
        const coincide = texto === '' || opcion.textContent.toLowerCase().includes(texto);
        opcion.hidden = !coincide;
        if (coincide) visibles++;
      });
      const cuenta = document.getElementById('cuenta-filtro');
      if (cuenta) cuenta.textContent = visibles + (visibles === 1 ? ' egresado disponible.' : ' egresados disponibles.');
      if (opciones.some((o) => o.value === select.value) && select.selectedOptions[0]?.hidden) select.value = '';
      if (!select.value && visibles > 0 && texto !== '') {
        const primera = opciones.find((o) => !o.hidden);
        if (primera) select.value = primera.value;
      }
    });
  })();
</script>
@endpush
