<?php

namespace App\Modules\SeguimientoEgresado\Services\Empresas;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gestión de empleadores: el catálogo de empresas del CRM y sus vínculos con los egresados.
 *
 * No hay tablas ni migraciones nuevas: la empresa vive en `comun.empresa` (el RUC ya es único en la base) y el
 * vínculo con un egresado en `comun.contacto_empresa`, que ata a la empresa con una persona de `core.persona`.
 * El egresado se identifica por su tipo y número de documento, los mismos con los que está en el padrón; el vínculo
 * se activa o se apaga con el estado del contacto, así que «desvincular» no borra el registro.
 */
final class GestionDeEmpresas
{
    public const ACTIVO = 'Activo';

    public const INACTIVO = 'Inactivo';

    /** Máximo de egresados ofrecidos para vincular en una sola consulta. */
    private const TOPE_DISPONIBLES = 300;

    /** @return list<string> */
    public static function estados(): array
    {
        return [self::ACTIVO, self::INACTIVO];
    }

    /** @return Collection<int, object> */
    public function listar(?string $busqueda, ?string $estado, ?int $sector): Collection
    {
        $consulta = DB::table('comun.empresa as em')
            ->leftJoin('comun.sector_economico as s', 's.id_sector', '=', 'em.id_sector')
            ->orderBy('em.razon_social');

        $texto = self::texto($busqueda);
        if ($texto !== null) {
            $termino = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto).'%';
            $consulta->where(function ($q) use ($termino) {
                $q->where('em.ruc', 'like', $termino)
                    ->orWhere('em.razon_social', 'ilike', $termino)
                    ->orWhere('em.nombre_comercial', 'ilike', $termino);
            });
        }

        if ($estado !== null && $estado !== '') {
            $consulta->where('em.estado', $estado);
        }

        if ($sector !== null) {
            $consulta->where('em.id_sector', $sector);
        }

        return $consulta->get([
            'em.id_empresa', 'em.ruc', 'em.razon_social', 'em.nombre_comercial', 'em.id_sector', 'em.estado',
            's.nombre as sector_nombre',
            // El conteo va en los mismos campos que se piden: `get()` ignora las columnas si ya hay un select previo.
            DB::raw("(SELECT COUNT(*) FROM comun.contacto_empresa ce JOIN core.persona p ON p.id_persona = ce.id_persona JOIN seguimiento_egresado.egresado e ON e.tipo_documento = p.tipo_documento AND e.numero_documento = p.numero_documento WHERE ce.id_empresa = em.id_empresa AND ce.estado = 'Activo') as vinculados"),
        ]);
    }

    /** @return Collection<int, object> */
    public function sectores(): Collection
    {
        return DB::table('comun.sector_economico')->orderBy('nombre')->get(['id_sector', 'codigo', 'nombre']);
    }

    public function empresa(int $id): object
    {
        $empresa = DB::table('comun.empresa as em')
            ->leftJoin('comun.sector_economico as s', 's.id_sector', '=', 'em.id_sector')
            ->where('em.id_empresa', $id)
            ->first([
                'em.id_empresa', 'em.ruc', 'em.razon_social', 'em.nombre_comercial', 'em.id_sector', 'em.direccion',
                'em.telefono', 'em.correo', 'em.pagina_web', 'em.estado', 's.nombre as sector_nombre',
            ]);

        if ($empresa === null) {
            throw new GestionNoValida('La empresa indicada no existe.', 'empresa');
        }

        return $empresa;
    }

    /**
     * Crea o actualiza una empresa. El RUC lo protege la base y la razón social se compara aquí sin distinguir
     * mayúsculas de minúsculas, porque dos veces la misma razón social con otro RUC también confunde.
     */
    public function guardar(array $datos, ?int $id = null): int
    {
        try {
            return DB::transaction(function () use ($datos, $id) {
                // Serializa las altas/ediciones de este catálogo para comprobar duplicados sin carreras.
                DB::select('SELECT pg_advisory_xact_lock(20261122, 400)');

                return $this->guardarBloqueado($datos, $id);
            });
        } catch (UniqueConstraintViolationException) {
            throw new GestionNoValida('Ya hay una empresa registrada con ese RUC.', 'ruc');
        }
    }

    private function guardarBloqueado(array $datos, ?int $id): int
    {
        $ruc = trim((string) ($datos['ruc'] ?? ''));
        $razonSocial = trim((string) ($datos['razon_social'] ?? ''));

        $existentes = fn () => DB::table('comun.empresa')->when(
            $id !== null,
            fn ($q) => $q->where('id_empresa', '<>', $id),
        );

        if ($existentes()->where('ruc', $ruc)->exists()) {
            throw new GestionNoValida('Ya hay una empresa registrada con el RUC '.$ruc.', usa otra o edita la que ya existe.', 'ruc');
        }

        if ($existentes()->whereRaw('UPPER(razon_social) = ?', [mb_strtoupper($razonSocial)])->exists()) {
            throw new GestionNoValida('Ya hay una empresa registrada con la razón social «'.$razonSocial.'».', 'razon_social');
        }

        $campos = [
            'ruc' => $ruc,
            'razon_social' => $razonSocial,
            'nombre_comercial' => self::texto($datos['nombre_comercial'] ?? null),
            'id_sector' => ! empty($datos['id_sector']) ? (int) $datos['id_sector'] : null,
            'direccion' => self::texto($datos['direccion'] ?? null),
            'telefono' => self::texto($datos['telefono'] ?? null),
            'correo' => self::texto($datos['correo'] ?? null),
            'pagina_web' => self::texto($datos['pagina_web'] ?? null),
            'estado' => in_array($datos['estado'] ?? null, self::estados(), true) ? $datos['estado'] : self::ACTIVO,
        ];

        if ($id !== null) {
            $actualizadas = DB::table('comun.empresa')->where('id_empresa', $id)->update($campos);
            if ($actualizadas === 0) {
                throw new GestionNoValida('La empresa indicada no existe.', 'empresa');
            }

            return $id;
        }

        return (int) DB::table('comun.empresa')->insertGetId($campos, 'id_empresa');
    }

    /**
     * Asocia un egresado a la empresa. La persona se crea solo si todavía no existe con ese documento, y el
     * contacto se reactiva si el vínculo se había apagado.
     *
     * @return bool true si el vínculo es nuevo, false si ya estaba activo
     */
    public function vincular(int $empresaId, int $egresadoId): bool
    {
        return DB::transaction(function () use ($empresaId, $egresadoId) {
            DB::table('seguimiento_egresado.egresado')->where('id', $egresadoId)->lockForUpdate()->first();

            return $this->vincularBloqueado($empresaId, $egresadoId);
        });
    }

    private function vincularBloqueado(int $empresaId, int $egresadoId): bool
    {
        if (! DB::table('comun.empresa')->where('id_empresa', $empresaId)->exists()) {
            throw new GestionNoValida('La empresa indicada no existe.', 'empresa');
        }

        $egresado = DB::table('seguimiento_egresado.egresado')
            ->where('id', $egresadoId)
            ->first(['tipo_documento', 'numero_documento', 'correo_institucional']);

        if ($egresado === null || $egresado->numero_documento === null) {
            throw new GestionNoValida('El egresado indicado no existe o no tiene documento registrado.', 'id_egresado');
        }

        $personaId = DB::table('core.persona')
            ->where('tipo_documento', $egresado->tipo_documento)
            ->where('numero_documento', $egresado->numero_documento)
            ->value('id_persona');

        if ($personaId === null) {
            $datos = DB::table('seguimiento_egresado.egresado')->where('id', $egresadoId)
                ->first(['nombres', 'apellido_paterno', 'apellido_materno']);
            $personaId = (int) DB::table('core.persona')->insertGetId([
                'tipo_documento' => $egresado->tipo_documento,
                'numero_documento' => $egresado->numero_documento,
                'nombres' => $datos->nombres,
                'apellido_paterno' => $datos->apellido_paterno,
                'apellido_materno' => $datos->apellido_materno,
            ], 'id_persona');
        }

        $contacto = DB::table('comun.contacto_empresa')
            ->where('id_empresa', $empresaId)
            ->where('id_persona', $personaId)
            ->first(['id_contacto', 'estado']);

        if ($contacto !== null) {
            if ($contacto->estado === self::ACTIVO) {
                return false;
            }

            DB::table('comun.contacto_empresa')->where('id_contacto', $contacto->id_contacto)->update([
                'estado' => self::ACTIVO,
                'correo_laboral' => $egresado->correo_institucional,
            ]);

            return true;
        }

        DB::table('comun.contacto_empresa')->insert([
            'id_empresa' => $empresaId,
            'id_persona' => $personaId,
            'correo_laboral' => $egresado->correo_institucional,
            'estado' => self::ACTIVO,
        ]);

        return true;
    }

    /** Apaga el vínculo; el registro se conserva para no perder el histórico. */
    public function desvincular(int $empresaId, int $egresadoId): void
    {
        $egresado = DB::table('seguimiento_egresado.egresado')
            ->where('id', $egresadoId)
            ->first(['tipo_documento', 'numero_documento']);

        if ($egresado === null || $egresado->numero_documento === null) {
            throw new GestionNoValida('El egresado indicado no existe.', 'id_egresado');
        }

        $personaIds = DB::table('core.persona')
            ->where('tipo_documento', $egresado->tipo_documento)
            ->where('numero_documento', $egresado->numero_documento)
            ->pluck('id_persona');

        DB::table('comun.contacto_empresa')
            ->where('id_empresa', $empresaId)
            ->whereIn('id_persona', $personaIds)
            ->where('estado', self::ACTIVO)
            ->update(['estado' => self::INACTIVO]);
    }

    /** @return Collection<int, object> */
    public function vinculados(int $empresaId): Collection
    {
        return DB::table('comun.contacto_empresa as ce')
            ->join('core.persona as p', 'p.id_persona', '=', 'ce.id_persona')
            ->join('seguimiento_egresado.egresado as e', function ($join) {
                $join->on('e.tipo_documento', '=', 'p.tipo_documento')->on('e.numero_documento', '=', 'p.numero_documento');
            })
            ->leftJoin('seguimiento_egresado.cohorte as c', 'c.id', '=', 'e.id_cohorte')
            ->where('ce.id_empresa', $empresaId)
            ->where('ce.estado', self::ACTIVO)
            ->orderBy('e.apellido_paterno')
            ->orderBy('e.apellido_materno')
            ->orderBy('e.nombres')
            ->get([
                'ce.id_contacto', 'e.id as id_egresado', 'e.nombres', 'e.apellido_paterno', 'e.apellido_materno',
                'e.tipo_documento', 'e.numero_documento', 'e.correo_institucional', 'e.programa', 'c.anio_ingreso',
                'ce.correo_laboral',
            ]);
    }

    /** Egresados que todavía no están vinculados a la empresa, para el selector de la página de detalle. */
    public function disponibles(int $empresaId, ?string $busqueda = null): Collection
    {
        $consulta = DB::table('seguimiento_egresado.egresado as e')
            ->leftJoin('seguimiento_egresado.cohorte as c', 'c.id', '=', 'e.id_cohorte')
            ->whereNotExists(function ($q) use ($empresaId) {
                $q->select(DB::raw(1))
                    ->from('comun.contacto_empresa as ce2')
                    ->join('core.persona as p2', 'p2.id_persona', '=', 'ce2.id_persona')
                    ->whereColumn('p2.tipo_documento', 'e.tipo_documento')
                    ->whereColumn('p2.numero_documento', 'e.numero_documento')
                    ->where('ce2.id_empresa', $empresaId)
                    ->where('ce2.estado', self::ACTIVO);
            })
            ->orderBy('e.apellido_paterno')
            ->orderBy('e.apellido_materno')
            ->orderBy('e.nombres')
            ->limit(self::TOPE_DISPONIBLES);

        $texto = self::texto($busqueda);
        if ($texto !== null) {
            $termino = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto).'%';
            $consulta->where(function ($q) use ($termino) {
                $q->where('e.nombres', 'ilike', $termino)
                    ->orWhere('e.apellido_paterno', 'ilike', $termino)
                    ->orWhere('e.apellido_materno', 'ilike', $termino)
                    ->orWhere('e.numero_documento', 'like', $termino)
                    ->orWhere('e.correo_institucional', 'ilike', $termino);
            });
        }

        return $consulta->get([
            'e.id', 'e.nombres', 'e.apellido_paterno', 'e.apellido_materno', 'e.numero_documento',
            'e.correo_institucional', 'c.anio_ingreso',
        ]);
    }

    private static function texto(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $limpio = trim(preg_replace('/\s+/u', ' ', $valor) ?? '');

        return $limpio === '' ? null : $limpio;
    }

    public function invitaciones(int $empresaId): Collection
    {
        $filas = DB::table('seguimiento_egresado.invitacion_empresa as i')
            ->join('seguimiento_egresado.campania_seguimiento as c', 'c.id', '=', 'i.id_campania')
            ->leftJoin('seguimiento_egresado.egresado as e', 'e.id', '=', 'i.id_egresado')
            ->leftJoin('seguimiento_egresado.respuesta_empresa as r', 'r.id_invitacion_empresa', '=', 'i.id')
            ->leftJoin('seguimiento_egresado.cat_nivel_satisfaccion as sf', 'sf.id', '=', 'r.id_satisfaccion_formacion')
            ->where('i.id_empresa', $empresaId)->orderByDesc('i.id')
            ->get(['i.id', 'i.contexto', 'i.estado', 'i.expira_en', 'i.respondido_en', 'c.nombre as campania', 'e.nombres', 'e.apellido_paterno',
                'r.id as id_respuesta', 'r.demanda_profesionales', 'sf.nombre as satisfaccion_formacion',
                ...array_map(fn ($campo) => 'r.'.$campo, array_keys(CuestionarioEmpresa::DIMENSIONES)),
            ]);
        $ids = $filas->pluck('id_respuesta')->filter();
        $sectores = DB::table('seguimiento_egresado.respuesta_empresa_sector as rs')
            ->join('seguimiento_egresado.cat_sector_empresa as s', 's.id', '=', 'rs.id_sector')
            ->whereIn('rs.id_respuesta', $ids)->orderBy('s.nombre')->get(['rs.id_respuesta', 's.nombre'])->groupBy('id_respuesta');
        $perfiles = DB::table('seguimiento_egresado.respuesta_empresa_perfil')
            ->whereIn('id_respuesta', $ids)->orderBy('codigo')->get(['id_respuesta', 'codigo'])->groupBy('id_respuesta');

        return $filas->map(function ($fila) use ($sectores, $perfiles) {
            $fila->sectores_contratacion = $sectores->get($fila->id_respuesta, collect())->pluck('nombre')->implode(', ');
            $fila->perfiles_requeridos = $perfiles->get($fila->id_respuesta, collect())->pluck('codigo')
                ->map(fn ($codigo) => CuestionarioEmpresa::PERFILES[$codigo])->implode(', ');

            return $fila;
        });
    }
}
