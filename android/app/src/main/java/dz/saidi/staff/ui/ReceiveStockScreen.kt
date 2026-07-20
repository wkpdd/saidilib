package dz.saidi.staff.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.google.mlkit.vision.codescanner.GmsBarcodeScanning
import dz.saidi.staff.api.*
import kotlinx.coroutines.launch

data class ReceiveLine(val product: ProductBrief, var qty: Int = 1, var cost: String = "")

/** Bon de réception by scanning: each line's quantity lands in stock on submit. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ReceiveStockScreen(onBack: () -> Unit) {
    val lines = remember { mutableStateListOf<ReceiveLine>() }
    var suppliers by remember { mutableStateOf(listOf<SupplierInfo>()) }
    var supplier by remember { mutableStateOf<SupplierInfo?>(null) }
    var note by remember { mutableStateOf("") }
    var showSearch by remember { mutableStateOf(false) }
    var busy by remember { mutableStateOf(false) }
    var toast by remember { mutableStateOf<String?>(null) }
    var doneRef by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()
    val ctx = LocalContext.current
    val snackbar = remember { SnackbarHostState() }

    LaunchedEffect(Unit) {
        try { suppliers = ApiClient.service.suppliers().suppliers } catch (_: Exception) {}
    }
    LaunchedEffect(toast) { toast?.let { snackbar.showSnackbar(it); toast = null } }

    fun addProduct(p: ProductBrief) {
        val existing = lines.indexOfFirst { it.product.id == p.id }
        if (existing >= 0) lines[existing] = lines[existing].copy(qty = lines[existing].qty + 1)
        else lines.add(ReceiveLine(product = p))
    }

    fun scan() {
        GmsBarcodeScanning.getClient(ctx).startScan()
            .addOnSuccessListener { barcode ->
                val code = barcode.rawValue ?: return@addOnSuccessListener
                scope.launch {
                    try {
                        val res = ApiClient.service.lookup(code)
                        if (res.found && res.product != null) { addProduct(res.product); toast = "✅ ${res.product.displayName}" }
                        else toast = "Référence inconnue: $code"
                    } catch (e: Exception) { toast = ApiClient.errorMessage(e) }
                }
            }
            .addOnFailureListener { toast = "Scanner indisponible" }
    }

    fun submit() {
        if (lines.isEmpty()) { toast = "Ajoutez au moins un article."; return }
        busy = true
        scope.launch {
            try {
                val res = ApiClient.service.createReceipt(CreateReceiptRequest(
                    supplierId = supplier?.id, note = note.ifBlank { null },
                    items = lines.map { ReceiptLineRequest(it.product.id, null, it.qty, it.cost.replace(',', '.').toDoubleOrNull()) },
                ))
                doneRef = res.receipt.reference
            } catch (e: Exception) { toast = ApiClient.errorMessage(e) }
            finally { busy = false }
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Réception de stock", fontWeight = FontWeight.Bold) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Retour") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
        bottomBar = {
            Surface(shadowElevation = 8.dp) {
                Button(
                    onClick = { submit() }, enabled = !busy && lines.isNotEmpty(),
                    modifier = Modifier.fillMaxWidth().padding(16.dp).height(50.dp),
                ) { Text(if (busy) "Envoi…" else "📥 Réceptionner (${lines.sumOf { it.qty }} unités)", fontSize = 16.sp, fontWeight = FontWeight.Bold) }
            }
        },
    ) { pad ->
        Column(
            Modifier.fillMaxSize().padding(pad).verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("Articles reçus (${lines.size})", fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                OutlinedButton(onClick = { scan() }) { Icon(Icons.Filled.QrCodeScanner, null, Modifier.size(18.dp)); Spacer(Modifier.width(4.dp)); Text("Scanner") }
                Spacer(Modifier.width(8.dp))
                OutlinedButton(onClick = { showSearch = true }) { Icon(Icons.Filled.Search, null, Modifier.size(18.dp)) }
            }
            if (lines.isEmpty()) {
                Text("Scannez chaque produit reçu — les quantités s'ajoutent au stock.", fontSize = 13.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            lines.forEachIndexed { idx, line ->
                Card {
                    Column(Modifier.padding(10.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(line.product.displayName, fontWeight = FontWeight.SemiBold, fontSize = 13.sp, modifier = Modifier.weight(1f), maxLines = 2)
                            Text("✕", color = MaterialTheme.colorScheme.error, modifier = Modifier.padding(4.dp).clickable { lines.removeAt(idx) })
                        }
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedButton(onClick = { if (line.qty > 1) lines[idx] = line.copy(qty = line.qty - 1) }, contentPadding = PaddingValues(0.dp), modifier = Modifier.size(34.dp)) { Text("−") }
                            Text("${line.qty}", fontWeight = FontWeight.Bold)
                            OutlinedButton(onClick = { lines[idx] = line.copy(qty = line.qty + 1) }, contentPadding = PaddingValues(0.dp), modifier = Modifier.size(34.dp)) { Text("+") }
                            OutlinedTextField(
                                value = line.cost, onValueChange = { lines[idx] = line.copy(cost = it) },
                                label = { Text("Coût unitaire") }, singleLine = true,
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                                modifier = Modifier.weight(1f),
                            )
                        }
                    }
                }
            }

            HorizontalDivider()
            SupplierPicker(suppliers, supplier) { supplier = it }
            OutlinedTextField(value = note, onValueChange = { note = it }, label = { Text("Note (optionnel)") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(4.dp))
        }

        if (showSearch) ProductSearchDialog(onPick = { addProduct(it); showSearch = false }, onDismiss = { showSearch = false })

        doneRef?.let { ref ->
            AlertDialog(
                onDismissRequest = {},
                title = { Text("✅ Stock mis à jour") },
                text = { Text("Bon de réception $ref créé et réceptionné — les quantités ont été ajoutées au stock.") },
                confirmButton = { TextButton(onClick = { doneRef = null; lines.clear(); onBack() }) { Text("OK") } },
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun SupplierPicker(suppliers: List<SupplierInfo>, selected: SupplierInfo?, onPick: (SupplierInfo?) -> Unit) {
    var open by remember { mutableStateOf(false) }
    ExposedDropdownMenuBox(expanded = open, onExpandedChange = { open = it }) {
        OutlinedTextField(
            value = selected?.name ?: "— Fournisseur (optionnel) —", onValueChange = {}, readOnly = true,
            label = { Text("Fournisseur") },
            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(open) },
            modifier = Modifier.fillMaxWidth().menuAnchor(MenuAnchorType.PrimaryNotEditable),
        )
        ExposedDropdownMenu(expanded = open, onDismissRequest = { open = false }) {
            DropdownMenuItem(text = { Text("— Aucun —") }, onClick = { onPick(null); open = false })
            suppliers.forEach { s ->
                DropdownMenuItem(text = { Text(s.name) }, onClick = { onPick(s); open = false })
            }
        }
    }
}
