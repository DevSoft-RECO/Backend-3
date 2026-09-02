<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CartillaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Agencias
        $agencias = [
            ['codigo' => 'AG01', 'nombre' => 'Agencia 01 Central', 'area_financiera' => 'GT0012600', 'activo' => true],
            ['codigo' => 'AG02', 'nombre' => 'Agencia 02 Catarina', 'area_financiera' => 'GT0012602', 'activo' => true],
            ['codigo' => 'AG03', 'nombre' => 'Agencia 03 San Antonio Huista', 'area_financiera' => 'GT0012603', 'activo' => true],
            ['codigo' => 'AG04', 'nombre' => 'Agencia 04 Camojá', 'area_financiera' => 'GT0012604', 'activo' => true],
            ['codigo' => 'AG05', 'nombre' => 'Agencia 05 Nentón', 'area_financiera' => 'GT0012605', 'activo' => true],
            ['codigo' => 'AG06', 'nombre' => 'Agencia 06 Todos Santos', 'area_financiera' => 'GT0012606', 'activo' => true],
            ['codigo' => 'AG07', 'nombre' => 'Agencia 07 Huehuetenango Z1', 'area_financiera' => 'GT0012607', 'activo' => true],
            ['codigo' => 'AG08', 'nombre' => 'Agencia 08 San Marcos Huista', 'area_financiera' => 'GT0012608', 'activo' => true],
            ['codigo' => 'AG09', 'nombre' => 'Agencia 09 Unión Cantinil', 'area_financiera' => 'GT0012609', 'activo' => true],
            ['codigo' => 'AG10', 'nombre' => 'Agencia 10 Concepción Huista', 'area_financiera' => 'GT0012610', 'activo' => true],
            ['codigo' => 'AG11', 'nombre' => 'Agencia 11 Kaibil Balam', 'area_financiera' => 'GT0012611', 'activo' => true],
            ['codigo' => 'AG12', 'nombre' => 'Agencia 12 Las Cruces', 'area_financiera' => 'GT0012612', 'activo' => true],
            ['codigo' => 'AG13', 'nombre' => 'Agencia 13 Petatán', 'area_financiera' => 'GT0012613', 'activo' => true],
            ['codigo' => 'AG14', 'nombre' => 'Agencia 14 La Libertad', 'area_financiera' => 'GT0012614', 'activo' => true],
            ['codigo' => 'AG15', 'nombre' => 'Agencia 15 La Democracia', 'area_financiera' => 'GT0012615', 'activo' => true],
            ['codigo' => 'AG16', 'nombre' => 'Agencia 16 Tajumuco', 'area_financiera' => 'GT0012616', 'activo' => true],
            ['codigo' => 'AG17', 'nombre' => 'Agencia 17 Santa Ana Huista', 'area_financiera' => 'GT0012617', 'activo' => true],
            ['codigo' => 'AG18', 'nombre' => 'Agencia 18 Tzisbaj', 'area_financiera' => 'GT0012618', 'activo' => true],
        ];

        foreach ($agencias as $agencia) {
            DB::table('cartilla_agencias')->updateOrInsert(
                ['codigo' => $agencia['codigo']],
                [
                    'nombre' => $agencia['nombre'],
                    'area_financiera' => $agencia['area_financiera'],
                    'activo' => $agencia['activo'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 2. Promocionales
        $promocionales = [
            'Pachon/Termo',
            'Taza/Pocillo',
            'Playera',
            'Gorra',
            'Paragua',
            'Olla',
        ];

        foreach ($promocionales as $promo) {
            DB::table('cartilla_promocionales')->updateOrInsert(
                ['nombre' => $promo],
                [
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 3. Notas Rápidas
        $notas = [
            'Reposición de cartilla por perdida',
            'Reposición de cartilla por deterioro',
            'Reposición de cartilla por daños físicos',
            'Cartilla completada y entrega de promocional',
            'Cartilla completada y entrega de promocional y nueva cartilla',
            'Cartilla completada y nueva por exceso de stickers',
        ];

        // Desactivar o limpiar notas anteriores no oficiales
        DB::table('cartilla_notas_rapidas')->whereNotIn('texto', $notas)->update(['activo' => false]);

        foreach ($notas as $index => $nota) {
            DB::table('cartilla_notas_rapidas')->updateOrInsert(
                ['texto' => $nota],
                [
                    'orden' => $index + 1,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        /*
        // 4. Recordatorios (Comentado para no sobreescribir en producción)
        $recordatorios = [
            'Recuerda verificar la existencia física de promocionales antes de asignarlos.',
            'La primera cartilla de cada asociado debe ser entregada con su primera acción.',
            'El número de cuenta debe tener exactamente 15 dígitos y empezar con 126.',
            'Los créditos nuevos otorgan 15 stickers y requieren ingresar cuenta y monto.',
            'Los plazos fijos otorgan 15 stickers y están sujetos a validación de un único diario por asociado.',
            'Las motocicletas financiadas otorgan 15 stickers, al contado 10 stickers. No requieren número de cuenta.',
            'Los pagos puntuales otorgan 5 stickers y requieren validación de cuenta.',
            'Revisa constantemente tu Kárdex de inventario en la sección de movimientos.',
            'Todo egreso por pérdida o deterioro de cartillas debe registrarse como reposición.',
            'Cualquier anomalía repórtala de inmediato al departamento de Mercadeo.',
            'Mantén tu sesión activa utilizando el portal y evita el cierre automático por inactividad.',
            'El sorteo se realizará de forma consolidada al finalizar la promoción.',
        ];

        foreach ($recordatorios as $index => $rec) {
            DB::table('cartilla_recordatorios')->updateOrInsert(
                ['texto' => $rec],
                [
                    'orden' => $index + 1,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 5. Configuración Base (Comentado para no sobreescribir en producción)
        $configuraciones = [
            'mecanica' => [
                'stickers_credito_nuevo' => 15,
                'stickers_plazo_fijo' => 15,
                'stickers_moto_financiada' => 15,
                'stickers_moto_contado' => 10,
                'stickers_pago_puntual' => 5,
                'stickers_cartilla_completa' => 30,
                'prefijo_cuenta' => '126',
                'digitos_cuenta' => 15,
                'plazo_fijo_unico_diario' => true
            ],
            'alertas_agencia' => [
                'stickers' => 500,
                'cartillas' => 50,
                'promocionales' => 20
            ],
            'alertas_central' => [
                'stickers' => 5000,
                'cartillas' => 1000,
                'promocionales' => 500
            ],
            'info_evento' => [
                'nombre' => 'La Cartilla Ganadora - Cooperativa Yaman Kutx',
            ]
        ];

        foreach ($configuraciones as $clave => $valor) {
            DB::table('cartilla_configuracion')->updateOrInsert(
                ['clave' => $clave],
                [
                    'valor' => json_encode($valor),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 6. Inicialización de stocks en cero (Comentado para no reiniciar los inventarios en producción)
        $dbAgencias = DB::table('cartilla_agencias')->get();

        // Stock Central (agencia_id = null)
        DB::table('cartilla_inventario_stocks')->updateOrInsert(
            ['agencia_id' => null, 'recurso' => 'STICKERS', 'nombre_promocional' => null],
            ['cantidad' => 0, 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('cartilla_inventario_stocks')->updateOrInsert(
            ['agencia_id' => null, 'recurso' => 'CARTILLAS', 'nombre_promocional' => null],
            ['cantidad' => 0, 'created_at' => now(), 'updated_at' => now()]
        );
        foreach ($promocionales as $promo) {
            DB::table('cartilla_inventario_stocks')->updateOrInsert(
                ['agencia_id' => null, 'recurso' => 'PROMOCIONAL', 'nombre_promocional' => $promo],
                ['cantidad' => 0, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // Stocks para agencias
        foreach ($dbAgencias as $ag) {
            DB::table('cartilla_inventario_stocks')->updateOrInsert(
                ['agencia_id' => $ag->id, 'recurso' => 'STICKERS', 'nombre_promocional' => null],
                ['cantidad' => 0, 'created_at' => now(), 'updated_at' => now()]
            );
            DB::table('cartilla_inventario_stocks')->updateOrInsert(
                ['agencia_id' => $ag->id, 'recurso' => 'CARTILLAS', 'nombre_promocional' => null],
                ['cantidad' => 0, 'created_at' => now(), 'updated_at' => now()]
            );
            foreach ($promocionales as $promo) {
                DB::table('cartilla_inventario_stocks')->updateOrInsert(
                    ['agencia_id' => $ag->id, 'recurso' => 'PROMOCIONAL', 'nombre_promocional' => $promo],
                    ['cantidad' => 0, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
        */
    }
}
