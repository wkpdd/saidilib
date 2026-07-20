package dz.saidi.staff.ui

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Add
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
import dz.saidi.staff.api.ApiClient
import dz.saidi.staff.api.ClientFull
import dz.saidi.staff.api.TransactionRequest
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ClientDetailScreen(id: Long, onBack: () -> Unit) {
    var client by remember { mutableStateOf<ClientFull?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var showAdd by remember { mutableStateOf(false) }
    var toast by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()
    val ctx = LocalContext.current
    val snackbar = remember { SnackbarHostState() }

    fun load() {
        scope.launch {
            try { client = ApiClient.service.client(id).client } catch (e: Exception) { error = ApiClient.errorMessage(e) }
        }
    }
    LaunchedEffect(id) { load() }
    LaunchedEffect(toast) { toast?.let { snackbar.showSnackbar(it); toast = null } }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(client?.name ?: "Client", fontWeight = FontWeight.Bold) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Retour") } },
            )
        },
        floatingActionButton = {
            ExtendedFloatingActionButton(onClick = { showAdd = true }) {
                Icon(Icons.Filled.Add, null); Spacer(Modifier.width(6.dp)); Text("Écriture")
            }
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { pad ->
        when {
            client == null && error != null -> ErrorBox(error!!, Modifier.padding(pad)) { error = null; load() }
            client == null -> Box(Modifier.fillMaxSize().padding(pad), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
            else -> {
                val c = client!!
                LazyColumn(Modifier.fillMaxSize().padding(pad), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    item {
                        Card {
                            Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Column(Modifier.weight(1f)) {
                                        Text(c.name, fontWeight = FontWeight.Bold, fontSize = 17.sp)
                                        Text(if (c.type == "wholesale") "💼 Grossiste" else "🛍️ Détail", fontSize = 13.sp)
                                    }
                                    Column(horizontalAlignment = Alignment.End) {
                                        Text(
                                            money(c.balance), fontWeight = FontWeight.Black, fontSize = 18.sp,
                                            color = if (c.isOverdue) MaterialTheme.colorScheme.error
                                            else if (c.balance > 0) statusColor("pending") else statusColor("delivered"),
                                        )
                                        Text("limite ${money(c.creditLimit)}", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                    }
                                }
                                if (c.isOverdue) {
                                    Text("⚠️ Limite de crédit dépassée", color = MaterialTheme.colorScheme.error, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                                }
                                c.commune?.let { Text("📍 $it", fontSize = 13.sp) }
                                c.notes?.takeIf { it.isNotBlank() }?.let { Text("📝 $it", fontSize = 13.sp) }
                                c.phone?.let { phone ->
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
                    }
                    item { Text("Historique (${c.transactions.size})", fontWeight = FontWeight.Bold) }
                    items(c.transactions) { t ->
                        Card {
                            Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                                Text(
                                    when (t.type) { "debt" -> "🔴"; "payment" -> "🟢"; else -> "🔵" },
                                    fontSize = 18.sp,
                                )
                                Spacer(Modifier.width(10.dp))
                                Column(Modifier.weight(1f)) {
                                    Text(
                                        when (t.type) { "debt" -> "Dette"; "payment" -> "Paiement"; else -> "Ajustement" } +
                                            (t.orderRef?.let { " · $it" } ?: ""),
                                        fontWeight = FontWeight.SemiBold, fontSize = 13.sp,
                                    )
                                    t.description?.let { Text(it, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant) }
                                    Text("${t.at.take(16).replace('T', ' ')} · ${t.author ?: ""}", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                }
                                Text(
                                    (if (t.type == "debt") "+" else "−") + money(t.amount),
                                    fontWeight = FontWeight.Bold,
                                    color = if (t.type == "debt") MaterialTheme.colorScheme.error else statusColor("delivered"),
                                )
                            }
                        }
                    }
                }

                if (showAdd) AddTransactionDialog(onSave = { type, amount, desc ->
                    showAdd = false
                    scope.launch {
                        try {
                            ApiClient.service.addTransaction(c.id, TransactionRequest(type, amount, desc))
                            toast = "✅ Écriture enregistrée"
                            load()
                        } catch (e: Exception) { toast = ApiClient.errorMessage(e) }
                    }
                }, onDismiss = { showAdd = false })
            }
        }
    }
}

@Composable
private fun AddTransactionDialog(onSave: (String, Double, String?) -> Unit, onDismiss: () -> Unit) {
    var type by remember { mutableStateOf("payment") }
    var amount by remember { mutableStateOf("") }
    var desc by remember { mutableStateOf("") }
    val types = mapOf("payment" to "🟢 Paiement reçu", "debt" to "🔴 Nouvelle dette", "adjustment" to "🔵 Ajustement")
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Nouvelle écriture") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                types.forEach { (k, v) ->
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        RadioButton(selected = type == k, onClick = { type = k }); Text(v)
                    }
                }
                OutlinedTextField(
                    value = amount, onValueChange = { amount = it }, label = { Text("Montant (DA)") },
                    singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                )
                OutlinedTextField(value = desc, onValueChange = { desc = it }, label = { Text("Description (optionnel)") }, singleLine = true)
            }
        },
        confirmButton = {
            TextButton(onClick = {
                amount.replace(',', '.').toDoubleOrNull()?.takeIf { it > 0 }?.let { onSave(type, it, desc.ifBlank { null }) }
            }) { Text("Enregistrer") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Annuler") } },
    )
}
