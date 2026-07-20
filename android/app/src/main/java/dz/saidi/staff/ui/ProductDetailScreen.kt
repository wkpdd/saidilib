package dz.saidi.staff.ui

import android.content.Context
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.AddAPhoto
import androidx.compose.material.icons.filled.PhotoLibrary
import androidx.compose.material.icons.filled.Star
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.FileProvider
import coil.compose.AsyncImage
import dz.saidi.staff.api.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.asRequestBody
import java.io.File

/** Full product editor: photos (camera/gallery), all fields, variants. id == 0 → create mode. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProductDetailScreen(initialId: Long, onBack: () -> Unit) {
    var productId by remember { mutableStateOf(initialId) }
    val isCreate = productId == 0L
    var loaded by remember { mutableStateOf(isCreate) }
    var error by remember { mutableStateOf<String?>(null) }
    var busy by remember { mutableStateOf(false) }
    var toast by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()
    val ctx = LocalContext.current
    val snackbar = remember { SnackbarHostState() }

    // Editable state
    var images by remember { mutableStateOf(listOf<ProductImageInfo>()) }
    var nameFr by remember { mutableStateOf("") }
    var sku by remember { mutableStateOf("") }
    var brand by remember { mutableStateOf("") }
    var categoryId by remember { mutableStateOf<Long?>(null) }
    var price by remember { mutableStateOf("") }
    var compareAt by remember { mutableStateOf("") }
    var wholesale by remember { mutableStateOf("") }
    var stock by remember { mutableStateOf("") }
    var trackStock by remember { mutableStateOf(false) }
    var active by remember { mutableStateOf(true) }
    var shortDesc by remember { mutableStateOf("") }
    var variants = remember { mutableStateListOf<EditableVariant>() }
    var categories by remember { mutableStateOf(listOf<CategoryInfo>()) }
    var pickingColorFor by remember { mutableStateOf<Int?>(null) }
    var suggestedColors by remember { mutableStateOf(listOf<String>()) }

    fun applyProduct(p: ProductFull) {
        productId = p.id
        images = p.images
        nameFr = p.name; sku = p.sku ?: ""; brand = p.brand ?: ""
        categoryId = p.categoryId
        price = trimNum(p.price); compareAt = if (p.compareAtPrice > 0) trimNum(p.compareAtPrice) else ""
        wholesale = if (p.wholesalePrice > 0) trimNum(p.wholesalePrice) else ""
        stock = p.stock.toString(); trackStock = p.trackStock; active = p.isActive
        shortDesc = p.shortDesc ?: ""
        variants.clear()
        p.variants.forEach {
            variants.add(EditableVariant(it.id, it.color ?: "", it.colorHex ?: "", it.size ?: "", it.stock.toString(), trimNum(it.priceDelta)))
        }
        loaded = true
    }

    LaunchedEffect(Unit) {
        try { categories = ApiClient.service.categories().categories } catch (_: Exception) {}
        if (!isCreate) {
            try { applyProduct(ApiClient.service.productFull(initialId).product) }
            catch (e: Exception) { error = ApiClient.errorMessage(e) }
        }
    }
    // Extract dominant colours of the main photo → suggestions in the picker.
    LaunchedEffect(images.firstOrNull()?.url) {
        suggestedColors = suggestedColorsFrom(ctx, images.firstOrNull()?.url)
    }
    LaunchedEffect(toast) { toast?.let { snackbar.showSnackbar(it); toast = null } }

    fun upload(uri: Uri) {
        if (productId == 0L) { toast = "Enregistrez d'abord le produit."; return }
        busy = true
        scope.launch {
            try {
                val part = withContext(Dispatchers.IO) { uriToPart(ctx, uri) }
                applyProduct(ApiClient.service.uploadProductImage(productId, part).product)
                toast = "📷 Photo ajoutée (compressée automatiquement)"
            } catch (e: Exception) { toast = ApiClient.errorMessage(e) }
            finally { busy = false }
        }
    }

    var cameraUri by remember { mutableStateOf<Uri?>(null) }
    val takePicture = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { ok ->
        if (ok) cameraUri?.let { upload(it) }
    }
    val pickImage = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        uri?.let { upload(it) }
    }

    fun startCamera() {
        val dir = File(ctx.cacheDir, "photos").apply { mkdirs() }
        val file = File(dir, "capture-${System.currentTimeMillis()}.jpg")
        val uri = FileProvider.getUriForFile(ctx, "dz.saidi.staff.fileprovider", file)
        cameraUri = uri
        takePicture.launch(uri)
    }

    fun save() {
        if (nameFr.isBlank() || price.toDoubleOrNull() == null) {
            toast = "Nom et prix sont obligatoires."; return
        }
        busy = true
        scope.launch {
            try {
                val body = ProductUpsertRequest(
                    nameFr = nameFr.trim(), brand = brand.trim().ifBlank { null },
                    sku = sku.trim().ifBlank { null }, categoryId = categoryId,
                    shortDescFr = shortDesc.trim().ifBlank { null },
                    price = price.replace(',', '.').toDouble(),
                    compareAtPrice = compareAt.replace(',', '.').toDoubleOrNull(),
                    wholesalePrice = wholesale.replace(',', '.').toDoubleOrNull(),
                    stock = stock.toIntOrNull(), trackStock = trackStock, isActive = active,
                    variants = variants.mapNotNull { v ->
                        if (v.color.isBlank() && v.hex.isBlank() && v.size.isBlank()) null
                        else VariantUpsert(
                            id = v.id.takeIf { it > 0 },
                            color = v.color.ifBlank { null },
                            colorHex = v.hex.ifBlank { null },
                            size = v.size.ifBlank { null },
                            stock = v.stock.toIntOrNull(),
                            priceDelta = v.delta.replace(',', '.').toDoubleOrNull(),
                        )
                    },
                )
                val res = if (productId == 0L) ApiClient.service.createProduct(body)
                          else ApiClient.service.updateProductFull(productId, body)
                applyProduct(res.product)
                toast = "✅ Produit enregistré — vous pouvez ajouter des photos"
            } catch (e: Exception) { toast = ApiClient.errorMessage(e) }
            finally { busy = false }
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(if (isCreate && productId == 0L) "Nouveau produit" else nameFr.ifBlank { "Produit" }, fontWeight = FontWeight.Bold, maxLines = 1) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Retour") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
        bottomBar = {
            Surface(shadowElevation = 8.dp) {
                Button(
                    onClick = { save() }, enabled = !busy,
                    modifier = Modifier.fillMaxWidth().padding(16.dp).height(50.dp),
                ) { Text(if (busy) "Enregistrement…" else "💾 Enregistrer", fontSize = 16.sp, fontWeight = FontWeight.Bold) }
            }
        },
    ) { pad ->
        when {
            error != null && !loaded -> ErrorBox(error!!, Modifier.padding(pad)) { error = null }
            !loaded -> Box(Modifier.fillMaxSize().padding(pad), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
            else -> Column(
                Modifier.fillMaxSize().padding(pad).verticalScroll(rememberScrollState()).padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                // ---- Photos ----
                Text("Photos", fontWeight = FontWeight.Bold)
                if (productId == 0L) {
                    Text("💡 Enregistrez le produit une première fois pour ajouter des photos.", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                } else {
                    LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        items(images) { im ->
                            Box {
                                AsyncImage(
                                    model = im.thumb ?: im.url, contentDescription = null,
                                    modifier = Modifier.size(96.dp).clip(RoundedCornerShape(12.dp)),
                                )
                                if (im.isMain) {
                                    Icon(
                                        Icons.Filled.Star, "Principale",
                                        tint = Color(0xFFF59E0B),
                                        modifier = Modifier.align(Alignment.TopStart).padding(4.dp).size(20.dp),
                                    )
                                } else {
                                    // Tap the star to promote; ✕ to delete.
                                    Icon(
                                        Icons.Filled.Star, "Définir principale",
                                        tint = Color.White.copy(alpha = 0.8f),
                                        modifier = Modifier.align(Alignment.TopStart).padding(4.dp).size(20.dp)
                                            .clickable(enabled = !busy) {
                                                busy = true
                                                scope.launch {
                                                    try { applyProduct(ApiClient.service.setMainImage(productId, im.id).product) }
                                                    catch (e: Exception) { toast = ApiClient.errorMessage(e) }
                                                    finally { busy = false }
                                                }
                                            },
                                    )
                                }
                                Text(
                                    "✕",
                                    color = Color.White, fontSize = 12.sp,
                                    modifier = Modifier.align(Alignment.TopEnd).padding(4.dp)
                                        .clip(CircleShape).background(Color.Black.copy(alpha = 0.55f))
                                        .padding(horizontal = 6.dp, vertical = 1.dp)
                                        .clickable(enabled = !busy) {
                                            busy = true
                                            scope.launch {
                                                try { applyProduct(ApiClient.service.deleteProductImage(productId, im.id).product) }
                                                catch (e: Exception) { toast = ApiClient.errorMessage(e) }
                                                finally { busy = false }
                                            }
                                        },
                                )
                            }
                        }
                    }
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedButton(onClick = { startCamera() }, enabled = !busy) {
                            Icon(Icons.Filled.AddAPhoto, null, Modifier.size(18.dp)); Spacer(Modifier.width(6.dp)); Text("Caméra")
                        }
                        OutlinedButton(onClick = { pickImage.launch("image/*") }, enabled = !busy) {
                            Icon(Icons.Filled.PhotoLibrary, null, Modifier.size(18.dp)); Spacer(Modifier.width(6.dp)); Text("Galerie")
                        }
                    }
                }

                HorizontalDivider()

                // ---- Fields ----
                OutlinedTextField(value = nameFr, onValueChange = { nameFr = it }, label = { Text("Nom *") }, modifier = Modifier.fillMaxWidth(), singleLine = true)
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    OutlinedTextField(value = sku, onValueChange = { sku = it }, label = { Text("Référence") }, modifier = Modifier.weight(1f), singleLine = true)
                    OutlinedTextField(value = brand, onValueChange = { brand = it }, label = { Text("Marque") }, modifier = Modifier.weight(1f), singleLine = true)
                }
                CategoryPicker(categories, categoryId) { categoryId = it }
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    OutlinedTextField(value = price, onValueChange = { price = it }, label = { Text("Prix *") }, modifier = Modifier.weight(1f), singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal))
                    OutlinedTextField(value = compareAt, onValueChange = { compareAt = it }, label = { Text("Prix barré") }, modifier = Modifier.weight(1f), singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal))
                }
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    OutlinedTextField(value = wholesale, onValueChange = { wholesale = it }, label = { Text("Prix gros") }, modifier = Modifier.weight(1f), singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal))
                    OutlinedTextField(value = stock, onValueChange = { stock = it }, label = { Text("Stock") }, modifier = Modifier.weight(1f), singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number))
                }
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Switch(checked = trackStock, onCheckedChange = { trackStock = it }); Spacer(Modifier.width(8.dp)); Text("Suivre le stock", fontSize = 14.sp)
                    Spacer(Modifier.width(20.dp))
                    Switch(checked = active, onCheckedChange = { active = it }); Spacer(Modifier.width(8.dp)); Text("Visible", fontSize = 14.sp)
                }
                OutlinedTextField(value = shortDesc, onValueChange = { shortDesc = it }, label = { Text("Description courte") }, modifier = Modifier.fillMaxWidth())

                HorizontalDivider()

                // ---- Variants ----
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("Couleurs / Tailles", fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                    TextButton(onClick = { variants.add(EditableVariant()) }) { Text("+ Ajouter") }
                }
                variants.forEachIndexed { idx, v ->
                    Card {
                        Column(Modifier.padding(10.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                                // Current colour dot → opens the full picker (recent +
                                // photo-suggested + palette + custom hue/shade).
                                Box(
                                    Modifier.size(38.dp).clip(CircleShape)
                                        .background(parseHex(v.hex) ?: MaterialTheme.colorScheme.surfaceVariant)
                                        .clickable { pickingColorFor = idx },
                                )
                                OutlinedButton(onClick = { pickingColorFor = idx }) { Text("🎨 Couleur") }
                                Spacer(Modifier.weight(1f))
                                Text("✕", color = MaterialTheme.colorScheme.error, modifier = Modifier.clickable { variants.removeAt(idx) }.padding(4.dp))
                            }
                            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                OutlinedTextField(value = v.color, onValueChange = { variants[idx] = v.copy(color = it) }, label = { Text("Nom couleur") }, modifier = Modifier.weight(1.2f), singleLine = true)
                                OutlinedTextField(value = v.size, onValueChange = { variants[idx] = v.copy(size = it) }, label = { Text("Taille") }, modifier = Modifier.weight(1f), singleLine = true)
                                OutlinedTextField(value = v.stock, onValueChange = { variants[idx] = v.copy(stock = it) }, label = { Text("Stock") }, modifier = Modifier.weight(0.8f), singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number))
                            }
                        }
                    }
                }
                Spacer(Modifier.height(4.dp))
            }
        }
    }

    // Colour picker for the tapped variant row.
    pickingColorFor?.let { idx ->
        if (idx < variants.size) {
            ColorPickerDialog(
                initialHex = variants[idx].hex.ifBlank { null },
                suggested = suggestedColors,
                onPick = { hex, name ->
                    val row = variants[idx]
                    variants[idx] = row.copy(hex = hex, color = row.color.ifBlank { name })
                    pickingColorFor = null
                },
                onDismiss = { pickingColorFor = null },
            )
        } else pickingColorFor = null
    }
}

data class EditableVariant(
    val id: Long = 0, val color: String = "", val hex: String = "",
    val size: String = "", val stock: String = "", val delta: String = "",
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun CategoryPicker(categories: List<CategoryInfo>, selected: Long?, onPick: (Long?) -> Unit) {
    var open by remember { mutableStateOf(false) }
    val label = categories.firstOrNull { it.id == selected }?.name ?: "— Catégorie —"
    ExposedDropdownMenuBox(expanded = open, onExpandedChange = { open = it }) {
        OutlinedTextField(
            value = label, onValueChange = {}, readOnly = true, label = { Text("Catégorie") },
            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(open) },
            modifier = Modifier.fillMaxWidth().menuAnchor(MenuAnchorType.PrimaryNotEditable),
        )
        ExposedDropdownMenu(expanded = open, onDismissRequest = { open = false }) {
            DropdownMenuItem(text = { Text("— Aucune —") }, onClick = { onPick(null); open = false })
            categories.forEach { c ->
                DropdownMenuItem(text = { Text(c.name) }, onClick = { onPick(c.id); open = false })
            }
        }
    }
}

fun trimNum(v: Double): String = if (v == v.toLong().toDouble()) v.toLong().toString() else v.toString()

/**
 * Compress ON the phone before uploading (longest edge ≤ 2000px, JPEG 85 —
 * same targets as the server's ImageOptimizer): a raw 6-8MB camera shot
 * becomes a few hundred KB, which matters a lot on 3G/4G.
 */
fun uriToPart(ctx: Context, uri: Uri): MultipartBody.Part {
    val dir = File(ctx.cacheDir, "photos").apply { mkdirs() }
    val file = File(dir, "upload-${System.currentTimeMillis()}.jpg")

    // Pass 1: bounds only, to pick a power-of-two sample size (memory-safe).
    val bounds = android.graphics.BitmapFactory.Options().apply { inJustDecodeBounds = true }
    ctx.contentResolver.openInputStream(uri).use { android.graphics.BitmapFactory.decodeStream(it, null, bounds) }
    var sample = 1
    while ((bounds.outWidth / sample).coerceAtLeast(bounds.outHeight / sample) > 4000) sample *= 2

    val opts = android.graphics.BitmapFactory.Options().apply { inSampleSize = sample }
    val raw = ctx.contentResolver.openInputStream(uri).use {
        android.graphics.BitmapFactory.decodeStream(it, null, opts)
    }

    if (raw == null) {
        // Undecodable? Send the original bytes; the server optimizer still runs.
        ctx.contentResolver.openInputStream(uri).use { input ->
            file.outputStream().use { out -> input!!.copyTo(out) }
        }
    } else {
        val maxEdge = 2000f
        val scale = minOf(1f, maxEdge / maxOf(raw.width, raw.height))
        val bmp = if (scale < 1f) android.graphics.Bitmap.createScaledBitmap(
            raw, (raw.width * scale).toInt(), (raw.height * scale).toInt(), true) else raw
        file.outputStream().use { out -> bmp.compress(android.graphics.Bitmap.CompressFormat.JPEG, 85, out) }
        if (bmp !== raw) bmp.recycle()
        raw.recycle()
    }

    return MultipartBody.Part.createFormData(
        "image", file.name, file.asRequestBody("image/jpeg".toMediaType())
    )
}
