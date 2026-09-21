# REDCap Odontología

Instalación de [REDCap](https://project-redcap.org/) (v15.7.1) para el equipo de Odontología — plataforma para captura de datos de investigación (encuestas y bases de datos, con foco en cumplimiento HIPAA/GDPR).

## Stack

- Debian 13 (trixie)
- Apache2 + MariaDB 11.8.6 + PHP 8.4.24
- REDCap v15.7.1

## Estructura del repo

- `redcap_v15.7.1/` — código de la aplicación (distribución oficial de REDCap)
- `database.php.example` — plantilla de configuración de base de datos. **Copiar a `database.php` y completar con las credenciales reales** (ese archivo está en `.gitignore` y nunca debe commitearse).
- `.gitignore` — excluye `database.php`, `temp/` y logs

## Notas operativas

- El `redcap_base_url` configurado en la tabla `redcap_config` de la base de datos debe coincidir exactamente con el `DocumentRoot` de Apache — un desajuste ahí rompe la carga de CSS/JS (404 en cascada).
- Mail: pendiente de configurar un MTA/relay SMTP (ver Infraestructura relacionada abajo).

## Infraestructura relacionada

Este servidor forma parte de la infraestructura on-premise de Odontología. Ver el plan de implementación, red, virtualización y servicios de infraestructura en [**Infra-Odonto**](https://github.com/josetucan79/Infra-Odonto).
