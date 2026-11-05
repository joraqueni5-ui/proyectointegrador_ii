<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\Comunidad;
use App\Models\Cosecha;
use App\Models\DiagnosticoCNN;
use App\Models\Inspeccion;
use App\Models\Parcela;
use App\Models\Productor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function kpis(Request $request)
    {
        $this->checkPermiso($request, 'ver_dashboard');

        return response()->json([
            'productores' => Productor::where('activo', true)->count(),
            'parcelas' => Parcela::where('estado', 'Activa')->count(),
            'inspecciones' => Inspeccion::count(),
            'diagnosticos' => DiagnosticoCNN::count(),
            'alertas_activas' => Alerta::where('estado', 'Activa')->count(),
            'cosechas_total_ton' => Cosecha::sum('cantidad_toneladas'),
            'comunidades' => Comunidad::where('activo', true)->count(),
        ]);
    }

    public function graficoEnfermedades(Request $request)
    {
        $this->checkPermiso($request, 'ver_dashboard');

        $data = DiagnosticoCNN::query()
            ->join('enfermedades', 'enfermedades.id', '=', 'diagnosticos_cnn.id_enfermedad')
            ->groupBy('enfermedades.id', 'enfermedades.nombre_comun')
            ->orderByDesc('total')
            ->get([
                'enfermedades.nombre_comun as nombre',
                DB::raw('COUNT(*) as total'),
            ])
            ->map(fn ($row) => [
                'nombre' => $row->nombre ?? 'Desconocida',
                'total' => (int) $row->total,
            ]);

        return response()->json($data);
    }

    public function graficoCosechas(Request $request)
    {
        $this->checkPermiso($request, 'ver_dashboard');

        // Últimos 12 meses con cosechas, en orden cronológico.
        $data = Cosecha::selectRaw("DATE_TRUNC('month', fecha_cosecha) as mes, SUM(cantidad_toneladas) as total")
            ->groupBy('mes')
            ->orderByDesc('mes')
            ->limit(12)
            ->get()
            ->reverse()
            ->map(fn ($row) => [
                'mes' => date('M Y', strtotime($row->mes)),
                'total' => round((float) $row->total, 2),
            ])
            ->values();

        return response()->json($data);
    }

    public function mapaParcelas(Request $request)
    {
        $this->checkPermiso($request, 'ver_dashboard');

        $parcelas = Parcela::with([
            'productor:id,nombre_completo',
            'cultivoActivo:id,id_parcela,variedad_papa,estado',
        ])
            ->whereNotNull('latitud')
            ->whereNotNull('longitud')
            ->where('estado', '!=', 'Inactiva')
            ->get([
                'id',
                'codigo_parcela',
                'nombre_referencial',
                'latitud',
                'longitud',
                'estado',
                'id_productor',
                'superficie_ha',
            ]);

        return response()->json($parcelas);
    }

    public function alertasRecientes(Request $request)
    {
        $this->checkPermiso($request, 'ver_dashboard');

        // nivel_riesgo es texto: se ordena por gravedad, no alfabéticamente.
        return response()->json(
            Alerta::with([
                'diagnostico.enfermedad:id,nombre_comun',
                'diagnostico.inspeccion.parcela:id,codigo_parcela',
            ])
                ->where('estado', 'Activa')
                ->orderByRaw("CASE nivel_riesgo WHEN 'Critico' THEN 4 WHEN 'Alto' THEN 3 WHEN 'Medio' THEN 2 ELSE 1 END DESC")
                ->orderByDesc('fecha_emision')
                ->limit(5)
                ->get()
        );
    }

    public function inspeccionesRecientes(Request $request)
    {
        $this->checkPermiso($request, 'ver_dashboard');

        return response()->json(
            Inspeccion::with([
                'usuario:id,name,paterno',
                'parcela:id,codigo_parcela',
                'parcela.productor:id,nombre_completo',
            ])
                ->latest('fecha_visita')
                ->limit(5)
                ->get([
                    'id',
                    'fecha_visita',
                    'estado',
                    'id_usuario',
                    'id_parcela',
                ])
        );
    }
}
