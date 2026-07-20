package dz.saidi.staff.ui

import android.content.Context
import android.graphics.Bitmap
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.graphics.drawable.toBitmap
import androidx.palette.graphics.Palette
import coil.ImageLoader
import coil.request.ImageRequest
import androidx.compose.foundation.gestures.detectDragGestures
import androidx.compose.foundation.gestures.detectTapGestures

/** Recently used variant colours — remembered on this device (like the web admin). */
object RecentColors {
    private const val PREFS = "saidi_colors"
    private const val KEY = "recent"

    fun get(ctx: Context): List<String> =
        ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .getString(KEY, "")!!.split(',').filter { it.isNotBlank() }

    fun add(ctx: Context, hex: String) {
        val list = (listOf(hex) + get(ctx).filter { !it.equals(hex, true) }).take(12)
        ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .edit().putString(KEY, list.joinToString(",")).apply()
    }
}

/** Curated FR names (same palette as the web admin) for auto-naming a picked colour. */
private val NAMED_COLORS = listOf(
    "Rouge" to 0xdc2626, "Bleu" to 0x2563eb, "Orange" to 0xe07d00,
    "Noir" to 0x000000, "Blanc" to 0xffffff, "Gris" to 0x808080,
    "Bordeaux" to 0x800000, "Rose" to 0xffc0cb, "Marron" to 0xa52a2a,
    "Beige" to 0xf5f5dc, "Jaune" to 0xffff00, "Doré" to 0xffd700,
    "Vert" to 0x008000, "Vert clair" to 0x90ee90, "Bleu clair" to 0xadd8e6,
    "Bleu marine" to 0x000080, "Violet" to 0x800080, "Turquoise" to 0x40e0d0,
    "Argenté" to 0xc0c0c0,
)

/** Closest friendly French name, or the hex itself when nothing is close enough. */
fun colorNameFor(hex: String): String {
    val clean = hex.removePrefix("#")
    val v = clean.toLongOrNull(16) ?: return hex
    val r = (v shr 16 and 0xFF).toInt(); val g = (v shr 8 and 0xFF).toInt(); val b = (v and 0xFF).toInt()
    var best = ""; var bestDist = Int.MAX_VALUE
    for ((name, c) in NAMED_COLORS) {
        val pr = (c shr 16 and 0xFF).toInt(); val pg = (c shr 8 and 0xFF).toInt(); val pb = (c and 0xFF).toInt()
        val d = (r - pr) * (r - pr) + (g - pg) * (g - pg) + (b - pb) * (b - pb)
        if (d < bestDist) { bestDist = d; best = name }
    }
    return if (bestDist < 9000) best else "#${clean.uppercase()}"
}

fun parseHex(hex: String): Color? = try {
    Color(android.graphics.Color.parseColor(if (hex.startsWith("#")) hex else "#$hex"))
} catch (_: Exception) { null }

private fun Color.toHex(): String = "#%02x%02x%02x".format(
    (red * 255).toInt(), (green * 255).toInt(), (blue * 255).toInt()
)

/** Extract dominant colours from the product photo → one-tap suggestions. */
suspend fun suggestedColorsFrom(ctx: Context, imageUrl: String?): List<String> {
    if (imageUrl == null) return emptyList()
    return try {
        val drawable = ImageLoader(ctx).execute(
            ImageRequest.Builder(ctx).data(imageUrl).allowHardware(false).build()
        ).drawable ?: return emptyList()
        val bmp: Bitmap = drawable.toBitmap()
        val palette = Palette.from(bmp).maximumColorCount(16).generate()
        listOfNotNull(
            palette.vibrantSwatch, palette.dominantSwatch, palette.darkVibrantSwatch,
            palette.lightVibrantSwatch, palette.mutedSwatch, palette.darkMutedSwatch,
            palette.lightMutedSwatch,
        ).map { "#%06x".format(it.rgb and 0xFFFFFF) }.distinct().take(8)
    } catch (_: Exception) { emptyList() }
}

/**
 * Full colour picker: photo-suggested + recent + named palette chips, plus a
 * custom hue/shade picker. Returns the hex and an auto French name.
 */
@Composable
fun ColorPickerDialog(
    initialHex: String?,
    suggested: List<String>,
    onPick: (hex: String, name: String) -> Unit,
    onDismiss: () -> Unit,
) {
    val ctx = androidx.compose.ui.platform.LocalContext.current
    val initial = initialHex?.let { parseHex(it) } ?: Color(0xFFDC2626)
    val hsvInit = FloatArray(3).also {
        android.graphics.Color.colorToHSV(android.graphics.Color.argb(
            255, (initial.red * 255).toInt(), (initial.green * 255).toInt(), (initial.blue * 255).toInt()), it)
    }
    var hue by remember { mutableStateOf(hsvInit[0]) }
    var sat by remember { mutableStateOf(hsvInit[1]) }
    var value by remember { mutableStateOf(hsvInit[2]) }
    val current = Color(android.graphics.Color.HSVToColor(floatArrayOf(hue, sat, value)))
    val currentHex = current.toHex()
    val recents = remember { RecentColors.get(ctx) }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Choisir une couleur") },
        text = {
            Column(Modifier.verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(10.dp)) {

                // Live preview + name
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    Box(Modifier.size(44.dp).clip(CircleShape).background(current)
                        .border(1.dp, MaterialTheme.colorScheme.outline, CircleShape))
                    Column {
                        Text(colorNameFor(currentHex), fontWeight = FontWeight.Bold)
                        Text(currentHex.uppercase(), fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }

                if (suggested.isNotEmpty()) {
                    Text("📷 Suggérées (photo du produit)", fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                    ChipRow(suggested) { h -> parseHex(h)?.let { c -> setHsv(c) { h1, s1, v1 -> hue = h1; sat = s1; value = v1 } } }
                }
                if (recents.isNotEmpty()) {
                    Text("🕘 Récentes", fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                    ChipRow(recents) { h -> parseHex(h)?.let { c -> setHsv(c) { h1, s1, v1 -> hue = h1; sat = s1; value = v1 } } }
                }
                Text("🎨 Palette", fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                ChipRow(NAMED_COLORS.map { "#%06x".format(it.second) }) { h ->
                    parseHex(h)?.let { c -> setHsv(c) { h1, s1, v1 -> hue = h1; sat = s1; value = v1 } }
                }

                Text("Personnalisée", fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                // Shade square (saturation × brightness) for the chosen hue.
                val pureHue = Color(android.graphics.Color.HSVToColor(floatArrayOf(hue, 1f, 1f)))
                Canvas(
                    Modifier.fillMaxWidth().height(140.dp).clip(RoundedCornerShape(10.dp))
                        .pointerInput(hue) {
                            detectTapGestures { p ->
                                sat = (p.x / size.width).coerceIn(0f, 1f)
                                value = 1f - (p.y / size.height).coerceIn(0f, 1f)
                            }
                        }
                        .pointerInput(hue) {
                            detectDragGestures { change, _ ->
                                sat = (change.position.x / size.width).coerceIn(0f, 1f)
                                value = 1f - (change.position.y / size.height).coerceIn(0f, 1f)
                            }
                        },
                ) {
                    drawRect(Brush.horizontalGradient(listOf(Color.White, pureHue)))
                    drawRect(Brush.verticalGradient(listOf(Color.Transparent, Color.Black)))
                    drawCircle(Color.White, radius = 9.dp.toPx(),
                        center = Offset(sat * size.width, (1f - value) * size.height),
                        style = androidx.compose.ui.graphics.drawscope.Stroke(3.dp.toPx()))
                }
                // Hue bar
                val rainbow = List(13) { Color(android.graphics.Color.HSVToColor(floatArrayOf(it * 30f, 1f, 1f))) }
                Canvas(
                    Modifier.fillMaxWidth().height(28.dp).clip(RoundedCornerShape(50))
                        .pointerInput(Unit) {
                            detectTapGestures { p -> hue = (p.x / size.width * 360f).coerceIn(0f, 360f) }
                        }
                        .pointerInput(Unit) {
                            detectDragGestures { change, _ ->
                                hue = (change.position.x / size.width * 360f).coerceIn(0f, 360f)
                            }
                        },
                ) {
                    drawRect(Brush.horizontalGradient(rainbow))
                    drawCircle(Color.White, radius = 10.dp.toPx(),
                        center = Offset(hue / 360f * size.width, size.height / 2),
                        style = androidx.compose.ui.graphics.drawscope.Stroke(3.dp.toPx()))
                }
            }
        },
        confirmButton = {
            TextButton(onClick = {
                RecentColors.add(ctx, currentHex)
                onPick(currentHex, colorNameFor(currentHex))
            }) { Text("Choisir", fontWeight = FontWeight.Bold) }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Annuler") } },
    )
}

private inline fun setHsv(c: Color, apply: (Float, Float, Float) -> Unit) {
    val hsv = FloatArray(3)
    android.graphics.Color.colorToHSV(android.graphics.Color.argb(
        255, (c.red * 255).toInt(), (c.green * 255).toInt(), (c.blue * 255).toInt()), hsv)
    apply(hsv[0], hsv[1], hsv[2])
}

@Composable
private fun ChipRow(hexes: List<String>, onTap: (String) -> Unit) {
    Row(
        Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        hexes.forEach { h ->
            val c = parseHex(h) ?: return@forEach
            Box(
                Modifier.size(34.dp).clip(CircleShape).background(c)
                    .border(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.4f), CircleShape)
                    .clickable { onTap(h) },
            )
        }
    }
}
