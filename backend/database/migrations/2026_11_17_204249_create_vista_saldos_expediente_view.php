<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Saldos por expediente (total contratado, pagado y saldo). 
        // El total suma las solicitudes vigentes   (sin canceladas ni «no aplica»)
        DB::statement(<<<'SQL'
            CREATE VIEW vista_saldos_expediente AS
            WITH totales AS (
                SELECT s.expediente_id,
                       SUM(s.valor_servicio - s.descuento + COALESCE(ps.total_productos, 0)) AS total
                  FROM solicitudes s
                  LEFT JOIN (SELECT solicitud_id, SUM(cantidad * precio_unitario) AS total_productos
                               FROM producto_solicitud WHERE deleted_at IS NULL GROUP BY solicitud_id) ps
                         ON ps.solicitud_id = s.id
                 WHERE s.deleted_at IS NULL AND s.estado NOT IN ('cancelada', 'no_aplica')
                 GROUP BY s.expediente_id
            ), pagado AS (
                SELECT expediente_id, SUM(monto) AS pagado FROM pagos GROUP BY expediente_id
            )
            SELECT e.id AS expediente_id,
                   COALESCE(t.total, 0)::bigint AS total,
                   COALESCE(p.pagado, 0)::bigint AS pagado,
                   (COALESCE(t.total, 0) - COALESCE(p.pagado, 0))::bigint AS saldo
              FROM expedientes e
              LEFT JOIN totales t ON t.expediente_id = e.id
              LEFT JOIN pagado p ON p.expediente_id = e.id
             WHERE e.deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vista_saldos_expediente');
    }
};