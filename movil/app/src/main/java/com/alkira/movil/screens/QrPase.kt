package com.alkira.movil.screens

import android.content.ClipData
import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.graphics.Color
import androidx.core.content.FileProvider
import com.alkira.movil.red.PaseResidente
import com.google.zxing.BarcodeFormat
import com.google.zxing.EncodeHintType
import com.google.zxing.qrcode.QRCodeWriter
import java.io.File

object QrPase {
    fun crear(token: String): Bitmap {
        val matriz = QRCodeWriter().encode(token, BarcodeFormat.QR_CODE, 600, 600, mapOf(EncodeHintType.MARGIN to 4))
        val pixeles = IntArray(matriz.width * matriz.height) { i ->
            if (matriz[i % matriz.width, i / matriz.width]) Color.BLACK else Color.WHITE
        }
        return Bitmap.createBitmap(pixeles, matriz.width, matriz.height, Bitmap.Config.ARGB_8888)
    }

    fun prepararCompartir(contexto: Context, pase: PaseResidente): Intent {
        val carpeta = File(contexto.cacheDir, "pases")
        check(carpeta.isDirectory || carpeta.mkdirs()) { "No se pudo preparar el QR para compartir." }
        val archivo = File(carpeta, "pase-${pase.id}.png")
        val bitmap = crear(pase.token)
        try { archivo.outputStream().use { bitmap.compress(Bitmap.CompressFormat.PNG, 100, it) } }
        finally { bitmap.recycle() }
        val uri = FileProvider.getUriForFile(contexto, "${contexto.packageName}.archivos", archivo)
        return Intent(Intent.ACTION_SEND).apply {
            type = "image/png"
            putExtra(Intent.EXTRA_STREAM, uri)
            putExtra(Intent.EXTRA_TEXT, "Pase para ${pase.visitante}. Vigente de ${pase.inicio} a ${pase.fin}.")
            clipData = ClipData.newUri(contexto.contentResolver, "Pase de acceso", uri)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }
    }
}
