<?php

namespace EgoBytes\EgoDocs\Tests;

use EgoBytes\EgoDocs\Firmador;

class FirmadorTest extends TestCase
{
    public function test_firma_un_jwt_hs256_que_se_verifica_con_el_mismo_secreto(): void
    {
        $token = Firmador::firmar(['sub' => '42', 'nombre' => 'Ana Mora', 'roles' => ['contador']], 'sk_uno');

        $this->assertCount(3, explode('.', $token));
        $this->assertSame(['sub' => '42', 'nombre' => 'Ana Mora', 'roles' => ['contador']], $this->verificar($token, 'sk_uno'));
    }

    public function test_con_otro_secreto_no_se_verifica(): void
    {
        $token = Firmador::firmar(['sub' => '42'], 'sk_uno');
        [$cabecera, $carga, $firma] = explode('.', $token);

        $otra = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$cabecera}.{$carga}", 'sk_dos', true)), '+/', '-_'), '=');

        $this->assertNotSame($firma, $otra);
    }

    public function test_es_base64_para_url_sin_relleno(): void
    {
        // Caracteres que en base64 común darían + / o =.
        $token = Firmador::firmar(['sub' => '>>>???', 'nombre' => 'Ñandú ü'], 'sk');

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $token);
        $this->assertSame('Ñandú ü', $this->verificar($token, 'sk')['nombre']);
    }
}
