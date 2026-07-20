package dz.saidi.staff.ui

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Call
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import dz.saidi.staff.api.*
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OrderDetailScreen(id: Long, onBack: () -> Unit) {
    var order by remember { mutableStateOf<OrderFull?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var busy by remember { mutableStateOf(false) }
    var toast by remember { mutableStateOf<String?>(null) }
    var showStatus by remember { mutableStateOf(false) }
    var showPrices by remember { mutableStateOf(false) }
    var showDispatch by remember { mutableStateOf(false) }
    var showRefund by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val ctx = LocalContext.current
    val snackbar = remember { SnackbarHostState() }

    fun load() {
        scope.launch {
            try { order = ApiClient.service.order(id).order } catch (e: Exception) { error = ApiClient.errorMessage(e) }
        }
    }
    LaunchedEffect(id) { load() }
    LaunchedEffect(toast) { toast?.let { snackbar.showSnackbar(it); toast = null } }

    fun run(block: suspend () -> OrderResponse) {
        if (busy) return
        busy = true
        scope.launch {
            try {
                val res = block()
                order = res.order
                toast = res.message ?: "✅ Enregistré"
            } catch (e: Exception) { toast = ApiClient.errorMessage(e) }
            finally { busy = false }
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(order?.reference ?: "Commande", fontWeight = FontWeight.Bold) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Retour") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { pad ->
        when {
            order == null && error != null -> ErrorBox(error!!, Modifier.padding(pad)) { error = null; load() }
            order == null -> Box(Modifier.fillMaxSize().padding(pad), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
            else -> {
                val o = order!!
                Column(Modifier.fillMaxSize().padding(pad).verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {

                    // Status + date
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        StatusChip(o.status, o.statusLabel)
                        Spacer(Modifier.weight(1f))
                        Text(o.createdAt.take(16).replace('T', ' '), fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }

                    // Customer card with call/WhatsApp
                    Card {
                        Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                            Text(o.customerName, fontWeight = FontWeight.Bold, fontSize = 16.sp)
                            Text("${o.wilaya ?: "—"} · ${o.commune ?: ""}", fontSize = 13.sp)
                            o.address?.let { Text(it, fontSize = 13.sp, color = MaterialTheme.colorScheme.onSurfaceVariant) }
                            Text(if (o.deliveryType == "stopdesk") "🏢 Stop-desk" else "🏠 Domicile", fontSize = 13.sp)
                            o.notes?.takeIf { it.isNotBlank() }?.let { Text("📝 $it", fontSize = 13.sp) }
                            o.client?.let { Text("Client compte: ${it.name} (${it.type})", fontSize = 12.sp, color = MaterialTheme.colorScheme.primary) }
                            o.phone?.let { phone ->
                                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 6.dp)) {
                                    OutlinedButton(onClick = { ctx.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:$phone"))) }) {
                                        Icon(Icons.Filled.Call, null, Modifier.size(16.dp)); Spacer(Modifier.width(6.dp)); Text(phone)
                                    }
                                    OutlinedButton(onClick = {
                                        val intl = "213" + phone.replace(" ", "").removePrefix("0")
                                        ctx.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/$intl")))
                                    }) { Text("WhatsApp") }
                                }
                            }
                        }
                    }

                    // Items + totals
                    Card {
                        Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Text("Articles", fontWeight = FontWeight.Bold)
                            o.items.forEach { i ->
                                Row {
                                    Column(Modifier.weight(1f)) {
                                        Text(i.name, fontSize = 14.sp)
                                        i.variant?.let { Text(it, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant) }
                                    }
                                    Text("${i.quantity} × ${money(i.unitPrice)}", fontSize = 13.sp)
                                    Spacer(Modifier.width(10.dp))
                                    Text(money(i.lineTotal), fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                                }
                            }
                            HorizontalDivider()
                            TotalRow("Sous-total", o.subtotal)
                            TotalRow("Livraison", o.deliveryFee)
                            if (o.discount > 0) TotalRow("Remise", -o.discount)
                            Row {
                                Text("TOTAL", fontWeight = FontWeight.Black); Spacer(Modifier.weight(1f))
                                Text(money(o.total), fontWeight = FontWeight.Black, color = MaterialTheme.colorScheme.primary, fontSize = 17.sp)
                            }
                            o.refund?.let {
                                Text("↩️ Remboursé ${money(it.amount)} (${it.method ?: ""})", color = MaterialTheme.colorScheme.error, fontSize = 13.sp)
                            }
                        }
                    }

                    // Delivery info
                    if (o.tracking != null || o.provider != null) {
                        Card {
                            Column(Modifier.padding(14.dp)) {
                                Text("Expédition", fontWeight = FontWeight.Bold)
                                Text("Transporteur: ${o.provider ?: "—"} · Suivi: ${o.tracking ?: "—"}", fontSize = 13.sp)
                            }
                        }
                    }

                    // Price-change history
                    if (o.adjustments.isNotEmpty()) {
                        Card {
                            Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                Text("Historique des prix", fontWeight = FontWeight.Bold)
                                o.adjustments.forEach { a ->
                                    Text(
                                        "${a.label ?: ""}: ${money(a.oldPrice)} → ${money(a.newPrice)} · ${a.author ?: ""}${a.reason?.let { " ($it)" } ?: ""}",
                                        fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    )
                                }
                            }
                        }
                    }

                    // Actions
                    Text("Actions", fontWeight = FontWeight.Bold)
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Button(onClick = { showStatus = true }, Modifier.weight(1f), enabled = !busy) { Text("Statut") }
                        if (o.isEditable) {
                            OutlinedButton(onClick = { showPrices = true }, Modifier.weight(1f), enabled = !busy) { Text("Prix") }
                        }
                    }
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        if (o.dispatchedAt == null && o.status !in listOf("cancelled", "returned", "delivered")) {
                            OutlinedButton(onClick = { showDispatch = true }, Modifier.weight(1f), enabled = !busy) { Text("🚚 Expédier (Noest)") }
                        }
                        if (o.refund == null && o.status != "pending") {
                            OutlinedButton(
                                onClick = { showRefund = true }, Modifier.weight(1f), enabled = !busy,
                                colors = ButtonDefaults.outlinedButtonColors(contentColor = MaterialTheme.colorScheme.error),
                            ) { Text("↩️ Rembourser") }
                        }
                    }
                    Spacer(Modifier.height(12.dp))
                }

                // ---- Dialogs ----
                if (showStatus) StatusDialog(o.status, onPick = { s ->
                    showStatus = false
                    run { ApiClient.service.updateOrderStatus(o.id, StatusRequest(s)) }
                }, onDismiss = { showStatus = false })

                if (showPrices) PriceDialog(o, onSave = { prices, reason ->
                    showPrices = false
                    run { ApiClient.service.editOrderPrices(o.id, PriceEditRequest(prices.mapKeys { it.key.toString() }.mapValues { PriceItem(it.value) }, reason)) }
                }, onDismiss = { showPrices = false })

                if (showDispatch) DispatchDialog(onSend = { tracking ->
                    showDispatch = false
                    run { ApiClient.service.dispatchOrder(o.id, DispatchRequest("noest", tracking?.ifBlank { null })) }
                }, onDismiss = { showDispatch = false })

                if (showRefund) RefundDialog(o.total - (o.refund?.amount ?: 0.0), onConfirm = { amount, method, reason ->
                    showRefund = false
                    run { ApiClient.service.refundOrder(o.id, RefundRequest(amount, method, reason?.ifBlank { null })) }
                }, onDismiss = { showRefund = false })
            }
        }
    }
}

@Composable
private fun TotalRow(label: String, value: Double) {
    Row { Text(label, fontSize = 13.sp); Spacer(Modifier.weight(1f)); Text(money(value), fontSize = 13.sp) }
}

@Composable
private fun StatusDialog(current: String, onPick: (String) -> Unit, onDismiss: () -> Unit) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Changer le statut") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                STATUS_LABELS.forEach { (key, label) ->
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        RadioButton(selected = key == current, onClick = { onPick(key) })
                        Text(label, Modifier.weight(1f))
                        StatusChip(key, label)
                    }
                }
            }
        },
        confirmButton = {},
        dismissButton = { TextButton(onClick = onDismiss) { Text("Annuler") } },
    )
}

@Composable
private fun PriceDialog(o: OrderFull, onSave: (Map<Long, Double>, String?) -> Unit, onDismiss: () -> Unit) {
    val prices = remember { mutableStateMapOf<Long, String>().apply { o.items.forEach { put(it.id, "%.0f".format(it.unitPrice)) } } }
    var reason by remember { mutableStateOf("") }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Modifier les prix") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                o.items.forEach { i ->
                    OutlinedTextField(
                        value = prices[i.id] ?: "",
                        onValueChange = { prices[i.id] = it },
                        label = { Text("${i.name} (×${i.quantity})", maxLines = 1) },
                        singleLine = true,
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                    )
                }
                OutlinedTextField(value = reason, onValueChange = { reason = it }, label = { Text("Raison (optionnel)") }, singleLine = true)
                Text("Chaque changement est enregistré dans l'historique.", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        },
        confirmButton = {
            TextButton(onClick = {
                val parsed = prices.mapNotNull { (id, v) -> v.replace(',', '.').toDoubleOrNull()?.let { id to it } }.toMap()
                onSave(parsed, reason.ifBlank { null })
            }) { Text("Enregistrer") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Annuler") } },
    )
}

@Composable
private fun DispatchDialog(onSend: (String?) -> Unit, onDismiss: () -> Unit) {
    var tracking by remember { mutableStateOf("") }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Expédier via Noest") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text("La commande sera créée chez Noest avec les informations du client.", fontSize = 13.sp)
                OutlinedTextField(value = tracking, onValueChange = { tracking = it }, label = { Text("N° de suivi (auto si vide)") }, singleLine = true)
            }
        },
        confirmButton = { TextButton(onClick = { onSend(tracking) }) { Text("Expédier") } },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Annuler") } },
    )
}

@Composable
private fun RefundDialog(max: Double, onConfirm: (Double, String, String?) -> Unit, onDismiss: () -> Unit) {
    var amount by remember { mutableStateOf("%.0f".format(max)) }
    var method by remember { mutableStateOf("cash") }
    var reason by remember { mutableStateOf("") }
    val methods = mapOf("cash" to "Espèces", "store_credit" to "Avoir (crédit client)", "delivery" to "Via le livreur")
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Rembourser") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(
                    value = amount, onValueChange = { amount = it },
                    label = { Text("Montant (max ${money(max)})") }, singleLine = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                )
                methods.forEach { (k, v) ->
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        RadioButton(selected = method == k, onClick = { method = k }); Text(v)
                    }
                }
                OutlinedTextField(value = reason, onValueChange = { reason = it }, label = { Text("Raison (optionnel)") }, singleLine = true)
            }
        },
        confirmButton = {
            TextButton(onClick = {
                amount.replace(',', '.').toDoubleOrNull()?.let { onConfirm(it, method, reason) }
            }) { Text("Confirmer") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Annuler") } },
    )
}
