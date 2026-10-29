package com.alkira.movil

import android.graphics.BitmapFactory
import android.content.Intent
import androidx.compose.ui.test.*
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.test.platform.app.InstrumentationRegistry
import com.alkira.movil.red.*
import com.alkira.movil.screens.PasesQrScreen
import com.alkira.movil.screens.QrPase
import com.alkira.movil.ui.theme.PantallasMovilTheme
import com.google.zxing.BinaryBitmap
import com.google.zxing.MultiFormatReader
import com.google.zxing.RGBLuminanceSource
import com.google.zxing.common.HybridBinarizer
import kotlinx.coroutines.runBlocking
import org.junit.After
import org.junit.Assert.*
import org.junit.Assume.assumeTrue
import org.junit.Before
import org.junit.Rule
import org.junit.Test

class Hu10MovilTest {
    @get:Rule val pantalla = createComposeRule()

    @Before fun iniciarSesionDePrueba(): Unit = runBlocking {
        val args = InstrumentationRegistry.getArguments()
        assumeTrue("Solo ejecutar contra backend efímero", args.getString("alkiraPrueba") == "true" && BuildConfig.APPLICATION_ID.endsWith(".pruebas"))
        SesionMovil.iniciar(AutenticacionApi.iniciarSesion("residente@alkira.test", requireNotNull(args.getString("alkiraClave"))).token)
    }
    @After fun cerrar() { SesionMovil.cerrar() }

    @Test fun paseRealQrCompartibleRevocacionYFormulario(): Unit = runBlocking {
        val contexto = InstrumentationRegistry.getInstrumentation().targetContext
        val pase = PasesQrApi.generar("QA Samsung API HU10", 2)
        assertTrue(PasesQrApi.listar().any { it.id == pase.id })
        val compartir = QrPase.prepararCompartir(contexto, pase)
        assertEquals("image/png", compartir.type)
        @Suppress("DEPRECATION")
        val uri = requireNotNull(compartir.getParcelableExtra<android.net.Uri>(Intent.EXTRA_STREAM))
        val bitmap = contexto.contentResolver.openInputStream(uri).use { BitmapFactory.decodeStream(it) }
        val pixeles = IntArray(bitmap.width * bitmap.height)
        bitmap.getPixels(pixeles, 0, bitmap.width, 0, 0, bitmap.width, bitmap.height)
        val leido = MultiFormatReader().decode(BinaryBitmap(HybridBinarizer(RGBLuminanceSource(bitmap.width, bitmap.height, pixeles))))
        bitmap.recycle()
        assertEquals(pase.token, leido.text)
        try { ClienteHistoriasApi.solicitar("/api/accesos-qr/bitacora"); fail("No debe ver la bitácora") }
        catch (e: ErrorApi) { assertEquals(403, e.codigoHttp) }
        PasesQrApi.revocar(pase.id)
        assertEquals("revocado", PasesQrApi.listar().first { it.id == pase.id }.estado)

        pantalla.setContent { PantallasMovilTheme { PasesQrScreen() } }
        pantalla.onNodeWithText("Nombre del visitante").performTextInput("QA Samsung formulario HU10")
        pantalla.onNodeWithText("Generar pase").performScrollTo()
        pantalla.waitUntil(30_000) { pantalla.onAllNodes(hasText("Generar pase") and isEnabled()).fetchSemanticsNodes().isNotEmpty() }
        pantalla.onNodeWithText("Generar pase").performClick()
        pantalla.waitUntil(30_000) { pantalla.onAllNodesWithContentDescription("Código QR del pase").fetchSemanticsNodes().isNotEmpty() }
        capturar("hu10-qr")
        pantalla.onNodeWithText("Cerrar").performClick()
        pantalla.onNode(hasScrollToIndexAction()).performScrollToIndex(0)
        pantalla.waitUntil(30_000) { pantalla.onAllNodesWithContentDescription("Código QR del pase activo").fetchSemanticsNodes().isNotEmpty() }
        capturar("hu10-diseno")
        pantalla.onNodeWithText("Mis pases").performClick()
        pantalla.onNode(hasScrollToIndexAction()).performScrollToIndex(3)
        pantalla.onAllNodesWithText("Revocar").onFirst().performClick()
        pantalla.onNodeWithText("Confirmar revocación").performClick()
        pantalla.onNode(hasScrollToIndexAction()).performScrollToIndex(0)
        pantalla.waitUntil(30_000) { pantalla.onAllNodesWithText("Pase revocado.").fetchSemanticsNodes().isNotEmpty() }
        assertEquals("revocado", PasesQrApi.listar().first { it.visitante == "QA Samsung formulario HU10" }.estado)
        // Se deja un pase efímero para verificar Android -> caseta en el ejecutor PHP.
        PasesQrApi.generar("QA Samsung caseta HU10", 2)
    }

    private fun capturar(nombre: String) {
        InstrumentationRegistry.getInstrumentation().uiAutomation.executeShellCommand(
            "screencap -p /data/local/tmp/alkira-$nombre.png"
        ).let { android.os.ParcelFileDescriptor.AutoCloseInputStream(it).use { salida -> salida.readBytes() } }
    }
}
