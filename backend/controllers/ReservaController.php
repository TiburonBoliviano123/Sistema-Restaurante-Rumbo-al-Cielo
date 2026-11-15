<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../models/Reserva.php';
require_once __DIR__ . '/../models/Familiar.php';
require_once __DIR__ . '/../models/Calendario.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Menu.php';
require_once __DIR__ . '/../models/Pensionamiento.php';
require_once __DIR__ . '/../models/Configuracion.php';

class ReservaController {
    private Reserva   $reservaModel;
    private Familiar  $familiarModel;
    private Calendario $calendarioModel;
    private Usuario   $usuarioModel;
    private Menu      $menuModel;
    private Pensionamiento $pensionamientoModel;
    private Configuracion $configuracionModel;
    private \Closure $nowProvider;

    public function __construct(
        ?Reserva $reservaModel = null,
        ?Familiar $familiarModel = null,
        ?Calendario $calendarioModel = null,
        ?Usuario $usuarioModel = null,
        ?Menu $menuModel = null,
        ?\Closure $nowProvider = null,
        ?Pensionamiento $pensionamientoModel = null,
        ?Configuracion $configuracionModel = null
    ) {
        $this->reservaModel    = $reservaModel ?? new Reserva();
        $this->familiarModel   = $familiarModel ?? new Familiar();
        $this->calendarioModel = $calendarioModel ?? new Calendario();
        $this->usuarioModel    = $usuarioModel ?? new Usuario();
        $this->menuModel       = $menuModel ?? new Menu();
        $this->pensionamientoModel = $pensionamientoModel ?? new Pensionamiento();
        $this->configuracionModel = $configuracionModel ?? new Configuracion();
        $this->nowProvider     = $nowProvider ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable('now');
    }

    /**
     * Realiza una reserva para el titular.
     */
    public function reservarTitular(int $usuarioId, string $fecha): array {
        // 1. Verificar que sea día operativo
        if (!$this->calendarioModel->esDiaOperativo($fecha)) {
            return ['success' => false, 'message' => 'El día seleccionado no es operativo (solo Lunes a Jueves, sin feriados).'];
        }

        // 2. Verificar que no sea fecha pasada
        if ($fecha < ($this->nowProvider)()->format('Y-m-d')) {
            return ['success' => false, 'message' => 'No puedes reservar para una fecha pasada.'];
        }

        if ($this->mismoDiaFueraDelHorarioDeReserva($fecha)) {
            return ['success' => false, 'message' => 'El plazo para reservar el almuerzo de hoy venció a las ' . $this->reglas()['hora_limite'] . '.'];
        }

        // 3. Verificar que exista menú publicado
        if (!$this->menuModel->findByFecha($fecha)) {
            return ['success' => false, 'message' => 'No hay menú publicado para ese día.'];
        }

        // 4. Verificar que no tenga ya una reserva activa
        if ($this->reservaModel->existeParaTitular($usuarioId, $fecha)) {
            return ['success' => false, 'message' => 'Ya tienes una reserva activa para ese día.'];
        }

        // 5. Verificar tipo de cuenta (solo PENSIONADO y NO_PENSIONADO pueden reservar)
        $usuario = $this->usuarioModel->findById($usuarioId);
        if (!$usuario || $usuario['tipo_cuenta'] === 'ADMIN') {
            return ['success' => false, 'message' => 'Los administradores no realizan reservas.'];
        }

        $anio = (int) substr($fecha, 0, 4);
        $mes = (int) substr($fecha, 5, 2);
        $modalidad = $this->pensionamientoModel->estaRegistrado($usuarioId, null, $anio, $mes)
            ? 'PENSIONADO'
            : 'ESPORADICO';
        $id = $this->reservaModel->crearParaTitular($usuarioId, $fecha, $modalidad);
        return ['success' => true, 'message' => 'Reserva realizada correctamente.', 'reserva_id' => $id];
    }

    /**
     * Realiza una reserva para un familiar del titular.
     */
    public function reservarFamiliar(int $titularId, int $familiarId, string $fecha): array {
        // 1. Verificar que el familiar pertenece al titular
        $familiar = $this->familiarModel->findById($familiarId);
        if (!$familiar || (int) $familiar['empleado_titular_id'] !== $titularId) {
            return ['success' => false, 'message' => 'El familiar no pertenece a tu cuenta.'];
        }

        // 2. Familiar activo
        if ($familiar['estado'] !== 'ACTIVO') {
            return ['success' => false, 'message' => 'El familiar está inactivo.'];
        }

        // 3. Verificar que sea día operativo
        if (!$this->calendarioModel->esDiaOperativo($fecha)) {
            return ['success' => false, 'message' => 'El día seleccionado no es operativo (solo Lunes a Jueves, sin feriados).'];
        }

        // 4. No fecha pasada
        if ($fecha < ($this->nowProvider)()->format('Y-m-d')) {
            return ['success' => false, 'message' => 'No puedes reservar para una fecha pasada.'];
        }

        if ($this->mismoDiaFueraDelHorarioDeReserva($fecha)) {
            return ['success' => false, 'message' => 'El plazo para reservar el almuerzo de hoy venció a las ' . $this->reglas()['hora_limite'] . '.'];
        }

        // 5. Verificar menú publicado
        if (!$this->menuModel->findByFecha($fecha)) {
            return ['success' => false, 'message' => 'No hay menú publicado para ese día.'];
        }

        // 6. Verificar que el titular sea PENSIONADO (solo pensionados tienen familiares)
        $titular = $this->usuarioModel->findById($titularId);
        if (!$titular || $titular['tipo_cuenta'] !== 'PENSIONADO') {
            return ['success' => false, 'message' => 'Solo empleados pensionados pueden gestionar reservas de familiares.'];
        }

        // 7. Verificar reserva duplicada
        if ($this->reservaModel->existeParaFamiliar($familiarId, $fecha)) {
            return ['success' => false, 'message' => 'El familiar ya tiene una reserva activa para ese día.'];
        }

        $anio = (int) substr($fecha, 0, 4);
        $mes = (int) substr($fecha, 5, 2);
        $modalidad = $this->pensionamientoModel->estaRegistrado($titularId, $familiarId, $anio, $mes)
            ? 'PENSIONADO'
            : 'ESPORADICO';
        $id = $this->reservaModel->crearParaFamiliar($titularId, $familiarId, $fecha, $modalidad);
        return ['success' => true, 'message' => 'Reserva del familiar realizada correctamente.', 'reserva_id' => $id];
    }

    /**
     * Cancela una reserva aplicando la hora límite configurada.
     * Un titular solo puede cancelar sus reservas o las de sus familiares.
     */
    public function cancelar(int $reservaId, int $titularId): array {
        $this->actualizarEstadosAutomaticos();
        $reserva = $this->reservaModel->findById($reservaId);

        if (!$reserva) {
            return ['success' => false, 'message' => 'Reserva no encontrada.'];
        }

        if ($reserva['estado'] !== 'RESERVADO') {
            return ['success' => false, 'message' => 'Esta reserva ya no se puede cancelar.'];
        }

        // Verificar propiedad: la reserva debe pertenecer al titular
        if ((int) $reserva['usuario_id'] !== $titularId) {
            return ['success' => false, 'message' => 'No tienes permiso para cancelar esta reserva.'];
        }

        // Regla de hora: si es para hoy, solo se permite antes del corte configurado.
        $ahora = ($this->nowProvider)();
        $hoy = $ahora->format('Y-m-d');
        if ($reserva['fecha_reserva'] === $hoy) {
            $horaActual = $ahora->format('H:i');
            $horaLimite = $this->reglas()['hora_limite'];
            if ($horaActual >= $horaLimite) {
                return [
                    'success' => false,
                    'message' => 'Las reservas del día de hoy solo pueden cancelarse antes de las ' . $horaLimite . '.'
                ];
            }
        }

        // No se puede cancelar reservas de días pasados
        if ($reserva['fecha_reserva'] < $hoy) {
            return ['success' => false, 'message' => 'No puedes cancelar reservas de fechas pasadas.'];
        }

        $usuario = $this->usuarioModel->findById($titularId);
        if ($usuario) {
            $anio = (int) substr($reserva['fecha_reserva'], 0, 4);
            $mes = (int) substr($reserva['fecha_reserva'], 5, 2);
            $tipoBeneficiario = $reserva['tipo_beneficiario'] ?? 'TITULAR';
            $familiarId = $tipoBeneficiario === 'FAMILIAR'
                ? (int) $reserva['familiar_id']
                : null;
            $estaPensionado = $this->pensionamientoModel->estaRegistrado($titularId, $familiarId, $anio, $mes);
            if ($estaPensionado) {
            $almuerzosActivos = $tipoBeneficiario === 'FAMILIAR'
                ? $this->reservaModel->contarConsumosFamiliar((int) $reserva['familiar_id'], $anio, $mes)
                : $this->reservaModel->contarConsumosTitular($titularId, $anio, $mes);

            $minimoMensual = $this->reglas()['minimo_almuerzos_mes'];
            if ($almuerzosActivos <= $minimoMensual) {
                return [
                    'success' => false,
                    'message' => 'No puedes cancelar porque quedarías por debajo del mínimo mensual de ' . $minimoMensual . ' almuerzos.',
                ];
            }
            }
        }

        $this->reservaModel->cancelar($reservaId);
        return ['success' => true, 'message' => 'Reserva cancelada correctamente.'];
    }

    /**
     * Lista las reservas del titular y sus familiares.
     */
    public function listarMisReservas(int $titularId, ?string $fechaInicio = null, ?string $fechaFin = null): array {
        $this->actualizarEstadosAutomaticos();
        return $this->reservaModel->listarPorTitular($titularId, $fechaInicio, $fechaFin);
    }

    /**
     * Lista todas las reservas de un día (para admin).
     */
    public function listarPorFecha(string $fecha): array {
        $this->actualizarEstadosAutomaticos();
        return $this->reservaModel->listarPorFecha($fecha);
    }

    public function consolidadoDisponible(string $fecha): bool {
        $ahora = ($this->nowProvider)();
        $hoy = $ahora->format('Y-m-d');
        return $fecha < $hoy
            || ($fecha === $hoy && $ahora->format('H:i') >= $this->reglas()['hora_limite']);
    }

    public function marcarEntregada(int $reservaId): array {
        $ahora = ($this->nowProvider)();
        $horaLimite = $this->reglas()['hora_limite'];
        if ($ahora->format('H:i') < $horaLimite) {
            return ['success' => false, 'message' => 'El registro de entrega estará disponible después del corte de las ' . $horaLimite . '.'];
        }

        $this->actualizarEstadosAutomaticos();
        if (!$this->reservaModel->marcarEntregada($reservaId, $ahora->format('Y-m-d'))) {
            return ['success' => false, 'message' => 'La reserva no está pendiente de entrega para hoy.'];
        }

        return ['success' => true, 'message' => 'Almuerzo marcado como entregado y cobrable.'];
    }

    private function actualizarEstadosAutomaticos(): void {
        $ahora = ($this->nowProvider)();
        $this->reservaModel->actualizarEstadosAutomaticos(
            $ahora->format('Y-m-d'),
            $ahora->format('H:i'),
            $this->reglas()['hora_limite']
        );
    }

    private function mismoDiaFueraDelHorarioDeReserva(string $fecha): bool {
        $ahora = ($this->nowProvider)();
        return $fecha === $ahora->format('Y-m-d')
            && $ahora->format('H:i') >= $this->reglas()['hora_limite'];
    }

    private function reglas(): array {
        return $this->configuracionModel->obtener();
    }
}
