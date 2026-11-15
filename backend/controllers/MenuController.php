<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Menu.php';
require_once __DIR__ . '/../models/Calendario.php';

class MenuController {
    private Menu      $menuModel;
    private Calendario $calendarioModel;

    public function __construct(?Menu $menuModel = null, ?Calendario $calendarioModel = null) {
        $this->menuModel       = $menuModel ?? new Menu();
        $this->calendarioModel = $calendarioModel ?? new Calendario();
    }

    /**
     * Publica o actualiza el menú de una fecha.
     * Solo el administrador puede hacerlo.
     */
    public function publicar(
        string $fecha,
        string $sopa,
        string $platoPrincipal,
        string $postre,
        int $adminId,
        ?string $imagenPath = null
    ): array {
        $platos = array_map('trim', [$sopa, $platoPrincipal, $postre]);
        if (in_array('', $platos, true)) {
            return ['success' => false, 'message' => 'Completa la sopa, el plato principal y el postre.'];
        }
        if (max(array_map('mb_strlen', $platos)) > 150) {
            return ['success' => false, 'message' => 'Cada plato debe tener como máximo 150 caracteres.'];
        }

        $dt = DateTime::createFromFormat('!Y-m-d', $fecha);
        if (!$dt || $dt->format('Y-m-d') !== $fecha) {
            return ['success' => false, 'message' => 'La fecha del menú no es válida.'];
        }

        if (!$this->calendarioModel->esDiaOperativo($fecha)) {
            return ['success' => false, 'message' => 'Solo se pueden publicar menús para días operativos (Lunes a Jueves, sin feriados).'];
        }

        $this->menuModel->guardar($fecha, $platos[0], $platos[1], $platos[2], $adminId, $imagenPath);
        return ['success' => true, 'message' => 'Menú publicado correctamente para el ' . $dt->format('d/m/Y') . '.'];
    }

    /**
     * Obtiene el menú de la semana actual (inicio: lunes).
     */
    public function menuSemanaActual(): array {
        // Calcular el lunes de la semana actual con DateTime (evita strtotime en Windows)
        $dt = new DateTime();
        $diaSemana = (int) $dt->format('N'); // 1=Lun … 7=Dom
        if ($diaSemana !== 1) {
            $dt->modify('last monday');
        }
        $lunes = $dt->format('Y-m-d');
        return $this->menuModel->listarSemana($lunes);
    }

    /**
     * Obtiene el menú de una semana específica a partir de una fecha.
     */
    public function menuSemana(string $fechaInicio): array {
        return $this->menuModel->listarSemana($fechaInicio);
    }

    /**
     * Obtiene el menú de una fecha específica.
     */
    public function menuDelDia(string $fecha): array|false {
        return $this->menuModel->findByFecha($fecha);
    }

    /**
     * Lista todos los menús (para panel admin).
     */
    public function listarTodos(int $pagina = 1, int $porPagina = 30): array {
        $offset = ($pagina - 1) * $porPagina;
        return $this->menuModel->listarTodos($porPagina, $offset);
    }

    /**
     * Elimina un menú.
     */
    public function eliminar(int $menuId): array {
        $menu = $this->menuModel->findById($menuId);
        if (!$menu) {
            return ['success' => false, 'message' => 'Menú no encontrado.'];
        }
        // No eliminar menús de fechas pasadas que ya tienen reservas (no crítico, solo informativo)
        $this->menuModel->eliminar($menuId);
        return ['success' => true, 'message' => 'Menú eliminado.'];
    }
}
