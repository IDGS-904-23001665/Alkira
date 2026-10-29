package com.alkira.movil

import androidx.compose.ui.test.*
import androidx.compose.ui.test.junit4.createAndroidComposeRule
import androidx.compose.ui.semantics.SemanticsActions
import androidx.test.platform.app.InstrumentationRegistry
import androidx.test.espresso.Espresso.closeSoftKeyboard
import androidx.test.espresso.Espresso.pressBack
import org.junit.Assume.assumeTrue
import org.junit.After
import org.junit.Rule
import org.junit.Test

/** Solo rama conjunta: verifica las dos historias desde la navegación real. */
class HistoriasNavegacionTest {
    @get:Rule val pantalla = createAndroidComposeRule<MainActivity>()

    @After fun capturarResultado() { capturar("resultado") }

    private fun capturar(nombre: String) {
        pantalla.waitForIdle()
        Thread.sleep(1200) // Permite ver cada paso en el dispositivo y terminar animaciones.
        InstrumentationRegistry.getInstrumentation().uiAutomation.executeShellCommand(
            "screencap -p /data/local/tmp/alkira-integral-$nombre.png"
        ).use { java.io.FileInputStream(it.fileDescriptor).use { salida -> salida.readBytes() } }
    }

    private fun comprobarNavegacion() {
        pantalla.waitForIdle()
        pantalla.onNodeWithContentDescription("Menú").assertIsDisplayed()
        listOf("Inicio", "Pagos", "Servicios", "Comunidad", "Pases QR").forEach {
            botonVisible(it).assertIsDisplayed()
        }
    }

    private fun botonVisible(texto: String): SemanticsNodeInteraction {
        val nodos = pantalla.onAllNodesWithText(texto)
        return nodos.fetchSemanticsNodes().indices.map { nodos[it] }.first {
            runCatching { it.assertIsDisplayed() }.isSuccess
        }
    }

    @Test fun loginYAccesoALasDosHistorias() {
        val args = InstrumentationRegistry.getArguments()
        assumeTrue(args.getString("alkiraPrueba") == "true" && BuildConfig.APPLICATION_ID.endsWith(".pruebas"))
        pantalla.onNodeWithText("Correo electrónico").performTextInput("residente@alkira.test")
        pantalla.onNodeWithText("Contraseña").performTextInput(requireNotNull(args.getString("alkiraClave")))
        closeSoftKeyboard()
        pantalla.onNodeWithText("Iniciar sesión").performScrollTo().performClick()
        pantalla.waitUntil(30_000) { pantalla.onAllNodesWithContentDescription("Menú").fetchSemanticsNodes().isNotEmpty() }
        comprobarNavegacion()
        capturar("inicio")
        pantalla.onNodeWithContentDescription("Menú").performClick()
        capturar("menu")
        pantalla.onNodeWithText("Reportar Incidencia").performSemanticsAction(SemanticsActions.OnClick) { it() }
        pantalla.waitUntil(30_000) { pantalla.onAllNodes(hasText("Nuevo reporte") and isEnabled()).fetchSemanticsNodes().isNotEmpty() }
        pantalla.onNodeWithContentDescription("Título").assertDoesNotExist()
        comprobarNavegacion()
        capturar("incidencias")
        pantalla.onNodeWithText("Nuevo reporte").performClick()
        pantalla.onNodeWithText("Volver a mis reportes").assertIsDisplayed()
        capturar("formulario-incidencia")
        pantalla.onNodeWithContentDescription("Título").performTextInput("QA integral desde menu HU05")
        pantalla.onNodeWithContentDescription("Descripción").performTextInput("Reporte creado dentro de la aplicacion completa")
        closeSoftKeyboard()
        pantalla.waitUntil(30_000) { pantalla.onAllNodes(hasText("Enviar Reporte") and isEnabled()).fetchSemanticsNodes().isNotEmpty() }
        pantalla.onNodeWithText("Enviar Reporte").performClick()
        pantalla.waitUntil(30_000) { pantalla.onAllNodes(hasText("enviado correctamente", substring = true)).fetchSemanticsNodes().isNotEmpty() }
        pantalla.waitUntil(30_000) { pantalla.onAllNodes(hasText("Nuevo reporte") and isEnabled()).fetchSemanticsNodes().isNotEmpty() }
        pantalla.onNodeWithContentDescription("Título").assertDoesNotExist()
        pantalla.onNode(hasScrollToIndexAction()).performScrollToNode(hasText("QA integral desde menu HU05", substring = true))
        pantalla.onNode(hasText("QA integral desde menu HU05", substring = true)).assertIsDisplayed()
        comprobarNavegacion()
        capturar("reporte-creado")
        // Atrás cierra el menú; desde el formulario vuelve al listado, luego al inicio.
        pantalla.onNodeWithContentDescription("Menú").performClick()
        pantalla.waitForIdle()
        pressBack()
        pantalla.onNodeWithText("Nuevo reporte").assertIsDisplayed()
        pantalla.onNodeWithText("Nuevo reporte").performClick()
        pantalla.onNodeWithContentDescription("Título").assertExists()
        pressBack()
        pantalla.onNodeWithText("Nuevo reporte").assertIsDisplayed()
        pressBack()
        comprobarNavegacion()
        pantalla.onNodeWithContentDescription("Título").assertDoesNotExist()
        pantalla.onNodeWithText("Pases QR").performClick()
        pantalla.waitUntil(30_000) { pantalla.onAllNodes(hasText("Mis pases") and isEnabled()).fetchSemanticsNodes().isNotEmpty() }
        comprobarNavegacion()
        capturar("pases")
        pantalla.onNode(hasScrollToIndexAction()).performScrollToNode(hasText("Nombre del visitante"))
        pantalla.onNodeWithText("Nombre del visitante").performTextInput("QA Samsung caseta HU10")
        closeSoftKeyboard()
        pantalla.onNode(hasScrollToIndexAction()).performScrollToNode(hasText("Generar pase"))
        comprobarNavegacion()
        capturar("nueva-visita")
        pantalla.waitUntil(30_000) { pantalla.onAllNodes(hasText("Generar pase") and isEnabled()).fetchSemanticsNodes().isNotEmpty() }
        pantalla.onNodeWithText("Generar pase").performClick()
        pantalla.waitUntil(30_000) { pantalla.onAllNodesWithText("Cerrar").fetchSemanticsNodes().isNotEmpty() }
        botonVisible("QA Samsung caseta HU10").assertIsDisplayed()
        capturar("qr-generado")
        pressBack()
        pantalla.onNodeWithText("Cerrar").assertDoesNotExist()
        pantalla.waitUntil(30_000) { pantalla.onAllNodes(hasText("Mis pases") and isEnabled()).fetchSemanticsNodes().isNotEmpty() }
        pantalla.onNode(hasScrollToIndexAction()).performScrollToIndex(0)
        pantalla.onNodeWithText("Mis pases").performClick()
        pantalla.onNode(hasScrollToIndexAction()).performScrollToNode(hasText("QA Samsung caseta HU10"))
        botonVisible("QA Samsung caseta HU10").assertIsDisplayed()
        comprobarNavegacion()
        capturar("historial-pases")
        pantalla.onNodeWithText("Ver QR").performClick()
        pantalla.waitUntil(30_000) { pantalla.onAllNodesWithText("Cerrar").fetchSemanticsNodes().isNotEmpty() }
        pressBack()
        pantalla.onNodeWithText("Nuevo pase").assertIsDisplayed()
        pantalla.onNodeWithText("Revocar").performClick()
        pantalla.onNodeWithText("Confirmar revocación").assertIsDisplayed()
        pressBack()
        pantalla.onNodeWithText("Confirmar revocación").assertDoesNotExist()
        pantalla.onNodeWithText("Revocar").assertIsDisplayed()
        pressBack()
        pantalla.onNodeWithText("Control de Accesos QR").assertIsDisplayed()
        pressBack()
        comprobarNavegacion()
        pantalla.onNodeWithText("Control de Accesos QR").assertDoesNotExist()
        // Prueba separada del borrador: no se envía ni se confunde con el reporte creado.
        pantalla.onNodeWithContentDescription("Menú").performClick()
        pantalla.onNodeWithText("Reportar Incidencia").performSemanticsAction(SemanticsActions.OnClick) { it() }
        pantalla.waitUntil(30_000) { pantalla.onAllNodes(hasText("Nuevo reporte") and isEnabled()).fetchSemanticsNodes().isNotEmpty() }
        pantalla.onNodeWithText("Nuevo reporte").performClick()
        pantalla.onNodeWithContentDescription("Título").performTextInput("Borrador sin enviar HU05")
        pantalla.onNodeWithContentDescription("Descripción").performTextInput("Conservar al cambiar de modulo")
        closeSoftKeyboard()
        pantalla.onNodeWithText("Volver a mis reportes").performClick()
        pantalla.onNodeWithContentDescription("Título").assertDoesNotExist()
        pantalla.onNodeWithText("Nuevo reporte").performClick()
        pantalla.onNodeWithContentDescription("Título").assertTextEquals("Borrador sin enviar HU05")
        botonVisible("Pases QR").performClick()
        pantalla.waitUntil(30_000) { pantalla.onAllNodes(hasText("Mis pases") and isEnabled()).fetchSemanticsNodes().isNotEmpty() }
        pantalla.onNode(hasScrollToIndexAction()).performScrollToNode(hasText("Nombre del visitante"))
        pantalla.onNodeWithText("Nombre del visitante").performTextInput("Borrador sin enviar HU10")
        closeSoftKeyboard()
        pantalla.onNodeWithContentDescription("Menú").performClick()
        pantalla.onNodeWithText("Reportar Incidencia").performSemanticsAction(SemanticsActions.OnClick) { it() }
        pantalla.onNodeWithContentDescription("Título").assertTextEquals("Borrador sin enviar HU05")
        pantalla.onNodeWithContentDescription("Descripción").assertTextEquals("Conservar al cambiar de modulo")
        capturar("borrador-conservado")
        botonVisible("Pases QR").performClick()
        pantalla.onNode(hasScrollToIndexAction()).performScrollToNode(hasText("Nombre del visitante"))
        pantalla.onNodeWithText("Nombre del visitante").assertTextContains("Borrador sin enviar HU10")
    }
}
