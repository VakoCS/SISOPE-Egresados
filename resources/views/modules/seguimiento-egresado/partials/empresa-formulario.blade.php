{{--
  Formulario de una empresa (alta o edición) en ventana, con el mismo diseño que el resto de formularios del CRM.
  Variables: $empresa (null al dar de alta), $sectores, $estados, $accion (URL), $metodo (POST|PUT), $idDialogo.
  Los errores de validación se muestran junto al campo y abren la ventana.
--}}
@php
  $campos = ['ruc', 'razon_social', 'nombre_comercial', 'id_sector', 'direccion', 'telefono', 'correo', 'pagina_web', 'estado'];
  $abrirConErrores = collect($campos)->contains(fn (string $campo) => $errors->has($campo));
  $valor = fn (string $campo, mixed $porDefecto = null) => old($campo, $empresa->$campo ?? $porDefecto);
  $idDialogo = $idDialogo ?? 'dlg-empresa';
@endphp
@push('styles')
<style>
  .{{ $idDialogo }} { text-align: left; }
  .{{ $idDialogo }} .sg-dialogo-cuerpo { text-align: left; padding: 24px 24px 8px; }
  .{{ $idDialogo }} .sg-dialogo-titulo { font-size: 1.15rem; }
  .{{ $idDialogo }} .sg-dialogo-mensaje { white-space: normal; }
  .{{ $idDialogo }} form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
  .{{ $idDialogo }} .sg-campos { display: grid; gap: 14px; margin-top: 14px; }
  .{{ $idDialogo }} .sg-campos-duo { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
  .{{ $idDialogo }} .sg-dialogo-pie { gap: 10px; padding: 8px 24px 22px; justify-content: flex-end; }
  .{{ $idDialogo }} .sg-error-formulario { color: var(--sg-danger, #a11d12); font-size: 13px; margin: 4px 0 0; }
  .{{ $idDialogo }} .sg-campos .sg-error-formulario { margin-top: -8px; }
</style>
@endpush

<dialog class="sg-dialogo sg-form-dialogo {{ $idDialogo }}" id="{{ $idDialogo }}" style="width: min(94vw, 560px);"
        aria-labelledby="{{ $idDialogo }}-titulo" @if($abrirConErrores) open @endif>
  <form method="POST" action="{{ $accion }}">
    @csrf
    @if ($metodo === 'PUT') @method('PUT') @endif
    <div class="sg-dialogo-cuerpo">
      <h2 class="sg-dialogo-titulo" id="{{ $idDialogo }}-titulo">{{ $empresa ? 'Editar la empresa' : 'Nueva empresa' }}</h2>
      <p class="sg-dialogo-mensaje">
        {{ $empresa ? 'Actualiza los datos de la empresa. El RUC no se puede repetir con otra empresa del catálogo.' : 'Registra a la empresa empleadora: el RUC y la razón social son obligatorios y no pueden repetirse.' }}
      </p>

      <div class="sg-campos">
        <div class="sg-campos-duo">
          <div class="sg-field">
            <label class="sg-label" for="{{ $idDialogo }}-ruc">RUC *</label>
            <input class="sg-input" id="{{ $idDialogo }}-ruc" name="ruc" type="text" inputmode="numeric"
                   maxlength="11" placeholder="20500000000" autocomplete="off"
                   value="{{ $valor('ruc') }}" @if(!$empresa) required @endif>
            @error('ruc')<p class="sg-error-formulario" role="alert">{{ $message }}</p>@enderror
          </div>
          <div class="sg-field">
            <label class="sg-label" for="{{ $idDialogo }}-estado">Estado *</label>
            <select class="sg-select" id="{{ $idDialogo }}-estado" name="estado" required>
              @foreach ($estados as $estado)
                <option value="{{ $estado }}" @selected($valor('estado', 'Activo') === $estado)>{{ $estado }}</option>
              @endforeach
            </select>
            @error('estado')<p class="sg-error-formulario" role="alert">{{ $message }}</p>@enderror
          </div>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="{{ $idDialogo }}-razon">Razón social *</label>
          <input class="sg-input" id="{{ $idDialogo }}-razon" name="razon_social" type="text" maxlength="180"
                 placeholder="Nombre legal de la empresa" autocomplete="off" value="{{ $valor('razon_social') }}" required>
          @error('razon_social')<p class="sg-error-formulario" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="sg-field">
          <label class="sg-label" for="{{ $idDialogo }}-comercial">Nombre comercial</label>
          <input class="sg-input" id="{{ $idDialogo }}-comercial" name="nombre_comercial" type="text" maxlength="180"
                 placeholder="Cómo se conoce a la empresa (opcional)" autocomplete="off" value="{{ $valor('nombre_comercial') }}">
          @error('nombre_comercial')<p class="sg-error-formulario" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="sg-field">
          <label class="sg-label" for="{{ $idDialogo }}-sector">Sector económico</label>
          <select class="sg-select" id="{{ $idDialogo }}-sector" name="id_sector">
            <option value="">Sin sector asignado</option>
            @foreach ($sectores as $sector)
              <option value="{{ $sector->id_sector }}" @selected((string) $valor('id_sector', '') === (string) $sector->id_sector)>{{ $sector->nombre }}</option>
            @endforeach
          </select>
          @error('id_sector')<p class="sg-error-formulario" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="sg-field">
          <label class="sg-label" for="{{ $idDialogo }}-direccion">Dirección</label>
          <input class="sg-input" id="{{ $idDialogo }}-direccion" name="direccion" type="text" maxlength="500"
                 placeholder="Av. Universidad s/n, Trujillo" autocomplete="off" value="{{ $valor('direccion') }}">
          @error('direccion')<p class="sg-error-formulario" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="sg-campos-duo">
          <div class="sg-field">
            <label class="sg-label" for="{{ $idDialogo }}-telefono">Teléfono</label>
            <input class="sg-input" id="{{ $idDialogo }}-telefono" name="telefono" type="text" maxlength="30"
                   placeholder="044 000000" autocomplete="off" value="{{ $valor('telefono') }}">
            @error('telefono')<p class="sg-error-formulario" role="alert">{{ $message }}</p>@enderror
          </div>
          <div class="sg-field">
            <label class="sg-label" for="{{ $idDialogo }}-correo">Correo de contacto</label>
            <input class="sg-input" id="{{ $idDialogo }}-correo" name="correo" type="email" maxlength="180"
                   placeholder="contacto@empresa.com" autocomplete="off" value="{{ $valor('correo') }}">
            @error('correo')<p class="sg-error-formulario" role="alert">{{ $message }}</p>@enderror
          </div>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="{{ $idDialogo }}-web">Página web</label>
          <input class="sg-input" id="{{ $idDialogo }}-web" name="pagina_web" type="text" maxlength="190"
                 placeholder="https://www.empresa.com" autocomplete="off" value="{{ $valor('pagina_web') }}">
          @error('pagina_web')<p class="sg-error-formulario" role="alert">{{ $message }}</p>@enderror
        </div>
      </div>
    </div>
    <div class="sg-dialogo-pie">
      <button type="button" class="sg-dialogo-secundario" onclick="document.getElementById('{{ $idDialogo }}').close()">Cancelar</button>
      <button type="submit">Guardar</button>
    </div>
  </form>
</dialog>
