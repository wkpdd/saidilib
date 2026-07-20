package dz.saidi.staff.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.google.mlkit.vision.codescanner.GmsBarcodeScanning
import dz.saidi.staff.api.ApiClient
import dz.saidi.staff.api.ProductBrief
import dz.saidi.staff.api.QuickUpdateRequest
import kotlinx.coroutines.FlowPreview
import kotlinx.coroutines.flow.debounce
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class, FlowPreview::class)
@Composable
fun ProductsScreen() {
    var products by remember { mutableStateOf(listOf<ProductBrief>()) }
    var search by remember { mutableStateOf("") }
    var lowStockOnly by remember { mutableStateOf(false) }
    var page by remember { mutableStateOf(1) }
    var hasMore by remember { mutableStateOf(false) }
    var loading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    var editing by remember { mutableStateOf<ProductBrief?>(null) }
    var toast by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()
    val ctx = LocalContext.current
    val snackbar = remember { SnackbarHostState() }

    fun load(reset: Boolean = true) {
        if (reset) page = 1
        loading = true; error = null
        scope.launch {
            try {
                val res = ApiClient.service.products(search.ifBlank { null }, if (lowStockOnly) true else null, page)
                products = if (reset) res.products else products + res.products
                hasMore = res.hasMore
            } catch (e: Exception) { error = ApiClient.errorMessage(e) }
            finally { loading = false }
        }
    }

    LaunchedEffect(lowStockOnly) { load() }
    LaunchedEffect(Unit) { snapshotFlow { search }.debounce(400).collect { load() } }
    LaunchedEffect(toast) { toast?.let { snackbar.showSnackbar(it); toast = null } }

    fun scan() {
        GmsBarcodeScanning.getClient(ctx).startScan()
            .addOnSuccessListener { barcode ->
                val code = barcode.rawValue ?: return@addOnSuccessListener
                scope.launch {
                    try {
                        val res = ApiClient.service.lookup(code)
                        if (res.found && res.product != null) {
                            editing = res.product
                        } else {
                            search = code
                            toast = "Aucun produit avec la référence « $code » — recherche lancée."
                        }
                    } catch (e: Exception) { toast = ApiClient.errorMessage(e) }
                }
            }
            .addOnFailureListener { toast = "Scanner indisponible: ${it.message}" }
    }

    Scaffold(
        topBar = { TopAppBar(title = { Text("Produits", fontWeight = FontWeight.Bold) }) },
        floatingActionButton = {
            ExtendedFloatingActionButton(onClick = { scan() }) {
                Icon(Icons.Filled.QrCodeScanner, null); Spacer(Modifier.width(8.dp)); Text("Scanner")
            }
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { pad ->
        Column(Modifier.fillMaxSize().padding(pad)) {
            OutlinedTextField(
                value = search, onValueChange = { search = it },
                placeholder = { Text("Nom, référence, marque…") },
                leadingIcon = { Icon(Icons.Filled.Search, null) },
                singleLine = true,
                modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
            )
            Row(Modifier.padding(horizontal = 16.dp)) {
                FilterChip(selected = lowStockOnly, onClick = { lowStockOnly = !lowStockOnly },
                    label = { Text("⚠️ Stock bas uniquement") })
            }

            when {
                loading && products.isEmpty() -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
                error != null && products.isEmpty() -> ErrorBox(error!!) { load() }
                products.isEmpty() -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Text("Aucun produit", color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                else -> LazyColumn(contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    items(products) { p ->
                        Card(Modifier.fillMaxWidth().clickable { editing = p }) {
                            Row(Modifier.padding(10.dp), verticalAlignment = Alignment.CenterVertically) {
                                AsyncImage(
                                    model = p.image, contentDescription = null,
                                    modifier = Modifier.size(52.dp).clip(RoundedCornerShape(10.dp)),
                                )
                                Spacer(Modifier.width(10.dp))
                                Column(Modifier.weight(1f)) {
                                    Text(p.displayName, fontWeight = FontWeight.SemiBold, fontSize = 14.sp, maxLines = 2)
                                    Text(p.category ?: "—", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                }
                                Column(horizontalAlignment = Alignment.End) {
                                    Text(money(p.price), fontWeight = FontWeight.Bold, fontSize = 14.sp)
                                    val stockColor = when {
                                        !p.trackStock -> MaterialTheme.colorScheme.onSurfaceVariant
                                        p.stock <= 0 -> MaterialTheme.colorScheme.error
                                        p.stock <= 3 -> statusColor("pending")
                                        else -> statusColor("delivered")
                                    }
                                    Text(if (p.trackStock) "Stock: ${p.stock}" else "Stock: ∞", fontSize = 12.sp, color = stockColor)
                                    if (!p.isActive) Text("Masqué", fontSize = 11.sp, color = MaterialTheme.colorScheme.error)
                                }
                            }
                        }
                    }
                    if (hasMore) {
                        item {
                            OutlinedButton(onClick = { page += 1; load(reset = false) }, modifier = Modifier.fillMaxWidth(), enabled = !loading) {
                                Text(if (loading) "Chargement…" else "Charger plus")
                            }
                        }
                    }
                }
            }
        }

        editing?.let { p ->
            QuickEditDialog(p, onSave = { req ->
                scope.launch {
                    try {
                        val updated = ApiClient.service.quickUpdateProduct(p.id, req).product
                        products = products.map { if (it.id == updated.id) updated else it }
                        toast = "✅ ${updated.name} mis à jour"
                    } catch (e: Exception) { toast = ApiClient.errorMessage(e) }
                }
                editing = null
            }, onDismiss = { editing = null })
        }
    }
}

@Composable
private fun QuickEditDialog(p: ProductBrief, onSave: (QuickUpdateRequest) -> Unit, onDismiss: () -> Unit) {
    var price by remember { mutableStateOf("%.0f".format(p.price)) }
    var stock by remember { mutableStateOf(p.stock.toString()) }
    var active by remember { mutableStateOf(p.isActive) }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(p.displayName, fontSize = 16.sp) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                OutlinedTextField(
                    value = price, onValueChange = { price = it }, label = { Text("Prix (DA)") },
                    singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                )
                OutlinedTextField(
                    value = stock, onValueChange = { stock = it }, label = { Text("Stock") },
                    singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                )
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Switch(checked = active, onCheckedChange = { active = it })
                    Spacer(Modifier.width(8.dp))
                    Text(if (active) "Visible en boutique" else "Masqué de la boutique")
                }
            }
        },
        confirmButton = {
            TextButton(onClick = {
                onSave(QuickUpdateRequest(
                    price = price.replace(',', '.').toDoubleOrNull(),
                    stock = stock.toIntOrNull(),
                    isActive = active,
                ))
            }) { Text("Enregistrer") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Annuler") } },
    )
}
