package com.alkira.movil

import android.graphics.Bitmap
import android.net.Uri
import androidx.compose.ui.test.*
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.test.platform.app.InstrumentationRegistry
import com.alkira.movil.red.*
import com.alkira.movil.screens.IncidenciasScreen
import com.alkira.movil.ui.theme.PantallasMovilTheme
import kotlinx.coroutines.runBlocking
import org.junit.After
import org.junit.Assert.*
import org.junit.Assume.assumeTrue
import org.junit.Before
import org.junit.Rule
import org.junit.Test
import java.io.File

class Hu05MovilTest {
    @get:Rule val pantalla = createComposeRule()

    @Before fun iniciarSesionDePrueba() = runBlocking {
        val args = InstrumentationRegistry.getArguments()
        assumeTrue("Solo ejecutar con backend efímero y aplicación de pruebas",
            args.getString("alkiraPrueba") == "true" && BuildConfig.APPLICATION_ID.endsWith(".pruebas"))
        val clave = requireNotNull(args.getString("alkiraClave"))
        SesionMovil.iniciar(AutenticacionApi.iniciarSesion("residente@alkira.test", clave).token)
    }

    @After fun cerrar() { SesionMovil.cerrar() }

    @Test fun reporteConArchivoYFormularioReal(): Unit = runBlocking {
        val contexto = InstrumentationRegistry.getInstrumentation().targetContext
        val archivo = File(contexto.cacheDir, "qa-hu05.png")
        try {
            val imagen = Bitmap.createBitmap(16, 16, Bitmap.Config.ARGB_8888)
            archivo.outputStream().use { imagen.compress(Bitmap.CompressFormat.PNG, 100, it) }
            imagen.recycle()
            val unidad = IncidenciasApi.unidades().first()
            val id = IncidenciasApi.crear("QA Samsung archivo HU05", "Archivo desde Android", "plomeria", "media", unidad, Uri.fromFile(archivo), contexto)
            assertTrue(IncidenciasApi.listar().any { it.id == id })
            try {
                ClienteHistoriasApi.solicitar("/api/incidencias?idUsuario=4")
                fail("No debe consultar reportes de otro residente")
            } catch (e: ErrorApi) { assertEquals(403, e.codigoHttp) }

            pantalla.setContent { PantallasMovilTheme { IncidenciasScreen() } }
            pantalla.waitUntil(30_000) { pantalla.onAllNodes(hasText("Mis reportes") and isEnabled()).fetchSemanticsNodes().isNotEmpty() }
            InstrumentationRegistry.getInstrumentation().uiAutomation.executeShellCommand(
                "screencap -p /data/local/tmp/alkira-hu05-diseno.png"
            ).use { java.io.FileInputStream(it.fileDescriptor).use { salida -> salida.readBytes() } }
            pantalla.onNodeWithContentDescription("Título").performTextInput("QA Samsung formulario HU05")
            pantalla.onNodeWithContentDescription("Descripción").performTextInput("Reporte enviado desde la pantalla real")
            pantalla.waitUntil(30_000) { pantalla.onAllNodes(hasText("Enviar Reporte") and isEnabled()).fetchSemanticsNodes().isNotEmpty() }
            pantalla.onNodeWithText("Enviar Reporte").performClick()
            pantalla.onNode(hasScrollToIndexAction()).performScrollToIndex(0)
            pantalla.waitUntil(30_000) { pantalla.onAllNodes(hasText("enviado correctamente", substring = true)).fetchSemanticsNodes().isNotEmpty() }
            pantalla.onNodeWithContentDescription("Título").assertTextEquals("")
            pantalla.onNodeWithText("Mis reportes").performClick()
            pantalla.onNode(hasScrollToIndexAction()).performScrollToIndex(2)
            pantalla.onNodeWithText("QA Samsung formulario HU05", substring = true).assertExists()
        } finally { archivo.delete() }
    }
}
