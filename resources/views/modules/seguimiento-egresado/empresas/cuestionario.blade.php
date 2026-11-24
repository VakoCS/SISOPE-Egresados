@php
  $cuestionario = \App\Modules\SeguimientoEgresado\Services\Empresas\CuestionarioEmpresa::class;
@endphp
@if ($esDesempeno)
  <fieldset class="sg-enc-seccion" data-sg-paso tabindex="-1">
    <legend>Desempeño del egresado</legend>
    <p class="sg-help">Valora cada aspecto de 1 (muy bajo) a 5 (muy alto).</p>
    <div class="sg-form-grid">
      @foreach ($cuestionario::DIMENSIONES as $campo => $etiqueta)
        <div class="sg-field">
          <label class="sg-label" for="{{ $campo }}">{{ $etiqueta }}</label>
          <select class="sg-select" id="{{ $campo }}" name="{{ $campo }}" required data-sg-mensaje="Valora: {{ $etiqueta }}." @error($campo) aria-invalid="true" @enderror>
            <option value="">Selecciona una valoración</option>
            @foreach ($cuestionario::ESCALA as $valor => $nombre)
              <option value="{{ $valor }}" @selected((string) old($campo) === (string) $valor)>{{ $valor }} — {{ $nombre }}</option>
            @endforeach
          </select>
          @error($campo)<p class="sg-field-error">{{ $message }}</p>@enderror
        </div>
      @endforeach
    </div>
  </fieldset>
@else
  @foreach ([
    ['sectores_contratacion', '¿En qué sectores contrata profesionales?', $sectores->pluck('nombre', 'codigo')->all(), 10],
    ['perfiles_requeridos', '¿Qué perfiles requiere?', $cuestionario::PERFILES, 7],
  ] as [$campo, $pregunta, $opciones, $maximoGrupo])
    <fieldset class="sg-enc-seccion" data-sg-paso data-sg-grupo="{{ $campo }}" data-sg-maximo="{{ $maximoGrupo }}" data-sg-mensaje="Selecciona al menos una opción." tabindex="-1">
      <legend>{{ $pregunta }}</legend>
      <div class="sg-enc-opciones">
        @foreach ($opciones as $codigo => $nombre)
          <label class="sg-enc-opcion">
            <input type="checkbox" name="{{ $campo }}[]" value="{{ $codigo }}" @checked(in_array($codigo, (array) old($campo, []), true))>
            <span>{{ $nombre }}</span>
          </label>
        @endforeach
      </div>
      @error($campo)<p class="sg-field-error">{{ $message }}</p>@enderror
      @error($campo.'.*')<p class="sg-field-error">{{ $message }}</p>@enderror
    </fieldset>
  @endforeach
  <fieldset class="sg-enc-seccion" data-sg-paso tabindex="-1">
    <legend>Demanda y formación profesional</legend>
    <div class="sg-form-grid">
      <div class="sg-field">
        <label class="sg-label" for="demanda_profesionales">¿Cuántos profesionales prevé contratar en los próximos 12 meses?</label>
        <select class="sg-select" id="demanda_profesionales" name="demanda_profesionales" required data-sg-mensaje="Selecciona la demanda prevista.">
          <option value="">Selecciona una cantidad</option>
          @foreach ($cuestionario::DEMANDA as $codigo => $nombre)
            <option value="{{ $codigo }}" @selected(old('demanda_profesionales') === $codigo)>{{ $nombre }}</option>
          @endforeach
        </select>
        @error('demanda_profesionales')<p class="sg-field-error">{{ $message }}</p>@enderror
      </div>
      <div class="sg-field">
        <label class="sg-label" for="satisfaccion_formacion">Satisfacción general con la formación profesional</label>
        <p class="sg-help">Opinión general; no evalúa a un egresado específico.</p>
        <select class="sg-select" id="satisfaccion_formacion" name="satisfaccion_formacion" required data-sg-mensaje="Selecciona tu satisfacción general.">
          <option value="">Selecciona un nivel</option>
          @foreach ($niveles as $nivel)
            <option value="{{ $nivel->codigo }}" @selected(old('satisfaccion_formacion') === $nivel->codigo)>{{ $nivel->nombre }}</option>
          @endforeach
        </select>
        @error('satisfaccion_formacion')<p class="sg-field-error">{{ $message }}</p>@enderror
      </div>
    </div>
  </fieldset>
@endif
