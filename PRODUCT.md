# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Director de Escuela, Secretaría Académica, empleadores y egresados de la Escuela de Ingeniería Informática.

## Product Purpose

CRM y Sistema de Información para el Seguimiento al Egresado (SISOPE). Permite registrar el padrón oficial de egresados y promociones, convocar campañas de seguimiento por enlace personal, recoger encuestas a egresados y empleadores, y calcular indicadores de inserción laboral (I1 a I6) y formatos institucionales (F1 a F11).

## Operating Context

El sistema funciona de forma independiente. La portada general (`/` y `/seguimiento-egresado`) actúa como centro del CRM:
- El personal institucional (Dirección y Secretaría) inicia sesión para acceder al padrón, campañas, encuestas, indicadores y evaluación institucional.
- Los egresados acceden directamente a través del enlace personal y seguro de su invitación (`/seguimiento-egresado/acceso/{token}`), sin necesidad de cuenta.
- Los empleadores acceden a su encuesta a través de enlaces con token seguro (`/seguimiento-egresado/encuesta-empresa/{token}`).

## Capabilities and Constraints

- Laravel 13, Blade, Livewire, Alpine.js y Tailwind CSS.
- PostgreSQL 17 con esquema `seguimiento_egresado` y tablas compartidas de infraestructura.
- Autenticación con Laravel Breeze y `spatie/laravel-permission` (roles `director_escuela` y `secretaria`).
- Rutas del CRM bajo `/seguimiento-egresado`.

## Brand Commitments

Identidad visual institucional de la Escuela de Ingeniería Informática (UNT): azul marino (`#17224d`), verde acento (`#09613f`), tipografías Fira Sans y Fira Code.

## Product Principles

- El CRM es la aplicación principal: flujo de trabajo directo sin selectores de otros módulos.
- Acceso seguro sin contraseñas para egresados: enlace personal tokenizado con vigencia configurable.
- Fidelidad de datos y trazabilidad: una operación, una transacción, con historial de estados académicos y mediciones inmutables.
