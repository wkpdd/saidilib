package dz.saidi.staff.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import dz.saidi.staff.api.ApiClient
import dz.saidi.staff.api.ClientBrief
import kotlinx.coroutines.FlowPreview
import kotlinx.coroutines.flow.debounce
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class, FlowPreview::class)
@Composable
fun ClientsScreen(onOpen: (Long) -> Unit) {
    var clients by remember { mutableStateOf(listOf<ClientBrief>()) }
    var search by remember { mutableStateOf("") }
    var page by remember { mutableStateOf(1) }
    var hasMore by remember { mutableStateOf(false) }
    var loading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    fun load(reset: Boolean = true) {
        if (reset) page = 1
        loading = true; error = null
        scope.launch {
            try {
                val res = ApiClient.service.clients(search.ifBlank { null }, page)
                clients = if (reset) res.clients else clients + res.clients
                hasMore = res.hasMore
            } catch (e: Exception) { error = ApiClient.errorMessage(e) }
            finally { loading = false }
        }
    }

    LaunchedEffect(Unit) { snapshotFlow { search }.debounce(400).collect { load() } }

    Scaffold(topBar = { TopAppBar(title = { Text("Clients & dettes", fontWeight = FontWeight.Bold) }) }) { pad ->
        Column(Modifier.fillMaxSize().padding(pad)) {
            OutlinedTextField(
                value = search, onValueChange = { search = it },
                placeholder = { Text("Nom, téléphone, email…") },
                leadingIcon = { Icon(Icons.Filled.Search, null) },
                singleLine = true,
                modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
            )

            when {
                loading && clients.isEmpty() -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
                error != null && clients.isEmpty() -> ErrorBox(error!!) { load() }
                clients.isEmpty() -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Text("Aucun client", color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                else -> LazyColumn(contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    items(clients) { c ->
                        Card(Modifier.fillMaxWidth().clickable { onOpen(c.id) }) {
                            Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                                Column(Modifier.weight(1f)) {
                                    Text(c.name, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                                    Text(
                                        "${if (c.type == "wholesale") "💼 Grossiste" else "🛍️ Détail"} · ${c.ordersCount} commandes",
                                        fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    )
                                }
                                Column(horizontalAlignment = Alignment.End) {
                                    Text(
                                        money(c.balance),
                                        fontWeight = FontWeight.Bold, fontSize = 14.sp,
                                        color = when {
                                            c.isOverdue -> MaterialTheme.colorScheme.error
                                            c.balance > 0 -> statusColor("pending")
                                            else -> statusColor("delivered")
                                        },
                                    )
                                    Text(if (c.balance > 0) "doit" else "à jour", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                    if (c.isOverdue) Text("⚠️ limite dépassée", fontSize = 11.sp, color = MaterialTheme.colorScheme.error)
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
    }
}
