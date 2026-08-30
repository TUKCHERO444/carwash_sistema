<?php

namespace App\Services;

/**
 * Centraliza las reglas de estandarización de datos del sistema.
 *
 * Estas reglas deben coincidir con las validaciones server-side definidas en
 * los controladores (ClienteController, TrabajadorController, VehiculoController,
 * AutomotorController, MarcaController, CategoriaController, ServicioController,
 * ProductoController) y con el `docs/patron-validacion-formularios.md`.
 *
 * Se usa tanto en los seeders (para generar datos válidos desde el inicio) como
 * en el comando de data-cleaning (para corregir registros legados existentes).
 */
class DataNormalizer
{
    /**
     * Nombres alfanuméricos: letras, números, espacios simples (sin símbolos).
     */
    public const ALFANUMERICO_PATTERN = '/^[A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ]+(?: [A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ]+)*$/u';

    /**
     * Nombres solo letras: letras (con acentos y ñ), espacios simples (sin símbolos).
     */
    public const SOLO_LETRAS_PATTERN = '/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?: [A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/u';

    /**
     * Placa peruana: 6-7 caracteres alfanuméricos y opcional guion medio.
     */
    public const PLACA_PATTERN = '/^[A-Z0-9-]{6,7}$/i';

    /**
     * Sanitiza un campo de texto conservando solo caracteres permitidos.
     * Colapsa espacios múltiples y elimina espacios al inicio/fin.
     *
     * @param  string  $pattern  Regex de los caracteres permitidos (no negado).
     */
    public function sanitize(?string $value, string $pattern): ?string
    {
        $value ??= '';
        $clean = preg_replace('/[^A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ ]/', '', $value);
        $clean = preg_replace('/ {2,}/', ' ', (string) $clean);
        $clean = trim($clean);

        if ($clean === '') {
            return null;
        }

        // Si tras sanitizar no encaja con el patrón objetivo, devolvemos lo
        // sanitizado; de lo contrario, el valor tal cual fue sanitizado.
        return $clean;
    }

    /**
     * Normaliza un DNI a exactamente 8 dígitos, o null si no es válido.
     */
    public function normalizarDni(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return (strlen($digits) === 8) ? $digits : null;
    }

    /**
     * Normaliza un teléfono a exactamente 9 dígitos, o null si no es válido.
     */
    public function normalizarTelefono(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return (strlen($digits) === 9) ? $digits : null;
    }

    /**
     * Normaliza un nombre / apellido a "solo letras".
     * Elimina números y símbolos; colapsa espacios. Devuelve null si queda vacío.
     */
    public function normalizarNombre(?string $value): ?string
    {
        $value = preg_replace('/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]/', '', (string) $value);
        $value = preg_replace('/ {2,}/', ' ', (string) $value);
        $value = trim($value);

        return ($value === '') ? null : $value;
    }

    /**
     * Normaliza un campo alfanumérico (letras, números, espacios).
     * Devuelve null si queda vacío.
     */
    public function normalizarAlfanumerico(?string $value): ?string
    {
        return $this->sanitize($value, self::ALFANUMERICO_PATTERN);
    }

    /**
     * Normaliza una placa a mayúsculas y sin espacios, respetando el
     * formato estándar (6-7 alfanuméricos). Devuelve null si es inválida.
     */
    public function normalizarPlaca(?string $value): ?string
    {
        $placa = strtoupper((string) preg_replace('/[^A-Za-z0-9-]/', '', (string) $value));

        return (preg_match(self::PLACA_PATTERN, $placa)) ? $placa : null;
    }
}
