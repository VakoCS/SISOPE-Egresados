<?php

namespace App\Modules\SeguimientoEgresado\Controllers\Empresas;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEgresado\Services\Accesos\SesionAcceso;
use App\Modules\SeguimientoEgresado\Services\Empresas\GestionDeEmpresas;
use App\Modules\SeguimientoEgresado\Services\Empresas\GestionNoValida;
use App\Modules\SeguimientoEgresado\Services\Empresas\ResultadosEmpresas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Gestión de empleadores: alta, consulta y edición del catálogo de empresas y los vínculos con los egresados.
 * Usa las tablas compartidas `comun.empresa` y `comun.contacto_empresa`; la encuesta empresarial sigue en
 * EmpresasController, que es quien genera los enlaces de respuesta.
 */
class GestionEmpresasController extends Controller
{
    public function index(Request $request, GestionDeEmpresas $gestion): Response
    {
        $estado = $request->query('estado');
        $filtros = [
            'busqueda' => trim((string) $request->query('q', '')),
            'estado' => is_string($estado) && in_array($estado, GestionDeEmpresas::estados(), true) ? $estado : null,
            'sector' => filter_var($request->query('sector'), FILTER_VALIDATE_INT) ?: null,
        ];

        return response()->view('modules.seguimiento-egresado.paginas.empresas-gestion', [
            'empresas' => $gestion->listar($filtros['busqueda'] ?: null, $filtros['estado'], $filtros['sector']),
            'sectores' => $gestion->sectores(),
            'filtros' => $filtros,
            'estados' => GestionDeEmpresas::estados(),
            'puedeGestionar' => $this->puedeGestionar($request),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, GestionDeEmpresas $gestion): RedirectResponse
    {
        $datos = $this->validar($request);

        try {
            $id = $gestion->guardar($datos);
        } catch (GestionNoValida $e) {
            return $this->rechazado($e, 'seguimiento-egresado.empresa');
        }

        return redirect()->route('seguimiento-egresado.empresa.detalle', $id)
            ->with('success', 'Se registró la empresa en el catálogo.');
    }

    public function detalle(Request $request, int $id, GestionDeEmpresas $gestion): Response
    {
        try {
            $empresa = $gestion->empresa($id);
        } catch (GestionNoValida $e) {
            abort(404, $e->getMessage());
        }

        return response()->view('modules.seguimiento-egresado.paginas.empresa-detalle', [
            'empresa' => $empresa,
            'vinculados' => $gestion->vinculados($id),
            'disponibles' => $gestion->disponibles($id, $request->string('egresado_q')->toString()),
            'busquedaEgresado' => $request->string('egresado_q')->toString(),
            'campaniasEmpresa' => app(ResultadosEmpresas::class)->campaniasDeEmpresas(),
            'invitacionesEmpresa' => $gestion->invitaciones($id),
            'sectores' => $gestion->sectores(),
            'estados' => GestionDeEmpresas::estados(),
            'puedeGestionar' => $this->puedeGestionar($request),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request, int $id, GestionDeEmpresas $gestion): RedirectResponse
    {
        $datos = $this->validar($request, $id);

        try {
            $gestion->guardar($datos, $id);
        } catch (GestionNoValida $e) {
            return $this->rechazado($e, 'seguimiento-egresado.empresa.detalle', ['empresa' => $id]);
        }

        return redirect()->route('seguimiento-egresado.empresa.detalle', $id)
            ->with('success', 'Se actualizaron los datos de la empresa.');
    }

    public function vincular(Request $request, int $id, GestionDeEmpresas $gestion): RedirectResponse
    {
        $datos = $request->validate([
            'id_egresado' => ['required', 'integer', 'exists:'.config('database.default').'.seguimiento_egresado.egresado,id'],
        ], [
            'id_egresado.required' => 'Elige al egresado que quieres vincular.',
            'id_egresado.exists' => 'El egresado indicado no existe.',
        ]);

        try {
            $nuevo = $gestion->vincular($id, (int) $datos['id_egresado']);
        } catch (GestionNoValida $e) {
            return $this->rechazado($e, 'seguimiento-egresado.empresa.detalle', ['empresa' => $id]);
        }

        return redirect()->route('seguimiento-egresado.empresa.detalle', $id)->with(
            $nuevo ? 'success' : 'info',
            $nuevo ? 'Egresado vinculado a la empresa.' : 'Ese egresado ya estaba vinculado a la empresa.',
        );
    }

    public function desvincular(int $id, int $egresado, GestionDeEmpresas $gestion): RedirectResponse
    {
        try {
            $gestion->desvincular($id, $egresado);
        } catch (GestionNoValida $e) {
            return $this->rechazado($e, 'seguimiento-egresado.empresa.detalle', ['empresa' => $id]);
        }

        return redirect()->route('seguimiento-egresado.empresa.detalle', $id)
            ->with('info', 'Se desvinculó al egresado de la empresa.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'ruc' => ['required', 'string', 'regex:/^\d{11}$/'],
            'razon_social' => ['required', 'string', 'max:180'],
            'nombre_comercial' => ['nullable', 'string', 'max:180'],
            'id_sector' => ['nullable', 'integer', 'exists:'.config('database.default').'.comun.sector_economico,id_sector'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'correo' => ['nullable', 'email:rfc', 'max:180'],
            'pagina_web' => ['nullable', 'string', 'max:190'],
            'estado' => ['required', 'in:'.implode(',', GestionDeEmpresas::estados())],
        ], [
            'ruc.required' => 'El RUC es obligatorio: identifica a la empresa.',
            'ruc.regex' => 'El RUC debe tener exactamente 11 dígitos (ejemplo: 20512345678).',
            'razon_social.required' => 'La razón social es obligatoria.',
            'razon_social.max' => 'La razón social no puede superar 180 caracteres.',
            'id_sector.exists' => 'El sector indicado no existe.',
            'correo.email' => 'Ingresa un correo con formato válido (ejemplo: contacto@empresa.com).',
            'estado.required' => 'Indica si la empresa está activa o inactiva.',
            'estado.in' => 'El estado indicado no es válido.',
        ]);
    }

    /** @param array<string, mixed> $parametros */
    private function rechazado(GestionNoValida $excepcion, string $ruta, array $parametros = []): RedirectResponse
    {
        return redirect()->route($ruta, $parametros)
            ->withInput()
            ->withErrors([$excepcion->campo => $excepcion->getMessage()]);
    }

    /** Quien administra campañas también administra el catálogo; el resto solo consulta. */
    private function puedeGestionar(Request $request): bool
    {
        if (! class_exists(SesionAcceso::class)) {
            return true;
        }

        return (bool) SesionAcceso::desde($request)->actual()?->puede('campanias.administrar');
    }
}
