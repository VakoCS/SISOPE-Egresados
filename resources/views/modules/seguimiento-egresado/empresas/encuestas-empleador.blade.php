<section class="sg-panel sg-bloque" aria-labelledby="encuestas-empleador">
  <h2 class="sg-section-title" id="encuestas-empleador">Encuestas de esta empresa</h2>
  <p class="sg-help">Los enlaces se muestran una sola vez en la pantalla de encuesta empresarial. Cópialos y entrégalos al contacto de la empresa. Evaluar a un egresado requiere su autorización de contacto.</p>
  @if ($puedeGestionar && $empresa->estado === 'Activo')
    <form method="POST" action="{{ route('seguimiento-egresado.empresas.enlaces') }}" class="sg-emp-vincular">
      @csrf
      <input type="hidden" name="id_empresa" value="{{ $empresa->id_empresa }}">
      <div class="sg-field">
        <label class="sg-label" for="encuesta-campania">Campaña</label>
        <select class="sg-select" name="id_campania" id="encuesta-campania" required>
          <option value="">Selecciona una campaña abierta</option>
          @foreach ($campaniasEmpresa->where('estado', 'ABIERTA') as $c)
            <option value="{{ $c->id }}">{{ $c->nombre }}</option>
          @endforeach
        </select>
      </div>
      <div class="sg-field">
        <label class="sg-label" for="encuesta-contexto">Tipo de encuesta</label>
        <select class="sg-select" name="contexto" id="encuesta-contexto">
          <option value="MERCADO">A — Mercado laboral (sin egresado)</option>
          <option value="DESEMPENO">B — Desempeño de un egresado</option>
        </select>
      </div>
      <div class="sg-field" id="encuesta-egresado-campo">
        <label class="sg-label" for="encuesta-egresado">Egresado vinculado (solo tipo B)</label>
        <select class="sg-select" name="id_egresado" id="encuesta-egresado">
          <option value="">Selecciona un egresado</option>
          @foreach ($vinculados as $e)
            <option value="{{ $e->id_egresado }}">{{ $e->apellido_paterno }}, {{ $e->nombres }}</option>
          @endforeach
        </select>
      </div>
      <button class="sg-button" type="submit">Generar enlace personalizado</button>
    </form>
    <script>
      (() => {
        const tipo = document.getElementById('encuesta-contexto');
        const egresado = document.getElementById('encuesta-egresado');
        const actualizar = () => {
          const requiere = tipo.value === 'DESEMPENO';
          egresado.required = requiere;
          egresado.disabled = !requiere;
          document.getElementById('encuesta-egresado-campo').hidden = !requiere;
        };
        tipo.addEventListener('change', actualizar);
        actualizar();
      })();
    </script>
  @endif

  @forelse ($invitacionesEmpresa as $i)
    <article class="sg-bloque">
      <h3>{{ $i->campania }} — {{ $i->contexto === 'MERCADO' ? 'Mercado laboral' : 'Desempeño: '.$i->apellido_paterno.', '.$i->nombres }}</h3>
      <p class="sg-help">{{ $i->estado === 'PENDIENTE' && \Carbon\Carbon::parse($i->expira_en)->isPast() ? 'EXPIRADA' : $i->estado }} · Vence: {{ \Carbon\Carbon::parse($i->expira_en)->format('d/m/Y') }}
        @if ($i->respondido_en) · Respondida: {{ \Carbon\Carbon::parse($i->respondido_en)->format('d/m/Y H:i') }} @endif
      </p>
      @if ($i->id_respuesta)
        <p><button type="button" class="sg-button sg-button-secondary" data-respuesta="{{ route('seguimiento-egresado.empresas.respuesta', $i->id) }}">Ver respuesta y habilidades</button></p>
        <dl class="sg-emp-ficha">
          @foreach (\App\Modules\SeguimientoEgresado\Services\Empresas\CuestionarioEmpresa::DIMENSIONES as $campo => $etiqueta)
            @if ($i->$campo !== null)<div><dt>{{ $etiqueta }}</dt><dd>{{ $i->$campo }} / 5</dd></div>@endif
          @endforeach
          @if ($i->demanda_profesionales)
            <div><dt>Contratación prevista (12 meses)</dt><dd>{{ \App\Modules\SeguimientoEgresado\Services\Empresas\CuestionarioEmpresa::DEMANDA[$i->demanda_profesionales] }}</dd></div>
            <div><dt>Satisfacción general con la formación</dt><dd>{{ $i->satisfaccion_formacion }}</dd></div>
            <div><dt>Sectores de contratación</dt><dd>{{ $i->sectores_contratacion }}</dd></div>
            <div><dt>Perfiles requeridos</dt><dd>{{ $i->perfiles_requeridos }}</dd></div>
          @endif
        </dl>
      @endif
    </article>
  @empty
    <p class="sg-help">Todavía no hay invitaciones asociadas al catálogo para esta empresa.</p>
  @endforelse
</section>
