# CLAUDE.md — proyecto-saas-tienda-virtual

> Reglas que **no se pueden inferir leyendo el código**. Si algo aquí contradice el código, **detente y pregúntale a Mateo** antes de asumir cuál es la fuente de verdad. Este proyecto reutiliza la arquitectura, las convenciones y la seguridad de un proyecto hermano (un SaaS de reservas para spas) que está copiado SOLO PARA LECTURA en `_hermano/`. Esa carpeta se borra al terminar la Fase 0.

## 1. El proyecto en una frase

Plataforma SaaS multi-negocio de tienda/catálogo para comercios locales (ropa, calzado, en general cualquier comercio pequeño): cada negocio administra sus productos (categorías, variantes, imágenes, stock) y tiene una página pública propia por slug donde sus clientes ven el catálogo y hacen el pedido por WhatsApp. Modelo freemium: núcleo gratis, funciones de pago activables por negocio.

**Decisiones ya tomadas por Mateo (no reabrir):**
- Roles del negocio: **admin** (dueño) y **vendedor** (acceso limitado). Más el **super_admin** de la plataforma (uno solo, `tenant_id` nulo).
- Clientes de la tienda pública: **solo compradores anónimos por WhatsApp**, sin cuenta ni login de cliente. No se porta ni se construye autenticación de clientes.
- El pedido público nace como **solicitud pendiente** en el panel (mismo patrón que las solicitudes públicas del hermano) y se envía por WhatsApp.
- Nombre de marca provisional: "Tienda Virtual" (se cambia cuando Mateo lo decida).

## 2. Stack (no cambiar sin aprobación explícita de Mateo)

- Backend: Laravel 13, PHP 8.4, MySQL 8.4 vía Laragon en Windows. Composer 2.x.
- Frontend: Blade + Bootstrap 5 + jQuery + Axios (vía `axiosSipleInterno()`) + DataTables + SweetAlert2 (solo confirmaciones) + toasts propios (`notificarUsuario`) + Shepherd.js + Chart.js + Bootstrap Icons + Google Fonts. Todo por CDN con versión fija, sin build tools. **JAMÁS Tailwind. JAMÁS React, Vue ni Angular. Nada de Node, npm, Vite ni Webpack. Tampoco Livewire, Inertia ni Alpine salvo aprobación explícita de Mateo.**
- Cualquier librería nueva (Composer o CDN) se propone a Mateo en 2 o 3 líneas (qué hace, por qué aquí, alternativa, compatibilidad con Laravel 13 y PHP 8.4) y se incluye solo si dice que sí. Al instalar: versión estable más reciente y adaptar el código a la API realmente instalada. Al cerrar un trabajo con paquetes nuevos: `composer audit`.
- Correo: Mailpit en desarrollo (127.0.0.1:1025, bandeja en localhost:8025). Todos los Mailables implementan `ShouldQueue` y requieren `php artisan queue:work`. Si cambia la firma de un Mailable o Job, reiniciar el worker.
- Colas: `QUEUE_CONNECTION=database`. Scheduler en `routes/console.php`.
- Imágenes: Intervention Image v4 (driver GD). Excel: maatwebsite/excel.

## 3. Arquitectura de capas (orden obligatorio por módulo)

1. Migración → 2. Model → 3. Service (prefijo `Svc`) → 4. Controller tipo Request (JSON, rutas `request/...`) → 5. Controller tipo Vista (Blade, rutas `backoffice/...`) → 6. Vista Blade.

Backend completo primero, frontend después. No mezclar módulos.

### Model
- `namespace App\Models`, `$table` y `$primaryKey` explícitos (la PK nunca es `id`, siempre `id_<tabla>`).
- `const CREATED_AT = null; const UPDATED_AT = null;` (timestamps manuales).
- `$fillable` explícito, incluida la PK y las columnas de auditoría (`usuario_registra`, `fecha_registro`, `estado`). `use HasFactory;`.
- Propiedades explícitas (sin los atributos nuevos de Laravel 13 para `$table` y `$fillable`).

### Service
- `namespace App\Service`, clase `Svc` + Entidad.
- Cada método en try/catch; en el catch `Log::channel("database")->info($e);` y retorno del valor vacío (`false`, `[]`, `null`).
- `listar()` y `listarById()` con `select()` explícito (nunca `select *`), siempre `->toArray() ?? []`.
- `crear($info)` retorna el id insertado o `false`. `editar($id, $info, ...)` retorna bool (excepción documentada: operaciones críticas pueden devolver constantes de resultado).
- Joins con el query builder, alias cortos y `AS` explícito.
- Toda la lógica de negocio vive aquí, nunca en el controller.
- Incrementos y decrementos con `increment()`, `decrement()` o expresión SQL: **nunca leer y reescribir** (condición de carrera, sobre todo con stock).
- Las operaciones multi-paso van en transacción; los bloqueos de fila (`lockForUpdate`) donde haya concurrencia.

### Regla multi-tenant (no negociable, la más importante)
- Todo método de Service que toque una tabla con `tenant_id` lo recibe como **parámetro explícito**; nunca lo lee de `session()`.
- El Controller extrae `session('tenant_id')` y se lo pasa. Si es `null` (super admin), el Service no filtra; si trae valor, filtra siempre.
- El `WHERE` de `editar()` y `eliminar()` SIEMPRE incluye `tenant_id` además del id del registro.
- En `crear()`, si la sesión tiene `tenant_id`, se **fuerza** ese valor e se ignora cualquier `tenant_id` que venga del formulario, del request o de un archivo importado.
- Excepciones documentadas: procesos de sistema sin sesión (comandos del Scheduler) y la consulta agregada de plataforma que devuelve solo un número.
- Un negocio nunca puede ver, ni deducir, datos de otro. Cada endpoint se prueba con "el admin de A pide un id de B".

### Controller base (abstracto, `App\Http\Controllers\Controller`)
- `$respuesta = ["error" => 1, "mensaje" => "", "data" => []]`.
- `$this->request` y `$this->requestData` se refrescan **en cada petición desde `callAction()`**, no solo en el constructor.
- Métodos: `setRequestValidationRules(array $rules, array $messages = [])`, `validateRequestRules()`, `agregarError($mensaje)`, `agregarErrorSistema(string $codigo)`, `agregarErrorNoDisponible(string $registro, string $codigo)`, `respSinError()`, `existeErrores()`, `setDataResponse($valor, $nombreVar = "")`, `setDataLog(...)`, `sendResponse()`. El código del hermano es la fuente de verdad; se porta tal cual.

### Controller tipo Request y tipo Vista
- Request: constructor con Services inyectados (`private` y tipados), `parent::__construct()` primero. Flujo: validar → `getRequestData()` → armar array limpio → Service → si falla `agregarError()` + `sendResponse()` → si OK `respSinError()` + `sendResponse()`. Rutas `request/modulo/...`.
- Vista: arma `$templateView["clave"]` y retorna `view("app.nombre_vista", $templateView)`. Nunca JSON. Rutas `backoffice/...`.

## 4. Reglas del frontend (absolutas)

- JAMÁS `method=""` en la etiqueta `<form>`. JAMÁS rutas con nombre ni la función `route()`: las URLs son cadenas literales con `url('backoffice/...')` o `url('request/...')`.
- Todos los botones son `type="button"`. Ninguna acción usa submit nativo.
- Toda interacción con el backend por JS usa `axiosSipleInterno(metodo, url, parametros, cuerpo, mostrarLoader, callback, extraOptions)`. Nunca `fetch` nativo ni `$.ajax` directo (excepciones justificadas: librerías que exigen el suyo, o subida de archivos con FormData).
- Los `name=""` de los `<input>` hijos sí se usan (los necesita `getDataJson()`). La prohibición es sobre el `name` y el `method` de la etiqueta `<form>`.
- Un checkbox de estado que puede desmarcarse va **sin `name`**, y su valor 0/1 se inyecta explícitamente antes de enviar.
- **Escape de texto:** todo dato de usuario se pinta escapado. En Blade `{{ }}`, nunca `{!! !!}`. En JS, `textContent` o `escaparTexto()`; en DataTables `renderTextoSeguro`; `notificarUsuario()` muestra el mensaje como texto. No leer con `.data()` el texto libre (jQuery lo convierte en número u objeto): usar `.attr('data-...')`. Nunca insertar con `innerHTML` ni `.html(` datos de usuario, de la IA o del servidor.
- **Exportaciones a Excel:** extienden la clase que neutraliza los textos que empiezan con `= + - @`.
- **Avisos:** `notificarUsuario()` muestra toast (5 s, errores 8 s). SweetAlert2 solo para confirmaciones o decisiones. Ningún `Swal.fire` informativo.
- Utilidades globales (su código vive en `public/js/utilidades.js`, es la fuente de verdad): `axiosSipleInterno`, `getDataJson`, `notificarUsuario`, `escaparTexto`, `renderTextoSeguro`, `system_validarcampos`, `Mostrarloader()`/`Ocultarloader()`, `colorVariable(nombre)`, `generarAvatar(nombre)`. Antes de escribir una vista que las use, comprueba que ese archivo ya está en el proyecto.
- Accesibilidad: objetivos táctiles mínimos de 44 px, foco visible, contraste mínimo 4,5:1, `aria-label` en botones de ícono, sin animación con `prefers-reduced-motion`.

## 5. Sistema de diseño y temas

- TODO color vive en variables CSS (`--accent`, `--accent-hover`, `--accent-soft`, `--bg-*`, `--text-*`, `--border-color`, `--danger`, `--success`, `--warning`) o en `color-mix(in srgb, var(--accent) N%, transparent)`. **Jamás hexadecimales sueltos** en las vistas: solo dentro de `:root` y de los bloques de tema.
- Dos capas combinables en `<body class="modo-{claro|oscuro} acento-{nombre}">`. Un acento nuevo es un bloque de 3 variables; un modo nuevo, un bloque de las demás.
- Antes de cerrar un módulo visual: auditar con grep que no queden hexadecimales sueltos y verificar el contraste real en ambos modos.
- La paleta por defecto del panel es cálida (crema/terracota), estilo acordado con Mateo. La página pública de cada negocio usa su propia marca (logo y color por negocio); eso se diseña aparte.
- Kit de formularios reutilizable (no reinventar): `.modal-moderno`, `.modal-header-moderno` + `.insignia-encabezado`, `.tarjeta-seccion-form` + `.etiqueta-seccion-form`, `.campo-flotante` (placeholder `" "` y el `<label>` va DESPUÉS del campo), `.con-error` + `.mensaje-error-campo`, `.stepper-campo` + `.btn-stepper[data-paso]`, `.interruptor-moderno` + `.pista-interruptor`, `.btn-guardar-moderno` (modificadores `.ocupado` y `.exito`).

## 6. Patrón de reactivación (activo/inactivo)

- `Svc*::listar($tenantId, $incluirInactivos = false)` filtra `estado = 1` salvo que se pida lo contrario.
- El interruptor de estado solo aparece en modo edición (en creación, `crear()` fuerza `estado = 1`).
- Toggle "Mostrar inactivos" en la tabla, con el mismo texto y ubicación en todos los módulos.
- Desactivar un registro no borra datos. Las cascadas de estado entre entidades vinculadas escriben con `update()` directo (nunca invocando el `editar()` del otro Service) y solo se disparan en la transición 1 → 0.

## 7. Zona pública (tienda por slug)

- Slug único GLOBAL por negocio, generado al crearlo, que **nunca** se regenera solo al cambiar el nombre.
- Rutas y controller completamente separados del backoffice, SIN sesión. Todo método que exponga datos usa **lista blanca de campos** (nunca `select('*')` ni `toArray()` del modelo completo). Hay pruebas por mutación que lo verifican.
- Negocio inexistente, suspendido o sin datos: 404 idéntico. Productos ocultos o inactivos no aparecen ni por URL directa.
- Pedidos desde la zona pública son **solicitudes**: nacen pendientes, con origen público. Límite de peticiones y campo trampa (honeypot); CSRF excluido solo en esa ruta. Los totales se recalculan en el servidor y se guarda una copia (snapshot) del nombre, la variante y el precio.
- El mensaje de WhatsApp se codifica con `encodeURIComponent` y el número se normaliza a solo dígitos.
- Descripciones de producto en texto plano (saltos de línea permitidos), sin HTML.
- Respuestas idénticas exista o no el dato (sin enumeración).

## 8. Módulos, planes y límites

- Funciones de pago = módulos en `modulos_plataforma` y por negocio en `negocio_modulos` (`activo`, `vigente_hasta` inclusive, `origen`, `nota`). `SvcModulo::estaActivo` incluye la vigencia en tiempo real; todos los consumidores deciden con él.
- Toda ruta de un módulo lleva `verificar.modulo:<clave>`, tanto `backoffice/*` como `request/*`. Las vistas de un módulo apagado redirigen a una pantalla de "no disponible" con candado y el contacto; los endpoints responden el error JSON de siempre. El super admin nunca queda bloqueado.
- Apagar o vencer un módulo **nunca borra datos**. Lo que depende del módulo se apaga con él.
- Origen y nota son **privados del super admin**. Origen `cortesia` o `pagado_fuera` (seleccionables); `plan` y `pasarela` reservados.
- Los **límites de plan** se aplican en TODOS los puntos de entrada (pantalla, carga masiva, reactivación). Al bajar de plan no se borra nada: se bloquea crear más.
- Cobro manual primero. Libro de pagos interno del super admin con consecutivo atómico y comprobante que dice "no reemplaza una factura". Nada de pasarela hasta decidirlo con Mateo (y consulta contable previa).
- Los módulos reales de la tienda se definen más adelante (Fase 5). El catálogo de módulos del hermano (comisiones, chatbot, ia) NO se siembra.

## 9. Seguridad y autenticación

- Sesión manual de Laravel (`session()`), NUNCA `Auth::` ni el middleware `auth`. Claves de sesión: `id_usuario`, `usuario`, `nombre_usuario`, `email`, `tenant_id`, `id_rol`, `nombre_negocio_sesion`, `modo_tema`, `color_acento`, `app_sesion`, `version_sesion` (copia, al iniciar sesión, de `usuarios.version_sesion`; si no coincide, se corta la sesión, para cerrar todas las sesiones al cambiar la clave).
- Contraseñas con `Hash::make()` y `Hash::check()`, mínimo 8 caracteres.
- Middleware `sesion.activa` (VerificarSesion) y el middleware que restringe al rol vendedor: bloquean **tanto vistas `backoffice/*` como endpoints `request/*`**. **Todas las rutas privadas `request/*` llevan `sesion.activa`** (hay un guardián de pruebas). Cortan la sesión si el negocio o el usuario están desactivados.
- Email y `usuario` únicos GLOBALMENTE, pero un usuario inactivo libera su correo y usuario (columnas generadas con índice único condicional en la base).
- **Roles: `admin` (dueño del negocio), `vendedor` (acceso limitado) y `super_admin` (`tenant_id` nulo).** El rol vendedor reemplaza al "empleado" del hermano y NO está atado a citas ni a una tabla de empleados del dominio de spa. **Una sola cuenta super admin**, garantizada con índice único en la base. El `id_rol` al crear o editar usuarios sale de una **lista blanca** (admin y vendedor para un admin de negocio). El panel de plataforma exige rol super_admin **y** ausencia de negocio en la sesión. Nadie puede crear, cambiar ni dar de baja la cuenta super admin.
- Login con límite de intentos por correo+IP y por IP, ID de sesión nuevo al entrar, sesión invalidada al salir, y misma respuesta y mismo tiempo de respuesta exista o no el correo.
- Registro en dos pasos: nada se crea hasta confirmar el correo. Recuperación de clave por código de 6 dígitos hasheado, vigente 1 hora, de un solo uso, con límite de intentos, y al usarlo se cierran las sesiones.
- Subida de archivos: tipo real con `getimagesize`, límite de peso y de píxeles, recodificación (elimina EXIF), nombres aleatorios, carpeta por negocio, nada ejecutable en la carpeta pública.
- NO hay autenticación de clientes de la tienda: los compradores son anónimos por WhatsApp.

## 10. Pruebas automatizadas (obligatorio, sin excepción)

- Módulo NUEVO: pruebas PHPUnit completas, incluido el **aislamiento entre negocios con prueba de mutación** (romper el filtro a propósito, confirmar que la prueba lo detecta, restaurar, confirmar que vuelve a pasar; documentar el resultado real en el test).
- Módulo EXISTENTE que se modifica: correr la **suite completa**, no solo la del módulo.
- Módulo viejo sin su prueba de mutación de tenant: agregarla antes de seguir.
- Pruebas que recorren el camino real (HTTP con `withSession()`, archivos reales para importaciones) en vez de mocks de capas intermedias. SQLite en memoria; las migraciones con columnas generadas o índices especiales se verifican además contra MySQL real.
- **Reloj congelado** con `Carbon::setTestNow` para todo lo que toque fechas. Prohibido `date()` nativo en código que decide "hoy". No buscar cadenas cortas en cuerpos que incluyan valores aleatorios.
- **Guardianes:** toda ruta privada `request/*` exige sesión; ningún `{!! !!}` fuera de una lista blanca documentada; ninguna columna de DataTables sin render seguro; cero hexadecimales sueltos; ningún `Swal.fire` informativo; el JS nuevo sin `innerHTML`, `.html(`, `insertAdjacentHTML` ni `eval`.
- **XSS en cada pantalla nueva:** probar con `<img src=x onerror=alert(1)>`, comillas, y un nombre con apóstrofe (O'Brien).
- Ninguna prueba llama a APIs reales ni envía correos reales (`Mail::fake`, `Http::preventStrayRequests`).
- Matriz de permisos por endpoint: anónimo, vendedor, admin de otro negocio y super admin.
- Si una prueba de seguridad pasa a la primera, se muta antes de confiar en ella.
- Ante un bug que persiste tras un primer intento: el siguiente intento exige **diagnóstico de causa raíz**, no otro parche.
- Verificación en navegador con Playwright cuando haya interfaz: datos de prueba que se retiran, sin tocar `storage/logs`, sin usar la cuenta super admin real, y que el reporte diga con franqueza qué no se pudo verificar.

## 11. Flujo de Git

- El nombre del remoto del proyecto cambia según el PC: verificar con `git remote -v` antes de cualquier push y descartar el que apunte al repo base de Laravel.
- Commits con mensaje descriptivo en español **sin tildes ni eñes**, uno por bloque o tema.
- El **push lo hace Mateo**, con confirmación suya: nunca automático.
- Nunca `--force`, `--amend` sobre commits ya publicados, ni `git stash` (ni `git checkout --` ni `git restore` sobre archivos con cambios sin commitear) sin preguntar antes a Mateo.
- El `.env` nunca se versiona ni se imprime. La carpeta `_hermano/` está en `.gitignore` y se borra al terminar la Fase 0.
- Nunca borrar ni editar archivos de `storage/logs` sin permiso.

## 12. Al terminar cada tarea, reportar siempre

- Resultado de la investigación y cualquier diferencia con lo pedido.
- Archivos creados y modificados; migraciones y paquetes nuevos.
- Antes de migrar: `php artisan db:show` (Laragon a veces deja MySQL apagado).
- Resultado de PHPUnit: filtro específico y suite completa (total antes y después), cada mutación con su resultado real.
- Cuando se toquen datos sensibles (aislamiento, permisos, dinero, archivos subidos): confirmación **explícita con evidencia**, no "creo que está bien".
- Qué no se pudo verificar y por qué.
- No hacer commit salvo que el prompt lo pida; si lo pide, el mensaje en español sin tildes y `git status` al final.