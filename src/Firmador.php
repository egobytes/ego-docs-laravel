<?php

namespace EgoBytes\EgoDocs;

/**
 * Firma el token de identidad: un JWT con HS256 y el secreto del widget.
 *
 * Sin librería a propósito: es una sola combinación de algoritmo, y así el
 * paquete no arrastra dependencias a cada sistema que lo instale.
 */
class Firmador
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public static function firmar(array $datos, string $secreto): string
    {
        $cabecera = self::base64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $carga = self::base64(json_encode($datos, JSON_UNESCAPED_UNICODE));
        $firma = self::base64(hash_hmac('sha256', "{$cabecera}.{$carga}", $secreto, true));

        return "{$cabecera}.{$carga}.{$firma}";
    }

    protected static function base64(string $binario): string
    {
        return rtrim(strtr(base64_encode($binario), '+/', '-_'), '=');
    }
}
