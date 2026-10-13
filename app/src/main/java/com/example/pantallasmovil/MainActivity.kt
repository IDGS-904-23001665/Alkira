package com.example.pantallasmovil

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Scaffold
import androidx.compose.ui.Modifier
import com.example.pantallasmovil.screens.HU01_Screen
import com.example.pantallasmovil.screens.HU02_Screen
import com.example.pantallasmovil.screens.HU03_Screen
import com.example.pantallasmovil.screens.HU04_Screen
import com.example.pantallasmovil.screens.HU05_Screen
import com.example.pantallasmovil.ui.theme.PantallasMovilTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            PantallasMovilTheme {
                Scaffold(modifier = Modifier.fillMaxSize()) { innerPadding ->
                    HU05_Screen(modifier = Modifier.padding(innerPadding))
                }
            }
        }
    }
}