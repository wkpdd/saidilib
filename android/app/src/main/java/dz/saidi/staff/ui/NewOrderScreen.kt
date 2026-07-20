package dz.saidi.staff.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
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
import dz.saidi.staff.data.Session
import kotlinx.coroutines.launch

data class CartLine(
    val product: ProductBrief,
    val variant: VariantFull? = null,
    var qty: Int = 1,
    var unitPrice: Double,
)

/** Staff order entry: scan/search products, customer + wilaya, live totals. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun NewOrderScreen(onDone: (Long) -> Unit, onBack: () -> Unit) {
    val lines = remember { mutableStateListOf<CartLine>() }
    var customerName by remember { mutableStateOf("") }
    var phone by remember { mutableStateOf("") }
    var commune by remember { mutableStateOf("") }
    var address by remember { mutableStateOf("") }
    var notes by remember { mutableStateOf("") }
    var deliveryType by remember { mutableStateOf("stopdesk") }
    var wilayas by remember { mutableStateOf(listOf<WilayaInfo>()) }
    var wilaya by remember { mutableStateOf<WilayaInfo?>(null) }
    var clientId by remember { mutableStateOf<Long?>(null) }
    var clientName by remember { mutableStateOf<String?>(null) }
    var showSearch by remember { mutableStateOf(false) }
    var showClientSearch by remember { mutableStateOf(false) }
    var busy by remember { mutableStateOf(false) }
    var toast by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()
    val ctx = LocalContext.current
    val snackbar = remember { SnackbarHostState() }

    LaunchedEffect(Unit) {
        try { wilayas = ApiClient.service.wilayas().wilayas } catch (e: Exception) { toast = ApiClient.errorMessage(e) }
    }
    LaunchedEffect(toast) { toast?.let { snackbar.showSnackbar(it); toast = null } }

    fun addProduct(p: ProductBrief) {
        val existing = lines.indexOfFirst { it.product.id == p.id && it.variant == null }
        if (existing >= 0) lines[existing] = lines[existing].copy(qty = lines[existing].qty + 1)
        else lines.add(CartLine(product = p, unitPrice = p.price))
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

    val subtotal = lines.sumOf { it.unitPrice * it.qty }
    val fee = wilaya?.let { if (deliveryType == "home") it.homeFee else it.stopdeskFee } ?: 0.0
    val total = subtotal + fee

    fun submit() {
        if (lines.isEmpty()) { toast = "Ajoutez au moins un article."; return }
        if (customerName.isBlank() || phone.isBlank() || wilaya == null) { toast = "Client, téléphone et wilaya sont obligatoires."; return }
        busy = true
        scope.launch {
            try {
                val res = ApiClient.service.createOrder(CreateOrderRequest(
                    customerName = customerName.trim(), phone = phone.trim(),
                    wilayaId = wilaya!!.id, commune = commune.ifBlank { null },
                    address = address.ifBlank { null }, deliveryType = deliveryType,
                    clientId = clientId, notes = notes.ifBlank { null },
                    items = lines.map { OrderLineRequest(it.product.id, it.variant?.id, it.qty, it.unitPrice) },
                ))
                onDone(res.order.id)
            } catch (e: Exception) { toast = ApiClient.errorMessage(e); busy = false }
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Nouvelle commande", fontWeight = FontWeight.Bold) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Retour") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
        bottomBar = {
            Surface(shadowElevation = 8.dp) {
                Column(Modifier.padding(16.dp)) {
                    Row { Text("Sous-total"); Spacer(Modifier.weight(1f)); Text(money(subtotal)) }
                    Row { Text("Livraison"); Spacer(Modifier.weight(1f)); Text(money(fee)) }
                    Row { Text("TOTAL", fontWeight = FontWeight.Black); Spacer(Modifier.weight(1f)); Text(money(total), fontWeight = FontWeight.Black, color = MaterialTheme.colorScheme.primary) }
                    Spacer(Modifier.height(8.dp))
                    Button(onClick = { submit() }, enabled = !busy, modifier = Modifier.fillMaxWidth().height(50.dp)) {
                        Text(if (busy) "Envoi…" else "🧾 Créer la commande", fontSize = 16.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }
        },
    ) { pad ->
        Column(
            Modifier.fillMaxSize().padding(pad).verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            // ---- Items ----
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("Articles (${lines.size})", fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                OutlinedButton(onClick = { scan() }) { Icon(Icons.Filled.QrCodeScanner, null, Modifier.size(18.dp)); Spacer(Modifier.width(4.dp)); Text("Scanner") }
                Spacer(Modifier.width(8.dp))
                OutlinedButton(onClick = { showSearch = true }) { Icon(Icons.Filled.Search, null, Modifier.size(18.dp)); Spacer(Modifier.width(4.dp)); Text("Chercher") }
            }
            if (lines.isEmpty()) {
                Text("Scannez un code-barres ou cherchez un produit pour commencer.", fontSize = 13.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
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
                                value = trimNum(line.unitPrice),
                                onValueChange = { it.replace(',', '.').toDoubleOrNull()?.let { p -> lines[idx] = line.copy(unitPrice = p) } },
                                label = { Text("PU") }, singleLine = true,
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                                modifier = Modifier.weight(1f),
                            )
                            Text(money(line.unitPrice * line.qty), fontWeight = FontWeight.Bold, fontSize = 13.sp)
                        }
                    }
                }
            }

            HorizontalDivider()

            // ---- Customer ----
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("Client", fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                if (Session.can("clients")) {
                    TextButton(onClick = { showClientSearch = true }) {
                        Text(clientName?.let { "💳 $it" } ?: "Lier un compte client")
                    }
                }
            }
            OutlinedTextField(value = customerName, onValueChange = { customerName = it }, label = { Text("Nom du client *") }, modifier = Modifier.fillMaxWidth(), singleLine = true)
            OutlinedTextField(value = phone, onValueChange = { phone = it }, label = { Text("Téléphone *") }, modifier = Modifier.fillMaxWidth(), singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone))

            WilayaPicker(wilayas, wilaya) { wilaya = it }
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                FilterChip(selected = deliveryType == "stopdesk", onClick = { deliveryType = "stopdesk" }, label = { Text("🏢 Stop-desk") })
                FilterChip(selected = deliveryType == "home", onClick = { deliveryType = "home" }, label = { Text("🏠 Domicile") })
            }
            OutlinedTextField(value = commune, onValueChange = { commune = it }, label = { Text("Commune") }, modifier = Modifier.fillMaxWidth(), singleLine = true)
            if (deliveryType == "home") {
                OutlinedTextField(value = address, onValueChange = { address = it }, label = { Text("Adresse") }, modifier = Modifier.fillMaxWidth())
            }
            OutlinedTextField(value = notes, onValueChange = { notes = it }, label = { Text("Notes") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(4.dp))
        }

        if (showSearch) ProductSearchDialog(onPick = { addProduct(it); showSearch = false }, onDismiss = { showSearch = false })
        if (showClientSearch) ClientSearchDialog(onPick = { c ->
            clientId = c.id; clientName = c.name
            if (customerName.isBlank()) customerName = c.name
            if (phone.isBlank()) phone = c.phone ?: ""
            showClientSearch = false
        }, onDismiss = { showClientSearch = false })
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun WilayaPicker(wilayas: List<WilayaInfo>, selected: WilayaInfo?, onPick: (WilayaInfo) -> Unit) {
    var open by remember { mutableStateOf(false) }
    ExposedDropdownMenuBox(expanded = open, onExpandedChange = { open = it }) {
        OutlinedTextField(
            value = selected?.label ?: "— Wilaya * —", onValueChange = {}, readOnly = true, label = { Text("Wilaya") },
            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(open) },
            modifier = Modifier.fillMaxWidth().menuAnchor(MenuAnchorType.PrimaryNotEditable),
        )
        ExposedDropdownMenu(expanded = open, onDismissRequest = { open = false }) {
            wilayas.forEach { w ->
                DropdownMenuItem(
                    text = { Text("${w.label}  (🏢 ${trimNum(w.stopdeskFee)} / 🏠 ${trimNum(w.homeFee)} DA)", fontSize = 13.sp) },
                    onClick = { onPick(w); open = false },
                )
            }
        }
    }
}

@Composable
fun ProductSearchDialog(onPick: (ProductBrief) -> Unit, onDismiss: () -> Unit) {
    var query by remember { mutableStateOf("") }
    var results by remember { mutableStateOf(listOf<ProductBrief>()) }
    val scope = rememberCoroutineScope()
    LaunchedEffect(query) {
        if (query.length >= 2) {
            try { results = ApiClient.service.products(query).products } catch (_: Exception) {}
        }
    }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Chercher un produit") },
        text = {
            Column {
                OutlinedTextField(value = query, onValueChange = { query = it }, label = { Text("Nom, référence…") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(8.dp))
                LazyColumn(Modifier.heightIn(max = 300.dp)) {
                    items(results) { p ->
                        ListItem(
                            headlineContent = { Text(p.displayName, fontSize = 14.sp) },
                            supportingContent = { Text(money(p.price), fontSize = 12.sp) },
                            modifier = Modifier.clickable { onPick(p) },
                        )
                    }
                }
            }
        },
        confirmButton = {},
        dismissButton = { TextButton(onClick = onDismiss) { Text("Fermer") } },
    )
}

@Composable
private fun ClientSearchDialog(onPick: (ClientBrief) -> Unit, onDismiss: () -> Unit) {
    var query by remember { mutableStateOf("") }
    var results by remember { mutableStateOf(listOf<ClientBrief>()) }
    LaunchedEffect(query) {
        try { results = ApiClient.service.clients(query.ifBlank { null }).clients } catch (_: Exception) {}
    }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Lier un compte client") },
        text = {
            Column {
                OutlinedTextField(value = query, onValueChange = { query = it }, label = { Text("Nom, téléphone…") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(8.dp))
                LazyColumn(Modifier.heightIn(max = 300.dp)) {
                    items(results) { c ->
                        ListItem(
                            headlineContent = { Text(c.name, fontSize = 14.sp) },
                            supportingContent = { Text("${c.phone ?: ""} · solde ${money(c.balance)}", fontSize = 12.sp) },
                            modifier = Modifier.clickable { onPick(c) },
                        )
                    }
                }
            }
        },
        confirmButton = {},
        dismissButton = { TextButton(onClick = onDismiss) { Text("Fermer") } },
    )
}
