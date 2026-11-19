#!/usr/bin/env bash
# Ruta sugerida en ALKIRA: backend/pruebas/probarIntegracionRailway.sh
# Pruebas de solo lectura, sin mostrar contraseñas, cookies ni tokens.
set -u
set -o pipefail

URL_BASE="${1:-https://api-php-production-0578dc22.up.railway.app}"
URL_BASE="${URL_BASE%/}"
umask 077
directorioTemporal="$(mktemp -d)" || exit 1
trap 'rm -rf "$directorioTemporal"; unset contrasena token' EXIT

correctas=0
fallidas=0
omitidas=0
correo=''
contrasena=''
token=''

probarPeticion() {
    local nombrePrueba="$1"
    local codigoEsperado="$2"
    local archivoRespuesta="$3"
    shift 3
    local codigoHttp
    codigoHttp="$(curl --silent --show-error --connect-timeout 10 --max-time 30 \
        --output "$archivoRespuesta" --write-out '%{http_code}' "$@")"
    if [[ "$codigoHttp" == "$codigoEsperado" ]]; then
        printf 'OK    %-43s HTTP %s\n' "$nombrePrueba" "$codigoHttp"
        ((correctas+=1))
        return 0
    fi
    printf 'FALLÓ %-43s HTTP %s (esperado %s)\n' \
        "$nombrePrueba" "${codigoHttp:-sin respuesta}" "$codigoEsperado"
    if [[ -f "$archivoRespuesta" ]]; then
        python3 - "$archivoRespuesta" <<'PY'
import json, sys
try:
    with open(sys.argv[1], encoding='utf-8') as archivo:
        respuesta = json.load(archivo)
    mensaje = respuesta.get('mensaje', 'Sin mensaje JSON') if isinstance(respuesta, dict) else 'Respuesta inesperada'
    print('      Mensaje:', str(mensaje)[:160])
except (OSError, ValueError, UnicodeError):
    print('      No se pudo interpretar la respuesta como JSON')
PY
    fi
    ((fallidas+=1))
    return 1
}

solicitarCredenciales() {
    local nombreRol="$1"
    echo
    read -r -p "Correo de $nombreRol (Enter para omitir): " correo
    if [[ -z "$correo" ]]; then
        echo "OMITIDA: sesión de $nombreRol"
        ((omitidas+=1))
        return 1
    fi
    read -r -s -p "Contraseña de $nombreRol (no se mostrará): " contrasena
    printf '\n'
    return 0
}

iniciarSesion() {
    local nombreRol="$1"
    local rutaLogin="$2"
    local archivoRespuesta="$3"
    shift 3
    local codigoHttp
    codigoHttp="$(printf '%s\n%s\n' "$correo" "$contrasena" \
        | python3 -c 'import json,sys; correo=sys.stdin.readline().rstrip("\n"); clave=sys.stdin.readline().rstrip("\n"); print(json.dumps({"correo":correo,"contrasena":clave}))' \
        | curl --silent --show-error --connect-timeout 10 --max-time 30 \
            --request POST --header 'Content-Type: application/json' \
            --data-binary @- --output "$archivoRespuesta" --write-out '%{http_code}' \
            "$@" "$URL_BASE$rutaLogin")"
    contrasena=''
    if [[ "$codigoHttp" == 200 ]]; then
        printf 'OK    %-43s HTTP 200\n' "Login de $nombreRol"
        ((correctas+=1))
        return 0
    fi
    printf 'FALLÓ Login de %-34s HTTP %s\n' "$nombreRol" "${codigoHttp:-sin respuesta}"
    ((fallidas+=1))
    return 1
}

validarRol() {
    local archivoRespuesta="$1"
    local rolEsperado="$2"
    python3 - "$archivoRespuesta" "$rolEsperado" <<'PY'
import json, sys
try:
    with open(sys.argv[1], encoding='utf-8') as archivo:
        respuesta = json.load(archivo)
    usuario = respuesta.get('usuario', {})
    if usuario.get('tipoUsuario') != sys.argv[2] or not isinstance(usuario.get('idUsuario'), int):
        raise ValueError('El rol o la identidad del login no coincide')
except (OSError, ValueError, KeyError, TypeError) as error:
    print('FALLÓ Validación del rol:', error)
    sys.exit(1)
PY
}

extraerToken() {
    python3 - "$1" <<'PY'
import json, sys
with open(sys.argv[1], encoding='utf-8') as archivo:
    respuesta = json.load(archivo)
token = respuesta.get('token', '')
if not isinstance(token, str) or len(token.split('.')) != 3:
    sys.exit(1)
print(token)
PY
}

resumenColeccion() {
    local archivoRespuesta="$1"
    local campo="$2"
    python3 - "$archivoRespuesta" "$campo" <<'PY'
import json,sys
try:
    with open(sys.argv[1], encoding='utf-8') as archivo:
        datos=json.load(archivo)
    registros=datos.get(sys.argv[2], None)
    if isinstance(registros, list):
        print('      Registros devueltos:', len(registros))
except (OSError,ValueError,AttributeError):
    pass
PY
}

probarHistorialTicket() {
    local archivoTickets="$1"
    local tokenMovil="$2"
    local idTicket
    idTicket="$(python3 - "$archivoTickets" <<'PY'
import json,sys
try:
    with open(sys.argv[1], encoding='utf-8') as archivo:
        tickets=json.load(archivo).get('tickets', [])
    if tickets and isinstance(tickets[0].get('idTicket'), int):
        print(tickets[0]['idTicket'])
except (OSError,ValueError,AttributeError,TypeError):
    pass
PY
)"
    if [[ -n "$idTicket" ]]; then
        probarPeticion 'HU06 - Historial de un ticket propio' 200 \
            "$directorioTemporal/historial-ticket.json" \
            --header "Authorization: Bearer $tokenMovil" \
            "$URL_BASE/api/tickets/$idTicket/historial"
    else
        echo 'OMITIDA: historial (no hay tickets propios con ID)'
        ((omitidas+=1))
    fi
}

echo '=== ALKIRA | Pruebas Railway HU02 / HU06 ==='
echo 'Solo GET y POST de inicio de sesión. No se modifican datos de negocio.'
echo "Destino: $URL_BASE"

probarPeticion 'Salud de API' 200 "$directorioTemporal/salud.json" "$URL_BASE/api/salud"
probarPeticion 'HU02 sin JWT bloqueado' 401 "$directorioTemporal/adeudos-anonimo.json" "$URL_BASE/api/adeudos/pendientes"
probarPeticion 'HU06 sin JWT bloqueado' 401 "$directorioTemporal/tickets-anonimo.json" "$URL_BASE/api/tickets"

if solicitarCredenciales 'administrador web'; then
    if iniciarSesion 'administrador web' '/api/auth/login' \
        "$directorioTemporal/login-web.json" \
        --cookie-jar "$directorioTemporal/cookies-web.txt"; then
        if validarRol "$directorioTemporal/login-web.json" administrador; then
            probarPeticion 'Web - Validar cookie de sesión' 200 "$directorioTemporal/yo-web.json" \
                --cookie "$directorioTemporal/cookies-web.txt" "$URL_BASE/api/auth/yo"
            probarPeticion 'HU02.4 - Contratos administrativos' 200 "$directorioTemporal/contratos-admin.json" \
                --cookie "$directorioTemporal/cookies-web.txt" "$URL_BASE/api/administracion/contratos"
            probarPeticion 'HU02.3 - Reglas de cobro' 200 "$directorioTemporal/reglas-admin.json" \
                --cookie "$directorioTemporal/cookies-web.txt" "$URL_BASE/api/administracion/reglas-cobro"
            probarPeticion 'HU06.3 - Tickets administrativos' 200 "$directorioTemporal/tickets-admin.json" \
                --cookie "$directorioTemporal/cookies-web.txt" "$URL_BASE/api/mantenimiento/tickets"
            probarPeticion 'HU06.3 - Técnicos disponibles' 200 "$directorioTemporal/tecnicos-admin.json" \
                --cookie "$directorioTemporal/cookies-web.txt" "$URL_BASE/api/mantenimiento/tecnicos"
        else
            ((fallidas+=1))
        fi
    fi
fi

if solicitarCredenciales 'inquilino móvil'; then
    if iniciarSesion 'inquilino móvil' '/api/auth/login-movil' \
        "$directorioTemporal/login-inquilino.json"; then
        if validarRol "$directorioTemporal/login-inquilino.json" inquilino \
           && token="$(extraerToken "$directorioTemporal/login-inquilino.json")"; then
            probarPeticion 'Móvil - Validar JWT de inquilino' 200 "$directorioTemporal/yo-inquilino.json" \
                --header "Authorization: Bearer $token" "$URL_BASE/api/auth/yo-movil"
            if probarPeticion 'HU02.1 - Adeudos del inquilino' 200 "$directorioTemporal/adeudos.json" \
                --header "Authorization: Bearer $token" "$URL_BASE/api/adeudos/pendientes"; then
                resumenColeccion "$directorioTemporal/adeudos.json" adeudos
            fi
            probarPeticion 'HU02.2 - Historial de pagos' 200 "$directorioTemporal/pagos.json" \
                --header "Authorization: Bearer $token" "$URL_BASE/api/pagos/historial"
            probarPeticion 'HU02.2 - Contratos propios' 200 "$directorioTemporal/contratos.json" \
                --header "Authorization: Bearer $token" "$URL_BASE/api/contratos"
            if probarPeticion 'HU06.1 - Tickets del inquilino' 200 "$directorioTemporal/tickets-inquilino.json" \
                --header "Authorization: Bearer $token" "$URL_BASE/api/tickets"; then
                resumenColeccion "$directorioTemporal/tickets-inquilino.json" tickets
                probarHistorialTicket "$directorioTemporal/tickets-inquilino.json" "$token"
            fi
        else
            echo 'FALLÓ: JWT o rol de inquilino incorrecto'
            ((fallidas+=1))
        fi
        token=''
    fi
fi

if solicitarCredenciales 'técnico móvil'; then
    if iniciarSesion 'técnico móvil' '/api/auth/login-movil' \
        "$directorioTemporal/login-tecnico.json"; then
        if validarRol "$directorioTemporal/login-tecnico.json" tecnico \
           && token="$(extraerToken "$directorioTemporal/login-tecnico.json")"; then
            probarPeticion 'Móvil - Validar JWT de técnico' 200 "$directorioTemporal/yo-tecnico.json" \
                --header "Authorization: Bearer $token" "$URL_BASE/api/auth/yo-movil"
            if probarPeticion 'HU06.1 - Tickets del técnico' 200 "$directorioTemporal/tickets-tecnico.json" \
                --header "Authorization: Bearer $token" "$URL_BASE/api/tickets"; then
                resumenColeccion "$directorioTemporal/tickets-tecnico.json" tickets
                probarHistorialTicket "$directorioTemporal/tickets-tecnico.json" "$token"
            fi
            probarPeticion 'HU02 - Técnico sin permiso para adeudos' 403 \
                "$directorioTemporal/adeudos-tecnico.json" \
                --header "Authorization: Bearer $token" "$URL_BASE/api/adeudos/pendientes"
        else
            echo 'FALLÓ: JWT o rol de técnico incorrecto'
            ((fallidas+=1))
        fi
        token=''
    fi
fi

printf '\nRESULTADO: %d correctas, %d fallidas, %d omitidas\n' \
    "$correctas" "$fallidas" "$omitidas"
if ((fallidas > 0)); then
    exit 1
fi
if ((omitidas > 0)); then
    echo 'Aviso: las pruebas omitidas no se consideran verificadas.'
fi
