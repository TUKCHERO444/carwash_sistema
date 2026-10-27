<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordSegura implements ValidationRule
{
    private const MIN_LENGTH = 12;

    private const MAX_LENGTH = 64;

    private const SIMBOLOS = '!@#$%^&*()-_=+[]{};:,.?/';

    private const CLAVES_COMUNES = [
        '123456',
        '1234567',
        '12345678',
        '123456789',
        '1234567890',
        'abc123',
        'admin',
        'admin123',
        'administrador',
        'bienvenido',
        'carwash',
        'carwash123',
        'carwash2026',
        'carwashcarwash',
        'chinito',
        'chinito123',
        'iloveyou',
        'lavado',
        'lavado123',
        'letmein',
        'password',
        'password1',
        'password12',
        'password123',
        'password1234',
        'qwerty',
        'qwerty123',
        'welcome',
    ];

    public function __construct(
        private ?User $user = null,
        private ?string $name = null,
        private ?string $email = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('La contraseña debe ser texto.');

            return;
        }

        $longitud = mb_strlen($value);

        if ($longitud < self::MIN_LENGTH) {
            $fail('La contraseña debe tener al menos 12 caracteres.');

            return;
        }

        if ($longitud > self::MAX_LENGTH) {
            $fail('La contraseña no puede tener más de 64 caracteres.');

            return;
        }

        if (! preg_match('/\p{Lu}/u', $value)) {
            $fail('La contraseña debe incluir al menos una mayúscula.');

            return;
        }

        if (! preg_match('/\p{Ll}/u', $value)) {
            $fail('La contraseña debe incluir al menos una minúscula.');

            return;
        }

        if (! preg_match('/\p{N}/u', $value)) {
            $fail('La contraseña debe incluir al menos un número.');

            return;
        }

        if (! preg_match('/['.preg_quote(self::SIMBOLOS, '/').']/u', $value)) {
            $fail('La contraseña debe incluir al menos un símbolo (!@#$%^&*).');

            return;
        }

        $normalizada = $this->normalizar($value);

        if ($this->esClaveComun($normalizada)) {
            $fail('La contraseña es demasiado común. Elige una diferente.');

            return;
        }

        if ($this->contieneDatoPersonal($normalizada)) {
            $fail('La contraseña no puede contener tu nombre, correo o documento.');

            return;
        }

        if ($this->esPasswordActual($value)) {
            $fail('La nueva contraseña debe ser diferente a la actual.');
        }
    }

    private function normalizar(string $valor): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($valor))) ?? '';
    }

    private function esClaveComun(string $normalizada): bool
    {
        if ($normalizada === '') {
            return false;
        }

        foreach (['password', 'carwash', 'chinito', 'lavado'] as $termino) {
            if (str_contains($normalizada, $termino)) {
                return true;
            }
        }

        $sinSufijoNumerico = rtrim($normalizada, '0123456789');

        return in_array($normalizada, self::CLAVES_COMUNES, true)
            || in_array($sinSufijoNumerico, self::CLAVES_COMUNES, true);
    }

    private function contieneDatoPersonal(string $normalizada): bool
    {
        $tokens = [];

        if (is_string($this->name) && $this->name !== '') {
            $nombreCompleto = $this->normalizar($this->name);

            if (mb_strlen($nombreCompleto) >= 3) {
                $tokens[] = $nombreCompleto;
            }

            foreach (preg_split('/\s+/u', $this->name) ?: [] as $parte) {
                $parteNormalizada = $this->normalizar($parte);

                if (mb_strlen($parteNormalizada) >= 3) {
                    $tokens[] = $parteNormalizada;
                }
            }
        }

        if (is_string($this->email) && $this->email !== '') {
            $emailCompleto = $this->normalizar($this->email);
            $emailLocal = $this->normalizar(Str::before($this->email, '@'));

            if (mb_strlen($emailCompleto) >= 3) {
                $tokens[] = $emailCompleto;
            }

            if (mb_strlen($emailLocal) >= 3) {
                $tokens[] = $emailLocal;
            }
        }

        foreach (array_unique($tokens) as $token) {
            if ($token !== '' && str_contains($normalizada, $token)) {
                return true;
            }
        }

        return false;
    }

    private function esPasswordActual(string $value): bool
    {
        if ($this->user === null || ! is_string($this->user->password) || $this->user->password === '') {
            return false;
        }

        return Hash::check($value, $this->user->password);
    }
}
