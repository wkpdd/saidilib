package dz.saidi.staff.ui

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

// Saidi brand palette (same orange as the website/logo).
val SaidiOrange = Color(0xFFE07D00)
val SaidiOrangeDark = Color(0xFFB85F00)
val SaidiRed = Color(0xFFDC2626)
val SaidiInk = Color(0xFF431407)
val SaidiCream = Color(0xFFFFF7ED)

private val LightColors = lightColorScheme(
    primary = SaidiOrangeDark,
    onPrimary = Color.White,
    primaryContainer = Color(0xFFFFE8CC),
    onPrimaryContainer = SaidiInk,
    secondary = SaidiRed,
    onSecondary = Color.White,
    background = SaidiCream,
    surface = Color.White,
    surfaceVariant = Color(0xFFFFF1E0),
)

private val DarkColors = darkColorScheme(
    primary = SaidiOrange,
    onPrimary = Color.Black,
    primaryContainer = Color(0xFF5C3A00),
    secondary = Color(0xFFFF8A80),
)

@Composable
fun SaidiTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = if (isSystemInDarkTheme()) DarkColors else LightColors,
        content = content,
    )
}

/** Status → chip colour, mirroring the web admin badges. */
fun statusColor(status: String): Color = when (status) {
    "pending" -> Color(0xFFF59E0B)
    "confirmed" -> Color(0xFF3B82F6)
    "preparing" -> Color(0xFF6366F1)
    "shipped" -> Color(0xFF06B6D4)
    "delivered" -> Color(0xFF22C55E)
    "cancelled" -> Color(0xFFEF4444)
    "returned" -> Color(0xFFF43F5E)
    else -> Color.Gray
}

val STATUS_LABELS = mapOf(
    "pending" to "En attente",
    "confirmed" to "Confirmée",
    "preparing" to "En préparation",
    "shipped" to "Expédiée",
    "delivered" to "Livrée",
    "cancelled" to "Annulée",
    "returned" to "Retournée",
)

fun money(v: Double): String = "%,.0f DA".format(v).replace(',', ' ')
