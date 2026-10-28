#!/usr/bin/env python3
"""Renombra el paquete de la aplicación y la pantalla del estado de cuenta.

Ejecutar desde la raíz del monorepo:
    python3 movil/scripts/renombrarProyecto.py

Conservar los cambios en una rama de trabajo antes de ejecutar.
"""
from pathlib import Path
import re
import sys

CARPETA_MOVIL = Path(__file__).resolve().parents[1]
CARPETA_APP = CARPETA_MOVIL / "app"
PAQUETE_ANTERIOR = "com.example.pantallasmovil"
PAQUETE_NUEVO = "com.alkira.movil"
NOMBRE_ANTERIOR = "HU02_Screen"
NOMBRE_NUEVO = "EstadoCuentaScreen"


def reemplazar_texto(ruta: Path, anterior: str, nuevo: str) -> bool:
    texto = ruta.read_text(encoding="utf-8")
    if anterior not in texto:
        return False
    ruta.write_text(texto.replace(anterior, nuevo), encoding="utf-8")
    return True


def main() -> int:
    if not (CARPETA_APP / "build.gradle.kts").is_file():
        print("Error: no se encuentra movil/app/build.gradle.kts", file=sys.stderr)
        return 1

    for origen in (CARPETA_APP / "src").glob("*/java/com/example/pantallasmovil"):
        destino = origen.parents[2] / "com/alkira/movil"
        if destino.exists():
            print(f"Error: ya existe {destino}; revisa la migración antes de repetirla.", file=sys.stderr)
            return 1

    rutas = [CARPETA_APP / "build.gradle.kts"]
    rutas += [
        ruta for ruta in (CARPETA_APP / "src").rglob("*")
        if ruta.is_file() and ruta.suffix in {".kt", ".java", ".kts"}
    ]
    for ruta in rutas:
        reemplazar_texto(ruta, PAQUETE_ANTERIOR, PAQUETE_NUEVO)
        if ruta.suffix in {".kt", ".java"}:
            reemplazar_texto(ruta, NOMBRE_ANTERIOR, NOMBRE_NUEVO)

    for origen in (CARPETA_APP / "src").glob("*/java/com/example/pantallasmovil"):
        destino = origen.parents[2] / "com/alkira/movil"
        destino.parent.mkdir(parents=True, exist_ok=True)
        origen.rename(destino)
        # Los directorios 'example' y 'com' vacíos se eliminan con cuidado.
        directorio = origen.parent
        while directorio.name in {"example", "com"}:
            if any(directorio.iterdir()):
                break
            directorio.rmdir()
            directorio = directorio.parent
        print(f"Paquete trasladado: {destino}")

    for ruta in (CARPETA_APP / "src").rglob("HU02_Screen.kt"):
        destino = ruta.with_name("EstadoCuentaScreen.kt")
        if destino.exists():
            print(f"Error: ya existe {destino}", file=sys.stderr)
            return 1
        ruta.rename(destino)
        print(f"Pantalla renombrada: {destino}")

    archivo_nombre = CARPETA_APP / "src/main/res/values/strings.xml"
    if archivo_nombre.is_file():
        texto = archivo_nombre.read_text(encoding="utf-8")
        nuevo, cantidad = re.subn(
            r'(<string\s+name="app_name">)[^<]*(</string>)',
            r'\g<1>ALKIRA\g<2>',
            texto,
            count=1,
        )
        if cantidad:
            archivo_nombre.write_text(nuevo, encoding="utf-8")

    print("Migración terminada: paquete com.alkira.movil y pantalla EstadoCuentaScreen.")
    print("Revisa git diff antes de realizar un commit.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
