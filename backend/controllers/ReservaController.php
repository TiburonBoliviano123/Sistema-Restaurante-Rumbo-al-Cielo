<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../models/Reserva.php';
require_once __DIR__ . '/../models/Familiar.php';
require_once __DIR__ . '/../models/Calendario.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Menu.php';

class ReservaController {
    private Reserva   $reservaModel;
    private Familiar  $familiarModel;
    private Calendario $calendarioModel;
    private Usuario   $usuarioModel;
    private Menu      $menuModel;

    public function __construct() {
        $this->reservaModel   = new Reserva();
        $this->familiarModel  = new Familiar();
        $this->calendarioModel= new Calendario();
        $this->usuarioModel   = new Usuario();
        $this->menuModel      = new Menu();
    }

    private function parametro(string $clave, string $porDefecto): string {
        try {
            $db = Database::getConnection();
            $q = $db->prepare('SELECT valor FROM configuracion_sistema WHERE clave = ?');
            $q->execute([$clave]);
            return (string)($q->fetchColumn() ?: $porDefecto);
        } catch (Throwable $e) { return $porDefecto; }
    }

    private function modalidadDelMes(int $usuarioId, string $fecha, int $beneficiarioId = 0): array {
        $dt = new DateTime($fecha);
        $q = Database::getConnection()->prepare("SELECT COUNT(*) FROM inscripciones_pension WHERE usuario_id=? AND beneficiario_id=? AND anio=? AND mes=? AND estado='ACTIVA'");
        $q->execute([$usuarioId, $beneficiarioId, (int)$dt->format('Y'), (int)$dt->format('m')]);
        $pensionado = (int)$q->fetchColumn() > 0;
        return [$pensionado ? 'PENSIONADO' : 'ESPORADICO', (float)$this->parametro($pensionado ? 'tarifa_pensionado' : 'tarifa_esporadico', $pensionado ? '15.00' : '18.00')];
    }

    /**
     * Realiza una reserva para el titular.
     */
    public function reservarTitular(int $usuarioId, string $fecha): array {
        return ['success'=>false,'message'=>'Las reservas esporádicas se solicitan por WhatsApp al administrador, quien registra y confirma la solicitud en el sistema.'];
    }

    /**
     * Realiza una reserva para un familiar del titular.
     */
    public function reservarFamiliar(int $titularId, int $familiarId, string $fecha): array {
        return ['success'=>false,'message'=>'Las reservas para familiares se solicitan por WhatsApp al administrador, quien registra y confirma la solicitud.'];
    }

    /**
     * Cancela una reserva aplicando la regla de las 09:00 a.m.
     * Un titular solo puede cancelar sus reservas o las de sus familiares.
     */
    public function cancelar(int $reservaId, int $titularId): array {
        $reserva = $this->reservaModel->findById($reservaId);

        if (!$reserva) {
            return ['success' => false, 'message' => 'Reserva no encontrada.'];
        }

        if ($reserva['estado'] !== 'RESERVADO') {
            return ['success' => false, 'message' => 'Esta reserva ya fue cancelada.'];
        }

        // Verificar propiedad: la reserva debe pertenecer al titular
        if ((int) $reserva['usuario_id'] !== $titularId) {
            return ['success' => false, 'message' => 'No tienes permiso para cancelar esta reserva.'];
        }

        // Regla de hora: si es para HOY, solo se permite antes de las 09:00
        $hoy = date('Y-m-d');
        if ($reserva['fecha_reserva'] === $hoy) {
            $horaActual = date('H:i');
            $horaLimite = $this->parametro('hora_limite', HORA_LIMITE_CANCELACION);
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

        // Para pensionados inscritos, no permitir bajar de 10 almuerzos programados/cobrables.
        $dtReserva = new DateTime($reserva['fecha_reserva']);
        $q = Database::getConnection()->prepare("SELECT COUNT(*) FROM inscripciones_pension WHERE usuario_id=? AND beneficiario_id=? AND anio=? AND mes=? AND estado='ACTIVA'");
        $beneficiarioId = $reserva['tipo_beneficiario'] === 'FAMILIAR' ? (int)$reserva['familiar_id'] : 0;
        $q->execute([$titularId, $beneficiarioId, (int)$dtReserva->format('Y'), (int)$dtReserva->format('m')]);
        if ((int)$q->fetchColumn() > 0) {
            $total = $reserva['tipo_beneficiario'] === 'FAMILIAR'
                ? $this->reservaModel->contarConsumosFamiliar((int)$reserva['familiar_id'], (int)$dtReserva->format('Y'), (int)$dtReserva->format('m'))
                : $this->reservaModel->contarConsumosTitular($titularId, (int)$dtReserva->format('Y'), (int)$dtReserva->format('m'));
            $minimo=(int)$this->parametro('minimo_pension',(string)MINIMO_ALMUERZOS_MES);
            if ($total <= $minimo) return ['success'=>false,'message'=>'No puedes cancelar: debes mantener el mínimo de '.$minimo.' almuerzos mensuales.'];
        }
        $this->reservaModel->cancelar($reservaId);
        return ['success' => true, 'message' => 'Reserva cancelada correctamente.'];
    }

    /** Registra una solicitud esporádica recibida por WhatsApp; solo ADMIN. */
    public function registrarEsporadicaAdmin(int $usuarioId, int $familiarId, string $fecha): array {
        $dt=DateTime::createFromFormat('Y-m-d',$fecha);
        if(!$dt || $dt->format('Y-m-d')!==$fecha) return ['success'=>false,'message'=>'Fecha inválida.'];
        if($fecha < date('Y-m-d')) return ['success'=>false,'message'=>'No se aceptan reservas para fechas pasadas.'];
        if(date('N', $dt->getTimestamp()) > 4 || !$this->calendarioModel->esDiaOperativo($fecha)) return ['success'=>false,'message'=>'El restaurante atiende de lunes a jueves y no opera en feriados.'];
        if (!$this->menuModel->findByFecha($fecha)) return ['success'=>false,'message'=>'No hay menú publicado para la fecha solicitada.'];
        $limite=$this->parametro('hora_limite',HORA_LIMITE_CANCELACION);
        if($fecha===date('Y-m-d') && date('H:i') >= $limite) return ['success'=>false,'message'=>'Expiró el horario límite de reserva ('.$limite.').'];
        $usuario=$this->usuarioModel->findById($usuarioId);
        if(!$usuario || $usuario['tipo_cuenta']==='ADMIN' || !(int)$usuario['activo']) return ['success'=>false,'message'=>'Empleado no autorizado o inactivo.'];
        $tarifa=(float)$this->parametro('tarifa_esporadico','18.00');
        if($familiarId>0) {
            $f=$this->familiarModel->findById($familiarId);
            if(!$f || (int)$f['empleado_titular_id']!==$usuarioId || $f['estado']!=='ACTIVO') return ['success'=>false,'message'=>'Familiar inválido.'];
            if($this->reservaModel->existeParaFamiliar($familiarId,$fecha)) return ['success'=>false,'message'=>'Ya existe una reserva para ese familiar y fecha.'];
            $id=$this->reservaModel->crearParaFamiliar($usuarioId,$familiarId,$fecha,'ESPORADICO',$tarifa,true);
        } else {
            if($this->reservaModel->existeParaTitular($usuarioId,$fecha)) return ['success'=>false,'message'=>'Ya existe una reserva para ese empleado y fecha.'];
            $id=$this->reservaModel->crearParaTitular($usuarioId,$fecha,'ESPORADICO',$tarifa,true);
        }
        $destinatario=$usuario['correo'] ?? '';
        if ($destinatario) { @mail($destinatario, 'Reserva de almuerzo confirmada', 'Tu reserva para el '.$fecha.' fue confirmada por Bs '.number_format($tarifa,2).'.', 'From: sistema@rumboalcielo.bo'); }
        return ['success'=>true,'message'=>'Reserva esporádica confirmada por administración a Bs '.number_format($tarifa,2).'.','reserva_id'=>$id];
    }

    /**
     * Lista las reservas del titular y sus familiares.
     */
    public function listarMisReservas(int $titularId, ?string $fechaInicio = null, ?string $fechaFin = null): array {
        return $this->reservaModel->listarPorTitular($titularId, $fechaInicio, $fechaFin);
    }

    /**
     * Lista todas las reservas de un día (para admin).
     */
    public function listarPorFecha(string $fecha): array {
        return $this->reservaModel->listarPorFecha($fecha);
    }
}
