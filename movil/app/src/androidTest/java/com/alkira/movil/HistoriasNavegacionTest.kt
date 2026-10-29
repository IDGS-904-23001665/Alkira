package com.alkira.movil

import androidx.compose.ui.test.*
import androidx.compose.ui.test.junit4.createAndroidComposeRule
import androidx.test.platform.app.InstrumentationRegistry
import org.junit.Assume.assumeTrue
import org.junit.Rule
import org.junit.Test

/** Solo rama conjunta: verifica las dos historias desde la navegación real. */
class HistoriasNavegacionTest {
    @get:Rule val pantalla = createAndroidComposeRule<MainActivity>()

    @Test fun loginYAccesoALasDosHistorias() {
        val args = InstrumentationRegistry.getArguments()
        assumeTrue(args.getString("alkiraPrueba") == "true" && BuildConfig.APPLICATION_ID.endsWith(".pruebas"))
        pantalla.onNodeWithText("Correo electrónico").performTextInput("residente@alkira.test")
        pantalla.onNodeWithText("Contraseña").performTextInput(requireNotNull(args.getString("alkiraClave")))
        pantalla.onNodeWithText("Iniciar sesión").performScrollTo().performClick()
        pantalla.waitUntil(30_000) { pantalla.onAllNodesWithContentDescription("Menú").fetchSemanticsNodes().isNotEmpty() }
        pantalla.onNodeWithContentDescription("Menú").performClick()
        pantalla.onNodeWithText("Reportar Incidencia").performClick()
        pantalla.onNodeWithContentDescription("Título").assertExists()
        pantalla.onNodeWithText("Pases QR").performClick()
        pantalla.waitUntil(30_000) { pantalla.onAllNodes(hasText("Mis pases") and isEnabled()).fetchSemanticsNodes().isNotEmpty() }
        pantalla.onNode(hasScrollToIndexAction()).performScrollToIndex(2)
        pantalla.onNodeWithText("Nombre del visitante").assertExists()
        pantalla.onNodeWithText("Generar pase").assertExists()
    }
}
